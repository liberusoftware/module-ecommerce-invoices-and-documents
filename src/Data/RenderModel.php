<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;

/**
 * Everything a renderer needs, resolved from the document's own rows. A
 * renderer that reached back for a product or a customer would reintroduce the
 * fault this module exists to remove.
 */
final readonly class RenderModel
{
    /** @param  list<Line>  $lines */
    public function __construct(
        public string $tenantId,
        public string $reference,
        public DocumentKind $kind,
        public DocumentState $state,
        public ?string $number,
        public ?Carbon $issuedAt,
        public Party $seller,
        public Party $buyer,
        public array $lines,
        public DocumentSummary $summary,
        public ?string $note,
        public ?string $correctsNumber,
        public ?string $correctsReference,
    ) {}
}
