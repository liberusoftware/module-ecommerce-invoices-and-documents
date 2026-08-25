<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Line;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Outcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Policies\CustodyPolicy;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Frozen;

/**
 * A document is corrected by another document, never by an edit. The identities
 * are copied from the document being corrected rather than re-read from the
 * sale, so a credit note carries the buyer the invoice carried.
 */
final class DraftCreditNote
{
    /** @param  list<Line>  $lines */
    public function __invoke(string $tenantId, Document $corrected, string $sourceRef, array $lines, ?string $note = null): Outcome
    {
        if (! CustodyPolicy::ownsDocument($corrected, $tenantId)) {
            throw NotFound::document();
        }

        if ($corrected->kind->correctsAnother()) {
            return Outcome::refused(RefusalReason::NotCorrectable);
        }

        if (! $corrected->state->isIssued()) {
            return Outcome::refused(RefusalReason::NotIssued);
        }

        if ($lines === []) {
            return Outcome::refused(RefusalReason::SaleHasNoLines);
        }

        $currency = Frozen::currencyOf($lines);

        if ($currency === null || $currency[0] !== $corrected->currency || $currency[1] !== $corrected->currency_exponent) {
            return Outcome::refused(RefusalReason::MixedCurrencies);
        }

        [$code, $exponent] = $currency;
        $credited = Frozen::summarise($lines, $code, $exponent)->gross;

        if ($credited->plus($this->alreadyCredited($corrected))->exceeds($this->grossOf($corrected))) {
            return Outcome::refused(RefusalReason::ExceedsCorrectedDocument);
        }

        try {
            $creditNote = DB::transaction(function () use ($tenantId, $corrected, $sourceRef, $lines, $note): Document {
                $document = Document::query()->create([
                    'tenant_id' => $tenantId,
                    'reference' => Document::mintReference(),
                    'kind' => DocumentKind::CreditNote,
                    'state' => DocumentState::Draft,
                    'source_ref' => $sourceRef,
                    'currency' => $corrected->currency,
                    'currency_exponent' => $corrected->currency_exponent,
                    'corrects_document_id' => $corrected->id,
                    'seller_ref' => $corrected->seller_ref,
                    'seller_name' => $corrected->seller_name,
                    'seller_address' => $corrected->seller_address,
                    'seller_tax_id' => $corrected->seller_tax_id,
                    'buyer_ref' => $corrected->buyer_ref,
                    'buyer_name' => $corrected->buyer_name,
                    'buyer_address' => $corrected->buyer_address,
                    'buyer_tax_id' => $corrected->buyer_tax_id,
                    'buyer_email' => $corrected->buyer_email,
                    'note' => $note,
                ]);

                Frozen::write($document, $lines);
                $document->recordEvent(EventKind::Drafted, DocumentState::Draft, null, null, ['corrects' => $corrected->reference]);

                return $document;
            });
        } catch (QueryException $exception) {
            $existing = Document::query()
                ->where('tenant_id', $tenantId)
                ->where('kind', DocumentKind::CreditNote->value)
                ->where('source_ref', $sourceRef)
                ->first();

            if (! $existing instanceof Document) {
                throw $exception;
            }

            return Outcome::alreadyRecorded($existing->id, $existing->reference);
        }

        return Outcome::recorded($creditNote->id, $creditNote->reference);
    }

    private function grossOf(Document $document): Money
    {
        return Frozen::summarise(Frozen::linesOf($document), $document->currency, $document->currency_exponent)->gross;
    }

    private function alreadyCredited(Document $corrected): Money
    {
        $total = Money::zero($corrected->currency, $corrected->currency_exponent);

        $notes = $corrected->corrections()->where('state', '!=', DocumentState::Void->value)->get();

        foreach ($notes as $credit) {
            $total = $total->plus($this->grossOf($credit));
        }

        return $total;
    }
}
