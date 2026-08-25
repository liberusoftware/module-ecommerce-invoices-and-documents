<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\DraftDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\IssueDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\OpenSeries;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Line;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Party;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\TransportOutcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Tests\Fakes\FakeRenderer;
use Liberu\Ecommerce\InvoicesAndDocuments\Tests\Fakes\FakeSaleSource;
use Liberu\Ecommerce\InvoicesAndDocuments\Tests\Fakes\FakeTransport;
use Liberu\PackageTestbench\PackageTestCase;

uses(PackageTestCase::class, RefreshDatabase::class)->in('Feature');

/*
 * No test inherits a binding. A suite that leaked one would prove the opposite
 * of what half of it claims about an unbound seam.
 */
uses()->beforeEach(function (): void {
    Config::set('invoices-and-documents.seams.sale', null);
    Config::set('invoices-and-documents.seams.renderer', null);
    Config::set('invoices-and-documents.seams.transport', null);
    Config::set('invoices-and-documents.redaction_token', 'redacted');
    Config::set('invoices-and-documents.retention.years', null);
})->in('Feature');

function line(string $description = 'A widget', int $netMinor = 1000, int $rateBp = 2000, int $taxMinor = 200, int $quantityMilli = 1000, string $currency = 'GBP'): Line
{
    return new Line(
        $description,
        $quantityMilli,
        new Money($netMinor, $currency),
        new Money($netMinor, $currency),
        $rateBp,
        new Money($taxMinor, $currency),
        new Money($netMinor + $taxMinor, $currency),
    );
}

/** @param  list<Line>|null  $lines */
function bindSale(?array $lines = null, string $tenantId = 'tenant-a', string $saleRef = 'order-1', ?string $buyerRef = null): FakeSaleSource
{
    $source = Config::get('invoices-and-documents.seams.sale');
    $source = $source instanceof FakeSaleSource ? $source : new FakeSaleSource();

    $source->offer($tenantId, $saleRef, $lines ?? [line()], $buyerRef === null ? null : buyer($buyerRef));
    Config::set('invoices-and-documents.seams.sale', $source);

    return $source;
}

function buyer(string $reference): Party
{
    return new Party($reference, 'A Buyer', '2 Home Road', null, $reference.'@example.test');
}

function bindRenderer(bool $declines = false): FakeRenderer
{
    $renderer = new FakeRenderer($declines);
    Config::set('invoices-and-documents.seams.renderer', $renderer);

    return $renderer;
}

function bindTransport(?TransportOutcome $answer = null): FakeTransport
{
    $transport = new FakeTransport($answer);
    Config::set('invoices-and-documents.seams.transport', $transport);

    return $transport;
}

function series(string $tenantId = 'tenant-a', string $code = 'INV', bool $fiscal = true, bool $gapless = true, int $startAt = 1): string
{
    (new OpenSeries())($tenantId, $code, $code.'-', 5, $fiscal, $gapless, $startAt);

    return $code;
}

/** A drafted document, frozen and unnumbered. */
function draft(string $tenantId = 'tenant-a', string $saleRef = 'order-1', DocumentKind $kind = DocumentKind::Invoice, ?array $lines = null, ?string $buyerRef = null): Document
{
    bindSale($lines, $tenantId, $saleRef, $buyerRef);
    $outcome = (new DraftDocument())($tenantId, $kind, $saleRef);

    return Document::query()->findOrFail($outcome->id);
}

/** A document issued under a fiscal series. */
function issued(string $tenantId = 'tenant-a', string $saleRef = 'order-1', DocumentKind $kind = DocumentKind::Invoice, ?array $lines = null, ?string $buyerRef = null, string $code = 'INV'): Document
{
    $document = draft($tenantId, $saleRef, $kind, $lines, $buyerRef);
    series($tenantId, $code);
    (new IssueDocument())($tenantId, $document, $code);

    return $document->fresh() ?? $document;
}
