<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Exceptions;

final class MoneyMismatch extends InvoicesAndDocumentsException
{
    public static function currency(string $left, string $right): self
    {
        return new self("Cannot combine {$left} with {$right}: a document carries one currency.");
    }

    public static function exponent(string $currency, int $left, int $right): self
    {
        return new self("Two {$currency} amounts arrived with exponents {$left} and {$right}.");
    }

    public static function malformedCurrency(string $currency): self
    {
        return new self("[{$currency}] is not an ISO 4217 alphabetic code.");
    }

    public static function malformedExponent(int $exponent): self
    {
        return new self("A currency exponent of {$exponent} is outside 0-4.");
    }
}
