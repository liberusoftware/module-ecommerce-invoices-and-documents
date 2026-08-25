<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

/**
 * What a series has actually spent. A gapless series with a non-empty `missing`
 * is the one alarm in the runbook that cannot wait.
 */
final readonly class ContinuityReport
{
    /** @param  list<int>  $missing */
    public function __construct(
        public string $tenantId,
        public string $code,
        public bool $gapless,
        public int $issued,
        public int $burned,
        public ?int $first,
        public ?int $last,
        public array $missing,
    ) {}

    public function isContinuous(): bool
    {
        return $this->missing === [];
    }
}
