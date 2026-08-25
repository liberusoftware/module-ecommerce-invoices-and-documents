<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

use Illuminate\Support\Carbon;

/**
 * One document erasure would not touch. A null `retainUntil` is not "no
 * retention": it is a host that never said, which the module refuses to guess.
 */
final readonly class RetentionRefusal
{
    public function __construct(
        public string $tenantId,
        public string $reference,
        public ?string $number,
        public ?Carbon $retainUntil,
    ) {}

    public function windowIsUnknown(): bool
    {
        return ! $this->retainUntil instanceof Carbon;
    }
}
