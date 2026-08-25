<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Queries;

use Illuminate\Database\Eloquent\Collection;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;

final class ListDocuments
{
    /** @return Collection<int, Document> */
    public function __invoke(
        string $tenantId,
        ?DocumentKind $kind = null,
        ?DocumentState $state = null,
        ?string $buyerRef = null,
    ): Collection {
        return Document::query()
            ->where('tenant_id', $tenantId)
            ->when($kind instanceof DocumentKind, fn ($query) => $query->where('kind', $kind?->value))
            ->when($state instanceof DocumentState, fn ($query) => $query->where('state', $state?->value))
            ->when($buyerRef !== null, fn ($query) => $query->where('buyer_ref', $buyerRef))
            ->orderByDesc('id')
            ->get();
    }
}
