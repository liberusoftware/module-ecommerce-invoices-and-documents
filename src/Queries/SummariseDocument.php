<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Queries;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\DocumentSummary;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\NotFound;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Policies\CustodyPolicy;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Frozen;

/**
 * Totals, summed from the frozen lines each time they are asked for. No column
 * holds them, so nothing can drift away from what the document says.
 */
final class SummariseDocument
{
    public function __invoke(string $tenantId, Document $document): DocumentSummary
    {
        if (! CustodyPolicy::ownsDocument($document, $tenantId)) {
            throw NotFound::document();
        }

        return Frozen::summarise(Frozen::linesOf($document), $document->currency, $document->currency_exponent);
    }
}
