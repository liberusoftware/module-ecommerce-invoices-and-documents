<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;

final readonly class ExportedDocument
{
    public function __construct(
        public string $tenantId,
        public string $reference,
        public DocumentKind $kind,
        public DocumentState $state,
        public ?string $number,
        public ?Carbon $issuedAt,
        public string $buyerName,
        public Money $gross,
        public ?Carbon $retainUntil,
        public bool $redacted,
    ) {}
}
