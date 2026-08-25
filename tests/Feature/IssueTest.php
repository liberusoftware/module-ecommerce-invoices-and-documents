<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\DraftDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\IssueDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\VoidDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Line;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Party;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Sale;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\DocumentIssued;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\DocumentVoided;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DocumentLine;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\FindDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\ListDocuments;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\SummariseDocument;

it('copies the sale into rows it owns and never asks again', function (): void {
    $source = bindSale();
    $document = draft();

    expect($source->asked)->toBe(1)
        ->and($document->state)->toBe(DocumentState::Draft)
        ->and($document->number)->toBeNull()
        ->and($document->seller_name)->toBe('Merchant Ltd')
        ->and($document->buyer_name)->toBe('A Buyer')
        ->and($document->currency)->toBe('GBP');

    // The catalogue changes; the document does not.
    $source->sales = [];

    $summary = (new SummariseDocument())('tenant-a', $document);
    expect($summary->gross->minor)->toBe(1200)
        ->and($document->lines()->first()?->description)->toBe('A widget')
        ->and($source->asked)->toBe(1);
});

it('sums totals from the lines rather than storing one beside them', function (): void {
    $document = draft(lines: [line(netMinor: 1000, taxMinor: 200), line('Second', 500, 0, 0)]);
    $summary = (new SummariseDocument())('tenant-a', $document);

    expect($summary->net->minor)->toBe(1500)
        ->and($summary->tax->minor)->toBe(200)
        ->and($summary->gross->minor)->toBe(1700)
        ->and($summary->byRate)->toHaveCount(2);

    expect(array_keys(Document::query()->firstOrFail()->getAttributes()))
        ->not->toContain('total_amount', 'net_minor', 'gross_minor');
});

