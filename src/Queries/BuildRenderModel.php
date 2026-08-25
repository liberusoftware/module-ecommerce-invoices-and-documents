<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Queries;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\Party;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\RenderModel;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Policies\CustodyPolicy;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Frozen;

/**
 * Everything a renderer needs, from this module's own rows only. A renderer
 * that had to fetch a product name would be the host's invoice again.
 */
final class BuildRenderModel
{
    public function __invoke(string $tenantId, Document $document): RenderModel
    {
        if (! CustodyPolicy::ownsDocument($document, $tenantId)) {
            throw NotFound::document();
        }

        $lines = Frozen::linesOf($document);
        $corrected = $document->corrects()->first();

        return new RenderModel(
            $document->tenant_id,
            $document->reference,
            $document->kind,
            $document->state,
            $document->number,
            $document->issued_at,
            new Party($document->seller_ref, $document->seller_name, $document->seller_address, $document->seller_tax_id),
            new Party($document->buyer_ref, $document->buyer_name, $document->buyer_address, $document->buyer_tax_id, $document->buyer_email),
            $lines,
            Frozen::summarise($lines, $document->currency, $document->currency_exponent),
            $document->note,
            $corrected?->number,
            $corrected?->reference,
        );
    }
}
