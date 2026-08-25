<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

/** One row of the per-rate block a VAT invoice has to carry. */
final readonly class TaxRateTotal
{
    public function __construct(
        public int $rateBasisPoints,
        public Money $net,
        public Money $tax,
        public Money $gross,
    ) {}
}
