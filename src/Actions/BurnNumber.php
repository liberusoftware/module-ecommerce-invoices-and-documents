<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Outcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\NumberBurned;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\BurnedNumber;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Series;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Numbering;

/**
 * Spend a number on nothing, on the record. A gapless series refuses, because
 * a hole is exactly what it promises not to have; any other series records the
 * hole rather than leaving somebody to discover it during an audit.
 */
final class BurnNumber
{
    public function __invoke(string $tenantId, string $seriesCode, string $reason): Outcome
    {
        $series = Series::query()->where('tenant_id', $tenantId)->where('code', $seriesCode)->first();

        if (! $series instanceof Series) {
            throw NotFound::series($seriesCode);
        }

        if ($series->gapless) {
            return Outcome::refused(RefusalReason::SeriesIsGapless);
        }

        $burned = DB::transaction(function () use ($tenantId, $series, $reason): BurnedNumber {
            [$sequence, $number] = Numbering::spend($series);

            return BurnedNumber::query()->create([
                'tenant_id' => $tenantId,
                'series_id' => $series->id,
                'number' => $number,
                'number_sequence' => $sequence,
                'reason' => $reason,
                'burned_at' => Carbon::now(),
            ]);
        });

        Event::dispatch(new NumberBurned($tenantId, $series->id, $series->code, $burned->number, $reason));

        return Outcome::recorded($burned->id, $burned->number);
    }
}
