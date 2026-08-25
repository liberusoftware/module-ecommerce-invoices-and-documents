<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Queries;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\RenderResult;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Seams;

/**
 * With nothing bound the module still knows exactly what the document says; it
 * just has no file. That is the whole blast radius of an unbound renderer, and
 * "declined" is a different answer from "nobody was asked".
 */
final class RenderDocument
{
    public function __invoke(string $tenantId, Document $document): RenderResult
    {
        $model = (new BuildRenderModel())($tenantId, $document);
        $renderer = Seams::renderer();

        if ($renderer === null) {
            return RenderResult::unavailable($model, RefusalReason::NoRendererBound);
        }

        $rendered = $renderer->render($model);

        return $rendered === null
            ? RenderResult::unavailable($model, RefusalReason::RendererDeclined)
            : RenderResult::rendered($model, $rendered);
    }
}
