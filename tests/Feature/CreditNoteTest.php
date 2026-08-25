<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\DraftCreditNote;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\IssueDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\VoidDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Line;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\BuildRenderModel;

it('corrects a document with another document that carries the identity it carried', function (): void {
    $invoice = issued();

    // The catalogue and the customer move on; the credit note does not follow them.
    $source = bindSale();
    $source->sales = [];
    $source->asked = 0;

    $outcome = (new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [line('A widget', 1000, 2000, 200)]);
    $note = Document::query()->findOrFail($outcome->id);

    expect($outcome->happened())->toBeTrue()
        ->and($note->kind)->toBe(DocumentKind::CreditNote)
        ->and($note->corrects_document_id)->toBe($invoice->id)
        ->and($note->buyer_name)->toBe($invoice->buyer_name)
        ->and($note->seller_name)->toBe($invoice->seller_name)
        ->and($note->currency)->toBe('GBP')
        ->and($source->asked)->toBe(0);

    series(code: 'CN');
    (new IssueDocument())('tenant-a', $note, 'CN');

    $model = (new BuildRenderModel())('tenant-a', $note->fresh() ?? $note);

    expect($model->correctsNumber)->toBe('INV-00001')
        ->and($model->correctsReference)->toBe($invoice->reference);
});

it('refuses to credit more than the document says, counting notes already issued', function (): void {
    $invoice = issued();

    expect((new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [line('A widget', 1200, 2000, 240)])->reason)
        ->toBe(RefusalReason::ExceedsCorrectedDocument);

    expect((new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [line('Half', 500, 2000, 100)])->happened())->toBeTrue();

    expect((new DraftCreditNote())('tenant-a', $invoice, 'refund-2', [line('The rest and more', 800, 2000, 160)])->reason)
        ->toBe(RefusalReason::ExceedsCorrectedDocument);

    expect((new DraftCreditNote())('tenant-a', $invoice, 'refund-3', [line('The rest', 500, 2000, 100)])->happened())->toBeTrue();
});

it('stops counting a credit note that was voided', function (): void {
    $invoice = issued();
    $first = (new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [line('All of it', 1000, 2000, 200)]);
    $note = Document::query()->findOrFail($first->id);

    expect((new DraftCreditNote())('tenant-a', $invoice, 'refund-2', [line('Again', 1000, 2000, 200)])->reason)
        ->toBe(RefusalReason::ExceedsCorrectedDocument);

    (new VoidDocument())('tenant-a', $note, 'raised in error');

    expect((new DraftCreditNote())('tenant-a', $invoice, 'refund-2', [line('Again', 1000, 2000, 200)])->happened())->toBeTrue();
});

it('refuses to correct a document that was never issued', function (): void {
    expect((new DraftCreditNote())('tenant-a', draft(), 'refund-1', [line()])->reason)->toBe(RefusalReason::NotIssued);
});

it('refuses to correct a credit note', function (): void {
    $invoice = issued();
    $outcome = (new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [line('Half', 500, 2000, 100)]);
    $note = Document::query()->findOrFail($outcome->id);
    series(code: 'CN');
    (new IssueDocument())('tenant-a', $note, 'CN');

    expect((new DraftCreditNote())('tenant-a', $note->fresh() ?? $note, 'refund-2', [line()])->reason)
        ->toBe(RefusalReason::NotCorrectable);
});

it('refuses a credit note with no lines or another currency', function (): void {
    $invoice = issued();
    $euro = new Line('euro', 1000, new Money(1, 'EUR'), new Money(1, 'EUR'), 0, new Money(0, 'EUR'), new Money(1, 'EUR'));

    expect((new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [])->reason)->toBe(RefusalReason::SaleHasNoLines)
        ->and((new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [$euro])->reason)->toBe(RefusalReason::MixedCurrencies);
});

it('treats the refund reference as the credit note natural key', function (): void {
    $invoice = issued();
    $first = (new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [line('Half', 500, 2000, 100)]);
    $second = (new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [line('Half', 500, 2000, 100)]);

    expect($second->happened())->toBeFalse()
        ->and($second->id)->toBe($first->id);
});

it('rethrows a database error that is not this credit note already existing', function (): void {
    $invoice = issued();
    (new DraftCreditNote())('tenant-a', $invoice, 'refund-1', [line('Quarter', 250, 2000, 50)]);
    DB::statement('CREATE UNIQUE INDEX documents_corrects_unique ON invoicing_documents (corrects_document_id)');

    expect(fn () => (new DraftCreditNote())('tenant-a', $invoice, 'refund-2', [line('Quarter', 250, 2000, 50)]))
        ->toThrow(QueryException::class);
});

it('refuses to correct another tenant document', function (): void {
    expect(fn () => (new DraftCreditNote())('tenant-b', issued(), 'refund-1', [line()]))->toThrow(NotFound::class);
});
