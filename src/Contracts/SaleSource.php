<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Contracts;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\Sale;

/**
 * The one moment this module reads a sale. Return null when the sale is not
 * yours to describe; the module refuses to draft rather than inventing lines.
 */
interface SaleSource
{
    public function sale(string $tenantId, string $saleReference): ?Sale;
}
