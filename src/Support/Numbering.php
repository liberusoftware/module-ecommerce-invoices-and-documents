<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Support;

use Liberu\Ecommerce\InvoicesAndDocuments\Models\Series;

/**
 * A number is spent under a row lock and only inside the caller's transaction,
 * so a rollback returns it. `commerce-core` spends on allocation instead and
 * says so: a gap is fine for an order number and is not fine for a fiscal one.
 */
final class Numbering
{
    /** @return array{int, string} the value spent and how it is spelled. */
    public static function spend(Series $series): array
    {
        $locked = Series::query()->whereKey($series->getKey())->lockForUpdate()->firstOrFail();
        $value = $locked->next_value;

        $locked->forceFill(['next_value' => $value + 1])->save();
        $series->setAttribute('next_value', $value + 1);

        return [$value, $locked->format($value)];
    }
}
