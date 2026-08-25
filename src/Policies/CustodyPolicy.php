<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Policies;

use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;

/**
 * Standing, in one place, and every check takes the tenant. The host dropped
 * the ownership filter for `hasRole(['super_admin','admin'])`, which is a name
 * and not a merchant: one merchant's admin read every merchant's invoices.
 */
final class CustodyPolicy
{
    public static function ownsDocument(Document $document, string $tenantId): bool
    {
        return $document->tenant_id === $tenantId;
    }

    /** The buyer's own reference proves the customer side; a role name never does. */
    public static function buyerMayRead(Document $document, string $tenantId, string $buyerRef): bool
    {
        return self::ownsDocument($document, $tenantId)
            && ! $document->isRedacted()
            && $document->buyer_ref === $buyerRef;
    }
}
