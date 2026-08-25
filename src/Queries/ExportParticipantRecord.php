<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Queries;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\ExportedDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\ParticipantRecord;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Frozen;

/**
 * Every document issued to one person, across every tenant, because a person is
 * not a tenant's property. The host's subject-access request returned orders and
 * never the documents issued against them.
 */
final class ExportParticipantRecord
{
    public function __invoke(string $subjectReference): ParticipantRecord
    {
        $documents = [];

        foreach (Document::query()->where('buyer_ref', $subjectReference)->orderBy('id')->get() as $document) {
            $documents[] = new ExportedDocument(
                $document->tenant_id,
                $document->reference,
                $document->kind,
                $document->state,
                $document->number,
                $document->issued_at,
                $document->buyer_name,
                Frozen::summarise(Frozen::linesOf($document), $document->currency, $document->currency_exponent)->gross,
                $document->retain_until,
                $document->isRedacted(),
            );
        }

        return new ParticipantRecord($subjectReference, $documents);
    }
}
