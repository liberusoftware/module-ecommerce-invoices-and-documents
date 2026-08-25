<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\DraftDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Contracts\DocumentRenderer;
use Liberu\Ecommerce\InvoicesAndDocuments\Contracts\DocumentTransport;
use Liberu\Ecommerce\InvoicesAndDocuments\Contracts\SaleSource;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Rendered;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\RenderModel;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\RenderDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Seams;
use Liberu\Ecommerce\InvoicesAndDocuments\Tests\Fakes\FakeRenderer;
use Liberu\Ecommerce\InvoicesAndDocuments\Tests\Fakes\FakeSaleSource;
use Liberu\Ecommerce\InvoicesAndDocuments\Tests\Fakes\FakeTransport;

it('binds nothing by default', function (): void {
    expect(Seams::saleSource())->toBeNull()
        ->and(Seams::renderer())->toBeNull()
        ->and(Seams::transport())->toBeNull();
});

it('refuses to draft with no sale source, and writes nothing', function (): void {
    expect((new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-1')->reason)
        ->toBe(RefusalReason::SaleSourceUnbound)
        ->and(Document::query()->count())->toBe(0);
});

it('resolves a seam given as a class name through the container', function (): void {
    Config::set('invoices-and-documents.seams.sale', FakeSaleSource::class);
    Config::set('invoices-and-documents.seams.renderer', FakeRenderer::class);
    Config::set('invoices-and-documents.seams.transport', FakeTransport::class);

    expect(Seams::saleSource())->toBeInstanceOf(FakeSaleSource::class)
        ->and(Seams::renderer())->toBeInstanceOf(FakeRenderer::class)
        ->and(Seams::transport())->toBeInstanceOf(FakeTransport::class);
});

it('answers null for a class name that is not the contract', function (): void {
    Config::set('invoices-and-documents.seams.sale', FakeRenderer::class);

    expect(Seams::saleSource())->toBeNull();
});

it('falls back to a container binding of the contract itself', function (): void {
    app()->bind(SaleSource::class, fn (): FakeSaleSource => new FakeSaleSource());

    expect(Seams::saleSource())->toBeInstanceOf(FakeSaleSource::class);
});

it('answers null when the container binds the contract to the wrong thing', function (): void {
    app()->bind(DocumentTransport::class, fn (): FakeRenderer => new FakeRenderer());

    expect(Seams::transport())->toBeNull();
});

it('takes a rebinding on the next call rather than the next deploy', function (): void {
    expect(Seams::renderer())->toBeNull();

    $renderer = bindRenderer();
    expect(Seams::renderer())->toBe($renderer);

    Config::set('invoices-and-documents.seams.renderer', null);
    expect(Seams::renderer())->toBeNull();
});

it('still knows what the document says when no renderer is bound', function (): void {
    $document = issued();
    $result = (new RenderDocument())('tenant-a', $document);

    expect($result->isRendered())->toBeFalse()
        ->and($result->rendered)->toBeNull()
        ->and($result->unavailable)->toBe(RefusalReason::NoRendererBound)
        ->and($result->model->number)->toBe('INV-00001')
        ->and($result->model->lines)->toHaveCount(1)
        ->and($result->model->summary->gross->minor)->toBe(1200);
});

it('tells a declined render apart from an unasked one', function (): void {
    $document = issued();
    bindRenderer(declines: true);

    $result = (new RenderDocument())('tenant-a', $document);

    expect($result->isRendered())->toBeFalse()
        ->and($result->unavailable)->toBe(RefusalReason::RendererDeclined);
});

it('hands the renderer everything the document says and nothing from anywhere else', function (): void {
    $document = issued(lines: [line('A widget', 1000, 2000, 200), line('Delivery', 500, 0, 0)]);
    bindRenderer();

    $result = (new RenderDocument())('tenant-a', $document);
    $model = $result->model;

    expect($result->isRendered())->toBeTrue()
        ->and($result->rendered?->filename)->toBe('INV-00001.pdf')
        ->and($model->kind)->toBe(DocumentKind::Invoice)
        ->and($model->seller->name)->toBe('Merchant Ltd')
        ->and($model->buyer->name)->toBe('A Buyer')
        ->and($model->buyer->email)->toBe('buyer@example.test')
        ->and($model->lines[0]->description)->toBe('A widget')
        ->and($model->lines[0]->quantity())->toBe('1')
        ->and($model->lines[1]->description)->toBe('Delivery')
        ->and($model->summary->byRate)->toHaveCount(2)
        ->and($model->correctsNumber)->toBeNull()
        ->and($model->issuedAt)->not->toBeNull()
        ->and($model->tenantId)->toBe('tenant-a');
});

it('confirms the renderer contract is satisfied by an anonymous binding too', function (): void {
    $renderer = new class() implements DocumentRenderer
    {
        public function render(RenderModel $model): ?Rendered
        {
            return null;
        }
    };

    Config::set('invoices-and-documents.seams.renderer', $renderer);

    expect(Seams::renderer())->toBe($renderer);
});

it('refuses to render another tenant document', function (): void {
    bindRenderer();

    expect(fn () => (new RenderDocument())('tenant-b', issued()))
        ->toThrow(NotFound::class, 'No such document');
});
