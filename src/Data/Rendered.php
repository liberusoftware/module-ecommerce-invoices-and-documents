<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

/** What a bound renderer handed back. The module never inspects the bytes. */
final readonly class Rendered
{
    public function __construct(
        public string $mediaType,
        public string $filename,
        public string $contents,
    ) {}
}
