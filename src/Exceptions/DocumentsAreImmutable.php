<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Exceptions;

final class DocumentsAreImmutable extends InvoicesAndDocumentsException
{
    /** @param  list<string>  $attributes */
    public static function forDocument(string $reference, array $attributes): self
    {
        return new self(
            "Document {$reference} is issued; ".implode(', ', $attributes).' cannot change. Issue a credit note instead.'
        );
    }

    public static function forLine(int $documentId): self
    {
        return new self("Document {$documentId}'s lines were frozen at issue and cannot be changed or removed.");
    }

    public static function forEvent(int $documentId): self
    {
        return new self("Document {$documentId}'s history is append-only.");
    }

    public static function forDeletion(string $reference): self
    {
        return new self("Document {$reference} cannot be deleted. Void it, which records rather than erases.");
    }
}
