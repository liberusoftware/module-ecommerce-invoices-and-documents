<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\BurnNumber;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\DraftDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\RecordDelivery;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DeliveryState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\BurnedNumber;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DeliveryAttempt;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DocumentEvent;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DocumentLine;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Series;
use Liberu\Ecommerce\InvoicesAndDocuments\Policies\CustodyPolicy;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\ListDocuments;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\SummariseDocument;

it('gives a second merchant its own document for a deliberately identical sale reference', function (): void {
    bindSale(tenantId: 'tenant-a', saleRef: 'order-1');
    bindSale(tenantId: 'tenant-b', saleRef: 'order-1');

    $a = (new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-1');
    $b = (new DraftDocument())('tenant-b', DocumentKind::Invoice, 'order-1');

    expect($a->happened())->toBeTrue()
        ->and($b->happened())->toBeTrue()
        ->and($b->id)->not->toBe($a->id)
        ->and(Document::query()->count())->toBe(2)
        ->and((new ListDocuments())('tenant-a'))->toHaveCount(1)
        ->and((new ListDocuments())('tenant-b'))->toHaveCount(1);
});

it('gives a second merchant its own series and its own number one', function (): void {
    $a = issued('tenant-a', 'order-1');
    $b = issued('tenant-b', 'order-1');

    expect($a->number)->toBe('INV-00001')
        ->and($b->number)->toBe('INV-00001')
        ->and($a->series_id)->not->toBe($b->series_id)
        ->and(Series::query()->count())->toBe(2);
});

it('gives a second merchant its own delivery for an identical delivery reference', function (): void {
    bindTransport();
    $a = issued('tenant-a', 'order-1');
    $b = issued('tenant-b', 'order-1');

    expect((new RecordDelivery())('tenant-a', $a, 'send-1', 'email')->happened())->toBeTrue()
        ->and((new RecordDelivery())('tenant-b', $b, 'send-1', 'email')->happened())->toBeTrue()
        ->and(DeliveryAttempt::query()->count())->toBe(2);
});

it('stamps every row with the tenant that owns it', function (): void {
    bindTransport();
    series(code: 'LOOSE', gapless: false);
    $document = issued(code: 'INV');
    (new RecordDelivery())('tenant-a', $document, 'send-1', 'email');
    (new BurnNumber())('tenant-a', 'LOOSE', 'reserved');

    expect(Document::query()->where('tenant_id', 'tenant-a')->count())->toBe(1)
        ->and(DocumentLine::query()->where('tenant_id', 'tenant-a')->count())->toBe(1)
        ->and(DocumentEvent::query()->where('tenant_id', 'tenant-a')->count())->toBe(3)
        ->and(DeliveryAttempt::query()->where('tenant_id', 'tenant-a')->count())->toBe(1)
        ->and(BurnedNumber::query()->where('tenant_id', 'tenant-a')->count())->toBe(1)
        ->and(Series::query()->where('tenant_id', 'tenant-a')->count())->toBe(2)
        ->and(Document::query()->whereNull('tenant_id')->count())->toBe(0);
});

it('restates the tenant on a loaded relation, excluding a row planted under another', function (): void {
    $document = issued();

    DocumentLine::query()->create([
        'tenant_id' => 'tenant-b',
        'document_id' => $document->id,
        'position' => 99,
        'description' => 'Another merchant put this here',
        'quantity_milli' => 1000,
        'unit_net_minor' => 5000,
        'net_minor' => 5000,
        'tax_rate_bp' => 0,
        'tax_minor' => 0,
        'gross_minor' => 5000,
    ]);

    expect(DocumentLine::query()->where('document_id', $document->id)->count())->toBe(2)
        ->and($document->lines()->count())->toBe(1)
        ->and((new SummariseDocument())('tenant-a', $document)->gross->minor)->toBe(1200);
});

it('drops the restatement when there is no tenant to restate, so withCount does not report zero', function (): void {
    $document = issued();

    DocumentEvent::query()->create([
        'tenant_id' => 'tenant-b',
        'document_id' => $document->id,
        'sequence' => 500,
        'kind' => EventKind::Voided,
        'to_state' => DocumentState::Void->value,
        'occurred_at' => Carbon::now(),
    ]);

    $counted = Document::query()->withCount(['lines', 'events', 'deliveries'])->findOrFail($document->id);

    // The fresh instance the relation is built from has no tenant, so the guard
    // stands down and the count is the real one rather than nothing at all.
    expect($counted->getAttribute('lines_count'))->toBe(1)
        ->and($counted->getAttribute('events_count'))->toBe(3)
        ->and($counted->getAttribute('deliveries_count'))->toBe(0);

    // whereHas is the same construction, and must find the document rather than none.
    expect(Document::query()->whereHas('lines')->count())->toBe(1)
        ->and(Series::query()->withCount(['documents', 'burnedNumbers'])->firstOrFail()->getAttribute('documents_count'))->toBe(1);
});

it('never reports zero because a null tenant became an empty string', function (): void {
    $document = issued();
    $fresh = new Document();

    expect($fresh->getAttribute('tenant_id'))->toBeNull()
        ->and($fresh->lines()->toBase()->toSql())->not->toContain('tenant_id')
        ->and($document->lines()->toBase()->toSql())->toContain('tenant_id');
});

it('answers standing from the buyer reference, never from a role', function (): void {
    $document = issued(buyerRef: 'person-7');

    expect(CustodyPolicy::ownsDocument($document, 'tenant-a'))->toBeTrue()
        ->and(CustodyPolicy::ownsDocument($document, 'tenant-b'))->toBeFalse()
        ->and(CustodyPolicy::buyerMayRead($document, 'tenant-a', 'person-7'))->toBeTrue()
        ->and(CustodyPolicy::buyerMayRead($document, 'tenant-a', 'person-8'))->toBeFalse()
        ->and(CustodyPolicy::buyerMayRead($document, 'tenant-b', 'person-7'))->toBeFalse();

    $document->forceFill(['redacted_at' => Carbon::now()])->save();

    expect(CustodyPolicy::buyerMayRead($document, 'tenant-a', 'person-7'))->toBeFalse();
});

it('keeps one merchant delivery state out of another merchant document', function (): void {
    bindTransport();
    $a = issued('tenant-a', 'order-1');
    issued('tenant-b', 'order-1');

    (new RecordDelivery())('tenant-a', $a, 'send-1', 'email');

    expect(DeliveryAttempt::query()->where('tenant_id', 'tenant-a')->where('state', DeliveryState::Sent->value)->count())->toBe(1)
        ->and(DeliveryAttempt::query()->where('tenant_id', 'tenant-b')->count())->toBe(0);
});
