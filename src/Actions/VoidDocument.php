<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Outcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\DocumentVoided;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Policies\CustodyPolicy;

/**
 * Void records; it does not erase. The number stays spent, which is what keeps
 * a gapless series gapless, and the reason is a row rather than a memory.
 */
final class VoidDocument
{
    public function __invoke(string $tenantId, Document $document, string $reason, ?string $actorRef = null): Outcome
    {
        if (! CustodyPolicy::ownsDocument($document, $tenantId)) {
            throw NotFound::document();
        }

        // Void is reachable from every state but Void, where it is already recorded.
        if (! $document->state->canTransitionTo(DocumentState::Void)) {
            return Outcome::alreadyRecorded($document->id, $document->reference);
        }

        $from = $document->state;

        $document->forceFill([
            'state' => DocumentState::Void,
            'voided_at' => Carbon::now(),
            'void_reason' => $reason,
        ])->save();

        $document->recordEvent(EventKind::Voided, DocumentState::Void, $from, $actorRef, ['reason' => $reason]);

        Event::dispatch(new DocumentVoided($tenantId, $document->id, $document->reference, $document->number, $reason));

        return Outcome::recorded($document->id, $document->reference);
    }
}
