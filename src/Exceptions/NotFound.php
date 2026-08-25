<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Exceptions;

final class NotFound extends InvoicesAndDocumentsException
{
    public static function document(): self
    {
        return new self('No such document for this tenant.');
    }

    public static function series(string $code): self
    {
        return new self("No numbering series [{$code}] for this tenant.");
    }
}
