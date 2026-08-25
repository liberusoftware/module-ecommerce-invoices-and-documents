<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

/**
 * Erasure's answer, where retention and a subject's request disagree. The
 * conflict is returned rather than resolved silently in either direction.
 */
final readonly class ForgetReport
{
    /**
     * @param  list<string>  $redactedDocuments  references whose buyer identity was removed
     * @param  list<RetentionRefusal>  $refusedDocuments
     */
    public function __construct(
        public string $subjectReference,
        public array $redactedDocuments,
        public array $refusedDocuments,
        public int $redactedContacts,
        public int $redactedDeliveries,
    ) {}

    public function wasComplete(): bool
    {
        return $this->refusedDocuments === [];
    }
}
