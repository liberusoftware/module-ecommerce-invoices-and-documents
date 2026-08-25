<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Events;

final readonly class DocumentDelivered
{
    public function __construct(
        public string $tenantId,
        public int $documentId,
        public string $reference,
        public string $channel,
        public string $deliveryReference,
    ) {}
}
