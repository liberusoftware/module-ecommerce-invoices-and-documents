<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

/**
 * Totals, summed from the frozen lines rather than stored beside them. The
 * host stored a header total its lines could stop matching.
 */
final readonly class DocumentSummary
{
    /** @param  list<TaxRateTotal>  $byRate */
    public function __construct(
        public Money $net,
        public Money $tax,
        public Money $gross,
        public array $byRate,
    ) {}
}
