<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Outcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DeliveryState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\DeliveryFailed;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\DocumentDelivered;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DeliveryAttempt;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Policies\CustodyPolicy;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\BuildRenderModel;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Seams;

/**
 * The attempt is a row before it is a transmission, and "delivered" is what a
 * transport said rather than what dispatching a mailable implied. The host had
 * a mailable nothing constructed and no fact anywhere.
 */
final class RecordDelivery
{
    public function __invoke(
        string $tenantId,
        Document $document,
        string $reference,
        string $channel,
        ?string $address = null,
    ): Outcome {
        if (! CustodyPolicy::ownsDocument($document, $tenantId)) {
            throw NotFound::document();
        }

        if (! $document->state->isIssued()) {
            return Outcome::refused(RefusalReason::NotIssued);
        }

        $address ??= $document->buyer_email;

        if ($address === null || $address === '') {
            return Outcome::refused(RefusalReason::NoDeliveryAddress);
        }

        try {
            $attempt = DeliveryAttempt::query()->create([
                'tenant_id' => $tenantId,
                'document_id' => $document->id,
                'reference' => $reference,
                'channel' => $channel,
                'address' => $address,
                'state' => DeliveryState::Pending,
                'attempted_at' => Carbon::now(),
            ]);
        } catch (QueryException $exception) {
            $existing = DeliveryAttempt::query()->where('tenant_id', $tenantId)->where('reference', $reference)->first();

            if (! $existing instanceof DeliveryAttempt) {
                throw $exception;
            }

            return Outcome::alreadyRecorded($existing->id, $existing->reference);
        }

        $transport = Seams::transport();

        if ($transport === null) {
            return Outcome::refused(RefusalReason::NoTransportBound);
        }

        $model = (new BuildRenderModel())($tenantId, $document);
        $rendered = Seams::renderer()?->render($model);
        $outcome = $transport->deliver($model, $rendered, $channel, $address);

        $attempt->forceFill([
            'state' => $outcome->state,
            'detail' => $outcome->detail,
            'settled_at' => Carbon::now(),
        ])->save();

        if ($outcome->state !== DeliveryState::Sent) {
            $document->recordEvent(EventKind::DeliveryFailed, $document->state, $document->state, null, [
                'delivery' => $reference,
                'state' => $outcome->state->value,
            ]);

            Event::dispatch(new DeliveryFailed($tenantId, $document->id, $document->reference, $channel, $outcome->state, $outcome->detail));

            return Outcome::refused($outcome->state === DeliveryState::Suppressed
                ? RefusalReason::TransportSuppressed
                : RefusalReason::TransportFailed);
        }

        if ($document->state === DocumentState::Issued) {
            $document->forceFill(['state' => DocumentState::Delivered, 'delivered_at' => Carbon::now()])->save();
            $document->recordEvent(EventKind::Delivered, DocumentState::Delivered, DocumentState::Issued, null, ['delivery' => $reference]);
        }

        Event::dispatch(new DocumentDelivered($tenantId, $document->id, $document->reference, $channel, $reference));

        return Outcome::recorded($attempt->id, $attempt->reference);
    }
}
