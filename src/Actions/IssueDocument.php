<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Outcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\DocumentIssued;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Series;
use Liberu\Ecommerce\InvoicesAndDocuments\Policies\CustodyPolicy;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Frozen;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Numbering;

/**
 * Issue, which is where the number is spent. It is spent inside the same
 * transaction that writes the document, so a rollback returns it: a fiscal
 * series cannot afford the gap `AllocateOrderNumber` deliberately accepts.
 */
final class IssueDocument
{
    public function __invoke(string $tenantId, Document $document, ?string $seriesCode = null, ?string $actorRef = null): Outcome
    {
        if (! CustodyPolicy::ownsDocument($document, $tenantId)) {
            throw NotFound::document();
        }

        if ($document->state->isIssued()) {
            return Outcome::alreadyRecorded($document->id, $document->reference);
        }

        if (! $document->state->canTransitionTo(DocumentState::Issued)) {
            return Outcome::refused(RefusalReason::IllegalTransition);
        }

        $series = null;

        if ($seriesCode !== null) {
            $series = Series::query()->where('tenant_id', $tenantId)->where('code', $seriesCode)->first();

            if (! $series instanceof Series) {
                return Outcome::refused(RefusalReason::SeriesNotFound);
            }

            if (! $document->kind->isFiscal() && $series->fiscal) {
                return Outcome::refused(RefusalReason::ProformaMayNotUseFiscalSeries);
            }
        } elseif ($document->kind->isFiscal()) {
            return Outcome::refused(RefusalReason::SeriesRequired);
        }

        DB::transaction(function () use ($document, $series, $actorRef): void {
            $number = null;
            $sequence = null;

            if ($series instanceof Series) {
                [$sequence, $number] = Numbering::spend($series);
            }

            $issuedAt = Carbon::now();

            $document->forceFill([
                'state' => DocumentState::Issued,
                'series_id' => $series?->id,
                'number' => $number,
                'number_sequence' => $sequence,
                'issued_at' => $issuedAt,
                'retain_until' => $this->retainUntil($issuedAt),
            ])->save();

            $document->recordEvent(EventKind::Issued, DocumentState::Issued, DocumentState::Draft, $actorRef, ['number' => $number]);
        });

        $summary = Frozen::summarise(Frozen::linesOf($document), $document->currency, $document->currency_exponent);

        Event::dispatch(new DocumentIssued(
            $tenantId,
            $document->id,
            $document->reference,
            $document->kind,
            $document->number,
            $document->source_ref,
            $summary->gross->minor,
            $document->currency,
        ));

        return Outcome::recorded($document->id, $document->reference);
    }

    /** Null when the host never said how long it keeps documents; erasure treats that as unknown, not as zero. */
    private function retainUntil(Carbon $issuedAt): ?Carbon
    {
        $years = Config::get('invoices-and-documents.retention.years');

        return is_int($years) && $years > 0 ? $issuedAt->copy()->addYears($years) : null;
    }
}
