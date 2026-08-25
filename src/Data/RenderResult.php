<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;

/**
 * The render model is always here; the bytes are only here when a renderer was
 * bound and produced some. Unbound removes exactly the artefact and nothing
 * else — the module still knows what the document says.
 */
final readonly class RenderResult
{
    private function __construct(
        public RenderModel $model,
        public ?Rendered $rendered = null,
        public ?RefusalReason $unavailable = null,
    ) {}

    public static function rendered(RenderModel $model, Rendered $rendered): self
    {
        return new self($model, $rendered);
    }

    public static function unavailable(RenderModel $model, RefusalReason $reason): self
    {
        return new self($model, null, $reason);
    }

    public function isRendered(): bool
    {
        return $this->rendered instanceof Rendered;
    }
}
