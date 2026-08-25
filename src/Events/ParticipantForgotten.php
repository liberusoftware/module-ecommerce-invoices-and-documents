<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Events;

/** Dispatched once per tenant touched, because an event without a tenant is unactionable. */
final readonly class ParticipantForgotten
{
    public function __construct(
        public string $tenantId,
        public string $subjectReference,
        public int $redactedDocuments,
        public int $refusedDocuments,
    ) {}
}
