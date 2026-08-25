<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Events;

use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DeliveryState;

final readonly class DeliveryFailed
{
    public function __construct(
        public string $tenantId,
        public int $documentId,
        public string $reference,
        public string $channel,
        public DeliveryState $state,
        public ?string $detail,
    ) {}
}
