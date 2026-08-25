<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Outcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Frozen;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Seams;

/**
 * The one moment the sale is read. Everything the document will ever display is
 * copied here; after this the module asks the sale nothing.
 */
final class DraftDocument
{
    public function __invoke(string $tenantId, DocumentKind $kind, string $sourceRef, ?string $note = null): Outcome
    {
        if ($kind->correctsAnother()) {
            return Outcome::refused(RefusalReason::CreditNoteRequiresCorrectedDocument);
        }

        $source = Seams::saleSource();

        if ($source === null) {
            return Outcome::refused(RefusalReason::SaleSourceUnbound);
        }

        $sale = $source->sale($tenantId, $sourceRef);

        if ($sale === null) {
            return Outcome::refused(RefusalReason::SaleNotFound);
        }

        if ($sale->lines === []) {
            return Outcome::refused(RefusalReason::SaleHasNoLines);
        }

        $currency = Frozen::currencyOf($sale->lines);

        if ($currency === null
            || $sale->statedNet->currency !== $currency[0]
            || $sale->statedNet->exponent !== $currency[1]
            || $sale->statedTax->currency !== $currency[0]
            || $sale->statedGross->currency !== $currency[0]) {
            return Outcome::refused(RefusalReason::MixedCurrencies);
        }

        [$code, $exponent] = $currency;
        $summary = Frozen::summarise($sale->lines, $code, $exponent);

        if (! $summary->net->equals($sale->statedNet)
            || ! $summary->tax->equals($sale->statedTax)
            || ! $summary->gross->equals($sale->statedGross)) {
            return Outcome::refused(RefusalReason::StatedTotalDisagreesWithLines);
        }

        try {
            $document = DB::transaction(function () use ($tenantId, $kind, $sourceRef, $note, $sale, $code, $exponent): Document {
                $document = Document::query()->create([
                    'tenant_id' => $tenantId,
                    'reference' => Document::mintReference(),
                    'kind' => $kind,
                    'state' => DocumentState::Draft,
                    'source_ref' => $sourceRef,
                    'currency' => $code,
                    'currency_exponent' => $exponent,
                    'seller_ref' => $sale->seller->reference,
                    'seller_name' => $sale->seller->name,
                    'seller_address' => $sale->seller->address,
                    'seller_tax_id' => $sale->seller->taxId,
                    'buyer_ref' => $sale->buyer->reference,
                    'buyer_name' => $sale->buyer->name,
                    'buyer_address' => $sale->buyer->address,
                    'buyer_tax_id' => $sale->buyer->taxId,
                    'buyer_email' => $sale->buyer->email,
                    'note' => $note,
                ]);

                Frozen::write($document, $sale->lines);
                $document->recordEvent(EventKind::Drafted, DocumentState::Draft);

                return $document;
            });
        } catch (QueryException $exception) {
            $existing = Document::query()
                ->where('tenant_id', $tenantId)
                ->where('kind', $kind->value)
                ->where('source_ref', $sourceRef)
                ->first();

            if (! $existing instanceof Document) {
                throw $exception;
            }

            return Outcome::alreadyRecorded($existing->id, $existing->reference);
        }

        return Outcome::recorded($document->id, $document->reference);
    }
}
