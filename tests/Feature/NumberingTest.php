<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\BurnNumber;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\IssueDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\OpenSeries;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\NumberBurned;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\BurnedNumber;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Series;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\CheckSeriesContinuity;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Numbering;

it('opens a series once per tenant and code', function (): void {
    $first = (new OpenSeries())('tenant-a', 'INV', 'INV-', 5);
    $second = (new OpenSeries())('tenant-a', 'INV', 'OTHER-', 2);

    expect($first->happened())->toBeTrue()
        ->and($second->happened())->toBeFalse()
        ->and($second->id)->toBe($first->id)
        ->and(Series::query()->count())->toBe(1)
        ->and(Series::query()->firstOrFail()->prefix)->toBe('INV-');

    expect((new OpenSeries())('tenant-b', 'INV')->happened())->toBeTrue();
});

it('rethrows a database error that is not this series already existing', function (): void {
    (new OpenSeries())('tenant-a', 'INV', 'SAME-');
    DB::statement('CREATE UNIQUE INDEX series_prefix_unique ON invoicing_series (prefix)');

    expect(fn () => (new OpenSeries())('tenant-a', 'OTHER', 'SAME-'))
        ->toThrow(QueryException::class);
});

it('spends numbers in order under the tenant that owns the series', function (): void {
    series();
    issued(saleRef: 'order-1');
    issued(saleRef: 'order-2');
    issued(saleRef: 'order-3');

    expect(Series::query()->firstOrFail()->next_value)->toBe(4);

    $report = (new CheckSeriesContinuity())('tenant-a', 'INV');

    expect($report->issued)->toBe(3)
        ->and($report->burned)->toBe(0)
        ->and($report->first)->toBe(1)
        ->and($report->last)->toBe(3)
        ->and($report->isContinuous())->toBeTrue()
        ->and($report->gapless)->toBeTrue();
});

it('returns the number when the issuing transaction rolls back', function (): void {
    series();
    $document = draft();

    try {
        DB::transaction(function () use ($document): void {
            (new IssueDocument())('tenant-a', $document, 'INV');

            throw new RuntimeException('the host changed its mind');
        });
    } catch (RuntimeException) {
        // The rollback is the assertion.
    }

    expect(Series::query()->firstOrFail()->next_value)->toBe(1)
        ->and($document->fresh()?->number)->toBeNull();

    $again = draft(saleRef: 'order-2');
    (new IssueDocument())('tenant-a', $again, 'INV');

    expect($again->fresh()?->number)->toBe('INV-00001');
});

it('refuses to burn a number from a gapless series', function (): void {
    series();

    $outcome = (new BurnNumber())('tenant-a', 'INV', 'a printer ate it');

    expect($outcome->reason)->toBe(RefusalReason::SeriesIsGapless)
        ->and(BurnedNumber::query()->count())->toBe(0)
        ->and(Series::query()->firstOrFail()->next_value)->toBe(1);
});

it('records the hole rather than leaving one, on a series that allows them', function (): void {
    Event::fake();
    series(code: 'LOOSE', gapless: false);
    issued(saleRef: 'order-1', code: 'LOOSE');

    $outcome = (new BurnNumber())('tenant-a', 'LOOSE', 'reserved by the tax office');
    issued(saleRef: 'order-2', code: 'LOOSE');

    expect($outcome->happened())->toBeTrue()
        ->and($outcome->reference)->toBe('LOOSE-00002');

    Event::assertDispatched(NumberBurned::class, fn (NumberBurned $e): bool => $e->number === 'LOOSE-00002' && $e->reason === 'reserved by the tax office');

    $report = (new CheckSeriesContinuity())('tenant-a', 'LOOSE');

    expect($report->issued)->toBe(2)
        ->and($report->burned)->toBe(1)
        ->and($report->last)->toBe(3)
        ->and($report->isContinuous())->toBeTrue();
});

it('names the numbers a series cannot account for', function (): void {
    series();
    issued(saleRef: 'order-1');

    // What a crashed process or a hand-edited counter leaves behind.
    Series::query()->firstOrFail()->forceFill(['next_value' => 4])->save();
    issued(saleRef: 'order-2');

    $report = (new CheckSeriesContinuity())('tenant-a', 'INV');

    expect($report->missing)->toBe([2, 3])
        ->and($report->isContinuous())->toBeFalse()
        ->and($report->gapless)->toBeTrue();
});

it('reports an unused series as continuous and empty', function (): void {
    series();
    $report = (new CheckSeriesContinuity())('tenant-a', 'INV');

    expect($report->first)->toBeNull()
        ->and($report->last)->toBeNull()
        ->and($report->issued)->toBe(0)
        ->and($report->isContinuous())->toBeTrue();
});

it('will not describe or burn a series belonging to another tenant', function (): void {
    series('tenant-b', 'INV');

    expect(fn () => (new CheckSeriesContinuity())('tenant-a', 'INV'))->toThrow(NotFound::class, 'No numbering series [INV]')
        ->and(fn () => (new BurnNumber())('tenant-a', 'INV', 'x'))->toThrow(NotFound::class);
});

it('starts a series where the merchant says and pads how the merchant says', function (): void {
    (new OpenSeries())('tenant-a', 'CN', 'CN/2026/', 4, true, true, 250);
    $series = Series::query()->firstOrFail();

    [$value, $number] = Numbering::spend($series);

    expect($value)->toBe(250)
        ->and($number)->toBe('CN/2026/0250')
        ->and($series->next_value)->toBe(251);
});
