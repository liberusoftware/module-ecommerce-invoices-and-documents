<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

/**
 * A name, an address and a tax registration, as they were at issue. `reference`
 * is opaque: this module never resolves it against anybody's table.
 */
final readonly class Party
{
    public function __construct(
        public string $reference,
        public string $name,
        public string $address,
        public ?string $taxId = null,
        public ?string $email = null,
    ) {}
}
