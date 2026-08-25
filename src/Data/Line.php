<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

/**
 * One line, as the sale stated it and as the document froze it. Net, tax and
 * gross all arrive computed: the tax module divides and rounds, this module
 * only ever adds.
 *
 * `quantityMilli` is a quantity in thousandths, so 2.5kg is 2500 and no float
 * ever touches the document.
 */
final readonly class Line
{
    public function __construct(
        public string $description,
        public int $quantityMilli,
        public Money $unitNet,
        public Money $net,
        public int $taxRateBasisPoints,
        public Money $tax,
        public Money $gross,
    ) {}

    /** Thousandths as a decimal string, trailing zeroes trimmed, no float involved. */
    public function quantity(): string
    {
        $sign = $this->quantityMilli < 0 ? '-' : '';
        $magnitude = abs($this->quantityMilli);
        $fraction = rtrim(str_pad((string) ($magnitude % 1000), 3, '0', STR_PAD_LEFT), '0');

        return $sign.intdiv($magnitude, 1000).($fraction === '' ? '' : '.'.$fraction);
    }
}
