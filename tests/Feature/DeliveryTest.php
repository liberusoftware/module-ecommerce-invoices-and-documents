<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\RecordDelivery;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\TransportOutcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DeliveryState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\DeliveryFailed;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\DocumentDelivered;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DeliveryAttempt;

it('records the attempt before it transmits, and delivers on what the transport said', function (): void {
    Event::fake();
    $document = issued();
    $renderer = bindRenderer();
    $transport = bindTransport();

    $outcome = (new RecordDelivery())('tenant-a', $document, 'send-1', 'email');
    $attempt = DeliveryAttempt::query()->firstOrFail();

    expect($outcome->happened())->toBeTrue()
        ->and($attempt->state)->toBe(DeliveryState::Sent)
        ->and($attempt->address)->toBe('buyer@example.test')
        ->and($attempt->settled_at)->not->toBeNull()
        ->and($transport->delivered)->toBe(1)
        ->and($transport->sawRendered?->mediaType)->toBe('application/pdf')
        ->and($renderer->sawModel?->number)->toBe('INV-00001')
        ->and($document->fresh()?->state)->toBe(DocumentState::Delivered)
        ->and($document->fresh()?->delivered_at)->not->toBeNull();

    Event::assertDispatched(DocumentDelivered::class, fn (DocumentDelivered $e): bool => $e->channel === 'email' && $e->deliveryReference === 'send-1');
});

it('records the attempt and refuses the transmission when no transport is bound', function (): void {
    Event::fake();
    $document = issued();

    $outcome = (new RecordDelivery())('tenant-a', $document, 'send-1', 'email');

    expect($outcome->reason)->toBe(RefusalReason::NoTransportBound)
        ->and(DeliveryAttempt::query()->firstOrFail()->state)->toBe(DeliveryState::Pending)
        ->and(DeliveryAttempt::query()->firstOrFail()->settled_at)->toBeNull()
        ->and($document->fresh()?->state)->toBe(DocumentState::Issued);

    Event::assertNotDispatched(DocumentDelivered::class);
});

it('delivers without a renderer, and hands the transport nothing rather than a guess', function (): void {
    $document = issued();
    $transport = bindTransport();

    expect((new RecordDelivery())('tenant-a', $document, 'send-1', 'email')->happened())->toBeTrue()
        ->and($transport->sawRendered)->toBeNull();
});

it('records a failure as a fact and leaves the document undelivered', function (): void {
    Event::fake();
    $document = issued();
    bindTransport(TransportOutcome::failed('mailbox full'));

    $outcome = (new RecordDelivery())('tenant-a', $document, 'send-1', 'email');

    expect($outcome->reason)->toBe(RefusalReason::TransportFailed)
        ->and(DeliveryAttempt::query()->firstOrFail()->state)->toBe(DeliveryState::Failed)
        ->and(DeliveryAttempt::query()->firstOrFail()->detail)->toBe('mailbox full')
        ->and($document->fresh()?->state)->toBe(DocumentState::Issued)
        ->and($document->events()->where('kind', EventKind::DeliveryFailed->value)->count())->toBe(1);

    Event::assertDispatched(DeliveryFailed::class, fn (DeliveryFailed $e): bool => $e->state === DeliveryState::Failed && $e->detail === 'mailbox full');
});

it('records a suppression as its own answer', function (): void {
    $document = issued();
    bindTransport(TransportOutcome::suppressed('unsubscribed'));

    expect((new RecordDelivery())('tenant-a', $document, 'send-1', 'email')->reason)
        ->toBe(RefusalReason::TransportSuppressed)
        ->and(DeliveryAttempt::query()->firstOrFail()->state)->toBe(DeliveryState::Suppressed);
});

it('lets one reference send once, whatever the caller retries', function (): void {
    $document = issued();
    $transport = bindTransport();

    $first = (new RecordDelivery())('tenant-a', $document, 'send-1', 'email');
    $second = (new RecordDelivery())('tenant-a', $document, 'send-1', 'email');

    expect($first->happened())->toBeTrue()
        ->and($second->happened())->toBeFalse()
        ->and($second->id)->toBe($first->id)
        ->and($transport->delivered)->toBe(1)
        ->and(DeliveryAttempt::query()->count())->toBe(1);
});

it('delivers a second time on another channel without moving the state again', function (): void {
    $document = issued();
    bindTransport();

    (new RecordDelivery())('tenant-a', $document, 'send-1', 'email');
    $second = (new RecordDelivery())('tenant-a', $document->fresh() ?? $document, 'send-2', 'post', '2 Home Road');

    expect($second->happened())->toBeTrue()
        ->and(DeliveryAttempt::query()->count())->toBe(2)
        ->and($document->fresh()?->state)->toBe(DocumentState::Delivered);
});

it('refuses to deliver a document that has not issued', function (): void {
    bindTransport();

    expect((new RecordDelivery())('tenant-a', draft(), 'send-1', 'email')->reason)->toBe(RefusalReason::NotIssued);
});

it('refuses to deliver with no address anywhere', function (): void {
    bindTransport();
    $document = issued(buyerRef: 'person-2');
    $document->forceFill(['buyer_email' => null])->save();

    expect((new RecordDelivery())('tenant-a', $document, 'send-1', 'email')->reason)
        ->toBe(RefusalReason::NoDeliveryAddress)
        ->and(DeliveryAttempt::query()->count())->toBe(0);
});

it('refuses to deliver another tenant document', function (): void {
    expect(fn () => (new RecordDelivery())('tenant-b', issued(), 'send-1', 'email'))->toThrow(NotFound::class);
});

it('rethrows a database error that is not this attempt already existing', function (): void {
    $document = issued();
    bindTransport();
    (new RecordDelivery())('tenant-a', $document, 'send-1', 'email');
    DB::statement('CREATE UNIQUE INDEX deliveries_channel_unique ON invoicing_deliveries (channel)');

    expect(fn () => (new RecordDelivery())('tenant-a', $document->fresh() ?? $document, 'send-2', 'email'))
        ->toThrow(QueryException::class);
});
