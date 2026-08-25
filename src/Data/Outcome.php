<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

use Liberu\Ecommerce\InvoicesAndDocuments\Enums\Recording;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;

/**
 * What every mutation here returns. A caller that cannot tell a refusal from a
 * success will report one as the other, which is how the host came to say an
 * invoice existed on a payment that never wrote one.
 */
final readonly class Outcome
{
    private function __construct(
        public Recording $recording,
        public ?RefusalReason $reason = null,
        public ?int $id = null,
        public ?string $reference = null,
    ) {}

    public static function recorded(?int $id = null, ?string $reference = null): self
    {
        return new self(Recording::Recorded, null, $id, $reference);
    }

    public static function alreadyRecorded(?int $id = null, ?string $reference = null): self
    {
        return new self(Recording::AlreadyRecorded, null, $id, $reference);
    }

    public static function refused(RefusalReason $reason): self
    {
        return new self(Recording::Refused, $reason);
    }

    public function happened(): bool
    {
        return $this->recording === Recording::Recorded;
    }

    public function wasRefused(): bool
    {
        return $this->recording === Recording::Refused;
    }
}
