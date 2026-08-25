<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Queries;

use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;

/** By reference, never by primary key: the host showed customers the key and enumerated the deployment. */
final class FindDocument
{
    public function __invoke(string $tenantId, string $reference): ?Document
    {
        return Document::query()->where('tenant_id', $tenantId)->where('reference', $reference)->first();
    }
}
