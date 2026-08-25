<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DeliveryState;

/** A transport's answer, which is a fact the module records and never assumes. */
final readonly class TransportOutcome
{
    private function __construct(
        public DeliveryState $state,
        public ?string $detail = null,
    ) {}

    public static function sent(?string $detail = null): self
    {
        return new self(DeliveryState::Sent, $detail);
    }

    public static function failed(string $detail): self
    {
        return new self(DeliveryState::Failed, $detail);
    }

    public static function suppressed(string $detail): self
    {
        return new self(DeliveryState::Suppressed, $detail);
    }
}
