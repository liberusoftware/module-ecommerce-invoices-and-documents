<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Data;

/**
 * Every document issued to one person, across every tenant. Erasure walks the
 * same set, so the two agree about what "everything" means.
 */
final readonly class ParticipantRecord
{
    /** @param  list<ExportedDocument>  $documents */
    public function __construct(
        public string $subjectReference,
        public array $documents,
    ) {}

    public function isEmpty(): bool
    {
        return $this->documents === [];
    }
}
