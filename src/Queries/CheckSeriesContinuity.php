<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Queries;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\ContinuityReport;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Series;

/**
 * Every number a series has spent, on a document or on the record of one it
 * could not use. A gapless series with anything in `missing` is the alarm in
 * the runbook that cannot wait, because nothing in the domain can fill it in
 * afterwards.
 */
final class CheckSeriesContinuity
{
    public function __invoke(string $tenantId, string $code): ContinuityReport
    {
        $series = Series::query()->where('tenant_id', $tenantId)->where('code', $code)->first();

        if (! $series instanceof Series) {
            throw NotFound::series($code);
        }

        $issued = $this->sequences($series->documents()->whereNotNull('number_sequence')->pluck('number_sequence')->all());
        $burned = $this->sequences($series->burnedNumbers()->pluck('number_sequence')->all());

        $spent = array_values(array_unique(array_merge($issued, $burned)));
        sort($spent);

        $first = $spent === [] ? null : $spent[0];
        $last = $spent === [] ? null : $spent[count($spent) - 1];
        $missing = [];

        if ($first !== null && $last !== null) {
            $present = array_flip($spent);

            for ($value = $first; $value <= $last; $value++) {
                if (! array_key_exists($value, $present)) {
                    $missing[] = $value;
                }
            }
        }

        return new ContinuityReport($tenantId, $code, $series->gapless, count($issued), count($burned), $first, $last, $missing);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<int>
     */
    private function sequences(array $values): array
    {
        $sequences = [];

        foreach ($values as $value) {
            if (is_int($value)) {
                $sequences[] = $value;
            }
        }

        return $sequences;
    }
}
