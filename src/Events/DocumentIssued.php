<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Events;

use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;

final readonly class DocumentIssued
{
    public function __construct(
        public string $tenantId,
        public int $documentId,
        public string $reference,
        public DocumentKind $kind,
        public ?string $number,
        public string $sourceRef,
        public int $grossMinor,
        public string $currency,
    ) {}
}
