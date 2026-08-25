<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\ForgetReport;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\RetentionRefusal;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\ParticipantForgotten;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Redaction;

/**
 * Retention outranks erasure, and the conflict is returned rather than settled
 * silently in either direction. The host redacted the customer row in place and
 * rewrote every invoice ever issued to that person; it had no rule that could
 * have refused, so it made the choice by accident.
 *
 * Person-wide across every tenant, exactly as the export is, so the two agree
 * about what "everything" means.
 */
final class ForgetParticipant
{
    public function __invoke(string $subjectReference): ForgetReport
    {
        $documents = Document::query()->where('buyer_ref', $subjectReference)->orderBy('id')->get();

        $redacted = [];
        $refused = [];
        $contacts = 0;
        $deliveries = 0;

        /** @var array<string, array{redacted: int, refused: int}> $tenants */
        $tenants = [];

        foreach ($documents as $document) {
            $tenant = $document->tenant_id;
            $tenants[$tenant] ??= ['redacted' => 0, 'refused' => 0];

            $deliveries += $this->redactDeliveries($document);

            if ($document->buyer_email !== null || $document->note !== null) {
                $document->forceFill(['buyer_email' => null, 'note' => null])->save();
                $contacts++;
            }

            if ($this->isRetained($document)) {
                $refused[] = new RetentionRefusal($tenant, $document->reference, $document->number, $document->retain_until);
                $tenants[$tenant]['refused']++;

                continue;
            }

            $document->forceFill([
                'buyer_ref' => Redaction::subject($document->reference),
                'buyer_name' => Redaction::token(),
                'buyer_address' => Redaction::token(),
                'buyer_tax_id' => null,
                'redacted_at' => Carbon::now(),
            ])->save();

            $document->recordEvent(EventKind::Redacted, $document->state, $document->state);

            $redacted[] = $document->reference;
            $tenants[$tenant]['redacted']++;
        }

        foreach ($tenants as $tenant => $counts) {
            Event::dispatch(new ParticipantForgotten($tenant, $subjectReference, $counts['redacted'], $counts['refused']));
        }

        return new ForgetReport($subjectReference, $redacted, $refused, $contacts, $deliveries);
    }

    /** A document never issued has no retention; one issued under no configured window has an unknown one, which is not zero. */
    private function isRetained(Document $document): bool
    {
        if (! $document->issued_at instanceof Carbon) {
            return false;
        }

        return ! $document->retain_until instanceof Carbon || $document->retain_until->isFuture();
    }

    private function redactDeliveries(Document $document): int
    {
        $redacted = 0;

        foreach ($document->deliveries()->whereNull('redacted_at')->get() as $attempt) {
            $attempt->forceFill(['address' => null, 'redacted_at' => Carbon::now()])->save();
            $redacted++;
        }

        return $redacted;
    }
}