it('refuses a sale whose stated total disagrees with its lines', function (): void {
    $source = bindSale();
    $source->offer('tenant-a', 'order-9', [line()], null, new Money(9999, 'GBP'));
    Config::set('invoices-and-documents.seams.sale', $source);

    $outcome = (new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-9');

    expect($outcome->wasRefused())->toBeTrue()
        ->and($outcome->reason)->toBe(RefusalReason::StatedTotalDisagreesWithLines)
        ->and(Document::query()->count())->toBe(0);
});

it('refuses a sale whose lines do not share one currency', function (): void {
    $mixed = [
        line(),
        new Line('euro', 1000, new Money(1, 'EUR'), new Money(1, 'EUR'), 0, new Money(0, 'EUR'), new Money(1, 'EUR')),
    ];
    $source = bindSale();
    $source->sales['tenant-a/order-mixed'] = new Sale(
        'order-mixed',
        new Party('seller-1', 'Merchant Ltd', '1 Trade Street'),
        new Party('person-1', 'A Buyer', '2 Home Road'),
        $mixed,
        new Money(1001, 'GBP'),
        new Money(200, 'GBP'),
        new Money(1201, 'GBP'),
    );

    expect((new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-mixed')->reason)
        ->toBe(RefusalReason::MixedCurrencies);
});

it('refuses a sale whose stated totals are in another currency than its lines', function (): void {
    $source = bindSale();
    $source->sales['tenant-a/order-eur'] = new Sale(
        'order-eur',
        new Party('seller-1', 'Merchant Ltd', '1 Trade Street'),
        new Party('person-1', 'A Buyer', '2 Home Road'),
        [line()],
        new Money(1000, 'EUR'),
        new Money(200, 'EUR'),
        new Money(1200, 'EUR'),
    );

    expect((new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-eur')->reason)
        ->toBe(RefusalReason::MixedCurrencies);
});

it('refuses a sale with no lines', function (): void {
    $source = bindSale();
    $source->sales['tenant-a/order-empty'] = new Sale(
        'order-empty',
        new Party('seller-1', 'Merchant Ltd', '1 Trade Street'),
        new Party('person-1', 'A Buyer', '2 Home Road'),
        [],
        Money::zero('GBP'),
        Money::zero('GBP'),
        Money::zero('GBP'),
    );

    expect((new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-empty')->reason)
        ->toBe(RefusalReason::SaleHasNoLines);
});

it('refuses a sale it has never heard of', function (): void {
    bindSale();

    expect((new DraftDocument())('tenant-a', DocumentKind::Invoice, 'nothing-like-it')->reason)
        ->toBe(RefusalReason::SaleNotFound);
});

it('refuses to draft a credit note from a sale', function (): void {
    bindSale();

    expect((new DraftDocument())('tenant-a', DocumentKind::CreditNote, 'order-1')->reason)
        ->toBe(RefusalReason::CreditNoteRequiresCorrectedDocument);
});

it('treats the cause as the natural key and returns the same document twice', function (): void {
    bindSale();
    $first = (new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-1');
    $second = (new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-1');

    expect($first->happened())->toBeTrue()
        ->and($second->happened())->toBeFalse()
        ->and($second->id)->toBe($first->id)
        ->and(Document::query()->count())->toBe(1);
});

it('lets one sale carry an invoice and a receipt', function (): void {
    bindSale();

    expect((new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-1')->happened())->toBeTrue()
        ->and((new DraftDocument())('tenant-a', DocumentKind::Receipt, 'order-1')->happened())->toBeTrue()
        ->and(Document::query()->count())->toBe(2);
});

it('numbers a document only when it issues, and says so', function (): void {
    Event::fake();
    $document = draft();
    series();

    $outcome = (new IssueDocument())('tenant-a', $document, 'INV', 'clerk-1');
    $document->refresh();

    expect($outcome->happened())->toBeTrue()
        ->and($document->state)->toBe(DocumentState::Issued)
        ->and($document->number)->toBe('INV-00001')
        ->and($document->number_sequence)->toBe(1)
        ->and($document->issued_at)->not->toBeNull();

    Event::assertDispatched(DocumentIssued::class, fn (DocumentIssued $event): bool => $event->tenantId === 'tenant-a'
        && $event->number === 'INV-00001'
        && $event->grossMinor === 1200
        && $event->currency === 'GBP'
        && $event->sourceRef === 'order-1');

    $issue = $document->events()->where('kind', EventKind::Issued->value)->firstOrFail();
    expect($issue->from_state)->toBe(DocumentState::Draft->value)
        ->and($issue->to_state)->toBe(DocumentState::Issued->value)
        ->and($issue->actor_ref)->toBe('clerk-1');
});

it('reports a second issue as already recorded rather than spending another number', function (): void {
    $document = issued();
    $outcome = (new IssueDocument())('tenant-a', $document, 'INV');

    expect($outcome->happened())->toBeFalse()
        ->and($outcome->id)->toBe($document->id)
        ->and($document->fresh()?->number)->toBe('INV-00001');
});

it('refuses to issue a fiscal document with no series', function (): void {
    expect((new IssueDocument())('tenant-a', draft())->reason)->toBe(RefusalReason::SeriesRequired);
});

it('refuses a series this tenant does not have', function (): void {
    series('tenant-b', 'INV');

    expect((new IssueDocument())('tenant-a', draft(), 'INV')->reason)->toBe(RefusalReason::SeriesNotFound);
});

it('refuses to file a proforma under a fiscal series', function (): void {
    $proforma = draft(kind: DocumentKind::Proforma);
    series();

    expect((new IssueDocument())('tenant-a', $proforma, 'INV')->reason)
        ->toBe(RefusalReason::ProformaMayNotUseFiscalSeries);
});

it('issues a proforma unnumbered, and numbered from a non-fiscal series', function (): void {
    $unnumbered = draft(kind: DocumentKind::Proforma);
    expect((new IssueDocument())('tenant-a', $unnumbered)->happened())->toBeTrue()
        ->and($unnumbered->fresh()?->number)->toBeNull();

    series(code: 'PRO', fiscal: false);
    $numbered = draft(saleRef: 'order-2', kind: DocumentKind::Proforma);
    expect((new IssueDocument())('tenant-a', $numbered, 'PRO')->happened())->toBeTrue()
        ->and($numbered->fresh()?->number)->toBe('PRO-00001');
});

it('refuses to issue a voided document', function (): void {
    $document = draft();
    (new VoidDocument())('tenant-a', $document, 'abandoned');
    series();

    expect((new IssueDocument())('tenant-a', $document, 'INV')->reason)->toBe(RefusalReason::IllegalTransition);
});

it('voids without erasing, and keeps the number', function (): void {
    Event::fake();
    $document = issued();

    $outcome = (new VoidDocument())('tenant-a', $document, 'wrong buyer', 'clerk-2');
    $document->refresh();

    expect($outcome->happened())->toBeTrue()
        ->and($document->state)->toBe(DocumentState::Void)
        ->and($document->number)->toBe('INV-00001')
        ->and($document->void_reason)->toBe('wrong buyer')
        ->and($document->voided_at)->not->toBeNull()
        ->and(DocumentLine::query()->where('document_id', $document->id)->count())->toBe(1);

    Event::assertDispatched(DocumentVoided::class, fn (DocumentVoided $e): bool => $e->number === 'INV-00001' && $e->reason === 'wrong buyer');

    expect((new VoidDocument())('tenant-a', $document, 'again')->happened())->toBeFalse();
});

it('finds and lists documents by reference rather than by primary key', function (): void {
    $document = issued();
    $other = draft(saleRef: 'order-2');

    expect((new FindDocument())('tenant-a', $document->reference)?->id)->toBe($document->id)
        ->and((new FindDocument())('tenant-b', $document->reference))->toBeNull()
        ->and((new FindDocument())('tenant-a', 'no-such-reference'))->toBeNull()
        ->and((new ListDocuments())('tenant-a'))->toHaveCount(2)
        ->and((new ListDocuments())('tenant-a', DocumentKind::Invoice))->toHaveCount(2)
        ->and((new ListDocuments())('tenant-a', null, DocumentState::Draft)->first()?->id)->toBe($other->id)
        ->and((new ListDocuments())('tenant-a', null, null, 'person-1'))->toHaveCount(2)
        ->and((new ListDocuments())('tenant-a', null, null, 'nobody'))->toHaveCount(0);
});

it('refuses every read and write for another tenant', function (): void {
    $document = issued();

    expect(fn () => (new IssueDocument())('tenant-b', $document, 'INV'))->toThrow(NotFound::class)
        ->and(fn () => (new VoidDocument())('tenant-b', $document, 'x'))->toThrow(NotFound::class)
        ->and(fn () => (new SummariseDocument())('tenant-b', $document))->toThrow(NotFound::class);
});
