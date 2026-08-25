<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Events;

final readonly class NumberBurned
{
    public function __construct(
        public string $tenantId,
        public int $seriesId,
        public string $code,
        public string $number,
        public string $reason,
    ) {}
}
