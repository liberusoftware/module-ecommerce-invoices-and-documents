<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\MoneyMismatch;

/**
 * Minor units, an ISO 4217 code and the exponent that relates them. The host
 * printed a dollar sign over a decimal column with no currency anywhere on it.
 */
final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency,
        public int $exponent = 2,
    ) {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw MoneyMismatch::malformedCurrency($currency);
        }

        if ($exponent < 0 || $exponent > 4) {
            throw MoneyMismatch::malformedExponent($exponent);
        }
    }

    public static function zero(string $currency, int $exponent = 2): self
    {
        return new self(0, $currency, $exponent);
    }

    public function plus(self $other): self
    {
        $this->assertComparable($other);

        return new self($this->minor + $other->minor, $this->currency, $this->exponent);
    }

    public function equals(self $other): bool
    {
        $this->assertComparable($other);

        return $this->minor === $other->minor;
    }

    public function exceeds(self $other): bool
    {
        $this->assertComparable($other);

        return $this->minor > $other->minor;
    }

    /** Decimal string, built by integer division so no float ever holds the amount. */
    public function decimal(): string
    {
        $scale = 10 ** $this->exponent;
        $sign = $this->minor < 0 ? '-' : '';
        $magnitude = abs($this->minor);

        if ($this->exponent === 0) {
            return $sign.(string) $magnitude;
        }

        return $sign.intdiv($magnitude, $scale).'.'.str_pad((string) ($magnitude % $scale), $this->exponent, '0', STR_PAD_LEFT);
    }

    private function assertComparable(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw MoneyMismatch::currency($this->currency, $other->currency);
        }

        if ($this->exponent !== $other->exponent) {
            throw MoneyMismatch::exponent($this->currency, $this->exponent, $other->exponent);
        }
    }
}
