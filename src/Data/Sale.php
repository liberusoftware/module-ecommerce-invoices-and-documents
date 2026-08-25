<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

/**
 * Everything a document copies at issue. After this the module reads nothing
 * from the sale again — that is the whole point of the boundary.
 */
final readonly class Sale
{
    /** @param  list<Line>  $lines */
    public function __construct(
        public string $reference,
        public Party $seller,
        public Party $buyer,
        public array $lines,
        public Money $statedNet,
        public Money $statedTax,
        public Money $statedGross,
    ) {}
}
