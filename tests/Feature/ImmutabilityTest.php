<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\DraftDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\DocumentsAreImmutable;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DocumentEvent;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DocumentLine;

it('refuses to change anything an issued document states', function (string $attribute, mixed $value): void {
    $document = issued();

    expect(fn () => $document->forceFill([$attribute => $value])->save())
        ->toThrow(DocumentsAreImmutable::class, 'Issue a credit note instead');
})->with([
    ['number', 'INV-09999'],
    ['currency', 'EUR'],
    ['source_ref', 'another-order'],
    ['seller_name', 'Someone Else Ltd'],
    ['issued_at', '2020-01-01 00:00:00'],
    ['kind', DocumentKind::Receipt->value],
    ['reference', 'forged'],
    ['tenant_id', 'tenant-b'],
]);

it('names every frozen attribute a caller tried to change at once', function (): void {
    $document = issued();

    expect(fn () => $document->forceFill(['number' => 'X', 'currency' => 'EUR'])->save())
        ->toThrow(DocumentsAreImmutable::class, 'number, currency');
});

it('still lets the state machine move and erasure redact', function (): void {
    $document = issued();

    $document->forceFill(['state' => DocumentState::Delivered, 'delivered_at' => Carbon::now(), 'buyer_name' => 'redacted'])->save();

    expect($document->fresh()?->state)->toBe(DocumentState::Delivered)
        ->and($document->fresh()?->buyer_name)->toBe('redacted');
});

it('lets a draft be corrected before it is issued', function (): void {
    $document = draft();
    $document->forceFill(['note' => 'Payable in 30 days'])->save();

    expect($document->fresh()?->note)->toBe('Payable in 30 days');
});

it('refuses to delete a document at all', function (): void {
    $document = draft();

    expect(fn () => $document->delete())->toThrow(DocumentsAreImmutable::class, 'Void it, which records rather than erases');
});

it('refuses to change or remove a frozen line', function (): void {
    $document = issued();
    $line = $document->lines()->firstOrFail();

    expect(fn () => $line->forceFill(['description' => 'Something cheaper'])->save())
        ->toThrow(DocumentsAreImmutable::class, 'frozen at issue')
        ->and(fn () => $line->delete())->toThrow(DocumentsAreImmutable::class);
});

it('keeps a document history append-only', function (): void {
    $document = issued();
    $event = $document->events()->firstOrFail();

    expect(fn () => $event->forceFill(['to_state' => 'void'])->save())
        ->toThrow(DocumentsAreImmutable::class, 'append-only')
        ->and(fn () => $event->delete())->toThrow(DocumentsAreImmutable::class);
});

it('lets the unique index arbitrate a concurrent history append', function (): void {
    $document = issued();
    $existing = $document->events()->orderBy('sequence')->firstOrFail();

    $duplicate = new DocumentEvent();
    $duplicate->forceFill([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'sequence' => $existing->sequence,
        'kind' => EventKind::Issued->value,
        'to_state' => DocumentState::Issued->value,
        'occurred_at' => Carbon::now(),
    ]);

    expect(fn () => $duplicate->save())->toThrow(QueryException::class);
});

it('lets the unique index arbitrate two documents racing for one cause', function (): void {
    bindSale();
    $first = (new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-1');

    $racer = new Document();
    $racer->forceFill([
        'tenant_id' => 'tenant-a',
        'reference' => Document::mintReference(),
        'kind' => DocumentKind::Invoice->value,
        'state' => DocumentState::Draft->value,
        'source_ref' => 'order-1',
        'currency' => 'GBP',
        'currency_exponent' => 2,
        'seller_ref' => 's', 'seller_name' => 's', 'seller_address' => 's',
        'buyer_ref' => 'b', 'buyer_name' => 'b', 'buyer_address' => 'b',
    ]);

    expect(fn () => $racer->save())->toThrow(QueryException::class)
        ->and(Document::query()->count())->toBe(1)
        ->and($first->happened())->toBeTrue();
});

it('lets the unique index arbitrate two documents racing for one number', function (): void {
    $document = issued();

    $racer = new Document();
    $racer->forceFill([
        'tenant_id' => 'tenant-a',
        'reference' => Document::mintReference(),
        'kind' => DocumentKind::Receipt->value,
        'state' => DocumentState::Issued->value,
        'source_ref' => 'order-99',
        'series_id' => $document->series_id,
        'number' => $document->number,
        'number_sequence' => $document->number_sequence,
        'currency' => 'GBP',
        'currency_exponent' => 2,
        'seller_ref' => 's', 'seller_name' => 's', 'seller_address' => 's',
        'buyer_ref' => 'b', 'buyer_name' => 'b', 'buyer_address' => 'b',
    ]);

    expect(fn () => $racer->save())->toThrow(QueryException::class);
});

it('lets the unique index arbitrate two lines racing for one position', function (): void {
    $document = issued();

    $duplicate = new DocumentLine();
    $duplicate->forceFill([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'position' => 1,
        'description' => 'Slipped in',
        'quantity_milli' => 1000,
        'unit_net_minor' => 1,
        'net_minor' => 1,
        'tax_rate_bp' => 0,
        'tax_minor' => 0,
        'gross_minor' => 1,
    ]);

    expect(fn () => $duplicate->save())->toThrow(QueryException::class);
});

it('rethrows a database error that is not this document already existing', function (): void {
    bindSale();
    (new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-1');
    DB::statement('CREATE UNIQUE INDEX documents_buyer_unique ON invoicing_documents (buyer_ref)');
    bindSale(tenantId: 'tenant-a', saleRef: 'order-2');

    expect(fn () => (new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-2'))
        ->toThrow(QueryException::class);
});
