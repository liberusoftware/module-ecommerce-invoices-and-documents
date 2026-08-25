<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Actions;

use Illuminate\Database\QueryException;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Outcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Series;

/** A series per tenant, not per store: the filing obligation is the merchant's. */
final class OpenSeries
{
    public function __invoke(
        string $tenantId,
        string $code,
        string $prefix = '',
        int $pad = 0,
        bool $fiscal = true,
        bool $gapless = true,
        int $startAt = 1,
    ): Outcome {
        try {
            $series = Series::query()->create([
                'tenant_id' => $tenantId,
                'code' => $code,
                'prefix' => $prefix,
                'pad' => $pad,
                'next_value' => $startAt,
                'fiscal' => $fiscal,
                'gapless' => $gapless,
            ]);
        } catch (QueryException $exception) {
            $existing = Series::query()->where('tenant_id', $tenantId)->where('code', $code)->first();

            if (! $existing instanceof Series) {
                throw $exception;
            }

            return Outcome::alreadyRecorded($existing->id, $existing->code);
        }

        return Outcome::recorded($series->id, $series->code);
    }
}
