<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Contracts;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\Rendered;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\RenderModel;

/** Null means the renderer declined this document, which is not the same as no renderer. */
interface DocumentRenderer
{
    public function render(RenderModel $model): ?Rendered;
}
