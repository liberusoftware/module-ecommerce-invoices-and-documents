<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\DraftDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\ForgetParticipant;
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\RecordDelivery;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Events\ParticipantForgotten;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DeliveryAttempt;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\ExportParticipantRecord;
use Liberu\Ecommerce\InvoicesAndDocuments\Queries\SummariseDocument;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Redaction;

it('refuses to redact a document still inside its retention window, and names it', function (): void {
    Config::set('invoices-and-documents.retention.years', 6);
    $document = issued(buyerRef: 'person-1');

    $report = (new ForgetParticipant())('person-1');
    $document->refresh();

    expect($report->wasComplete())->toBeFalse()
        ->and($report->redactedDocuments)->toBe([])
        ->and($report->refusedDocuments)->toHaveCount(1)
        ->and($report->refusedDocuments[0]->reference)->toBe($document->reference)
        ->and($report->refusedDocuments[0]->number)->toBe('INV-00001')
        ->and($report->refusedDocuments[0]->retainUntil?->year)->toBe(Carbon::now()->addYears(6)->year)
        ->and($report->refusedDocuments[0]->windowIsUnknown())->toBeFalse()
        ->and($document->buyer_name)->toBe('A Buyer')
        ->and($document->buyer_ref)->toBe('person-1')
        ->and($document->isRedacted())->toBeFalse();
});

it('treats a retention window the host never configured as unknown rather than expired', function (): void {
    $document = issued(buyerRef: 'person-1');

    $report = (new ForgetParticipant())('person-1');

    expect($report->wasComplete())->toBeFalse()
        ->and($report->refusedDocuments[0]->retainUntil)->toBeNull()
        ->and($report->refusedDocuments[0]->windowIsUnknown())->toBeTrue()
        ->and($document->fresh()?->buyer_name)->toBe('A Buyer');
});

it('redacts contact details and notes even on a document it may not otherwise touch', function (): void {
    Config::set('invoices-and-documents.retention.years', 6);
    bindTransport();
    $document = issued(buyerRef: 'person-1');
    $document->forceFill(['note' => 'Ring the buyer on 07700 900000'])->save();
    (new RecordDelivery())('tenant-a', $document->fresh() ?? $document, 'send-1', 'email');

    $report = (new ForgetParticipant())('person-1');
    $document->refresh();

    expect($report->redactedContacts)->toBe(1)
        ->and($report->redactedDeliveries)->toBe(1)
        ->and($document->buyer_email)->toBeNull()
        ->and($document->note)->toBeNull()
        ->and(DeliveryAttempt::query()->firstOrFail()->address)->toBeNull()
        ->and(DeliveryAttempt::query()->firstOrFail()->redacted_at)->not->toBeNull()
        ->and($document->buyer_name)->toBe('A Buyer');
});

it('redacts the identity once the retention window has passed, and keeps the arithmetic', function (): void {
    Event::fake();
    Config::set('invoices-and-documents.retention.years', 6);
    $document = issued(buyerRef: 'person-1');
    $before = (new SummariseDocument())('tenant-a', $document);

    Carbon::setTestNow(Carbon::now()->addYears(7));
    $report = (new ForgetParticipant())('person-1');
    $document->refresh();
    Carbon::setTestNow();

    expect($report->wasComplete())->toBeTrue()
        ->and($report->redactedDocuments)->toBe([$document->reference])
        ->and($document->buyer_name)->toBe('redacted')
        ->and($document->buyer_address)->toBe('redacted')
        ->and($document->buyer_tax_id)->toBeNull()
        ->and($document->buyer_ref)->toBe('redacted:'.$document->reference)
        ->and($document->isRedacted())->toBeTrue()
        ->and($document->number)->toBe('INV-00001')
        ->and($document->events()->where('kind', EventKind::Redacted->value)->count())->toBe(1);

    $after = (new SummariseDocument())('tenant-a', $document);

    expect($after->gross->minor)->toBe($before->gross->minor)
        ->and($after->net->minor)->toBe($before->net->minor)
        ->and($after->tax->minor)->toBe($before->tax->minor);

    Event::assertDispatched(ParticipantForgotten::class, fn (ParticipantForgotten $e): bool => $e->tenantId === 'tenant-a' && $e->redactedDocuments === 1 && $e->refusedDocuments === 0);
});

it('keeps two redacted buyers distinct rather than collapsing them onto one token', function (): void {
    $one = draft(saleRef: 'order-1', buyerRef: 'person-1');
    $two = draft(saleRef: 'order-2', buyerRef: 'person-1');

    (new ForgetParticipant())('person-1');

    expect($one->fresh()?->buyer_ref)->not->toBe($two->fresh()?->buyer_ref)
        ->and($one->fresh()?->buyer_ref)->toBe('redacted:'.$one->reference);
});

it('redacts a document that never issued, because nothing requires it be kept', function (): void {
    $document = draft(buyerRef: 'person-1');

    $report = (new ForgetParticipant())('person-1');

    expect($report->wasComplete())->toBeTrue()
        ->and($document->fresh()?->buyer_name)->toBe('redacted');
});

it('walks every tenant, because a person is not one merchant property', function (): void {
    Event::fake();
    draft('tenant-a', 'order-1', buyerRef: 'person-1');
    draft('tenant-b', 'order-1', buyerRef: 'person-1');

    $report = (new ForgetParticipant())('person-1');

    expect($report->redactedDocuments)->toHaveCount(2);

    Event::assertDispatchedTimes(ParticipantForgotten::class, 2);
});

it('exports exactly the set erasure walks, before and after', function (): void {
    Config::set('invoices-and-documents.retention.years', 6);
    $a = issued('tenant-a', 'order-1', buyerRef: 'person-1');
    $b = draft('tenant-b', 'order-1', buyerRef: 'person-1');
    draft('tenant-a', 'order-2', buyerRef: 'person-2');

    $record = (new ExportParticipantRecord())('person-1');

    expect($record->subjectReference)->toBe('person-1')
        ->and($record->isEmpty())->toBeFalse()
        ->and($record->documents)->toHaveCount(2)
        ->and(array_map(fn ($d): string => $d->reference, $record->documents))
        ->toBe([$a->reference, $b->reference])
        ->and($record->documents[0]->kind)->toBe(DocumentKind::Invoice)
        ->and($record->documents[0]->number)->toBe('INV-00001')
        ->and($record->documents[0]->gross->minor)->toBe(1200)
        ->and($record->documents[0]->buyerName)->toBe('A Buyer')
        ->and($record->documents[0]->redacted)->toBeFalse()
        ->and($record->documents[0]->retainUntil)->not->toBeNull()
        ->and($record->documents[1]->issuedAt)->toBeNull();

    $report = (new ForgetParticipant())('person-1');

    expect(count($report->redactedDocuments) + count($report->refusedDocuments))
        ->toBe(count($record->documents));

    // What erasure could remove is gone from the export; what retention kept is still there.
    $after = (new ExportParticipantRecord())('person-1');

    expect($after->documents)->toHaveCount(1)
        ->and($after->documents[0]->reference)->toBe($a->reference)
        ->and((new ExportParticipantRecord())('person-3')->isEmpty())->toBeTrue();
});

it('finds nothing to forget for somebody who bought nothing', function (): void {
    Event::fake();

    $report = (new ForgetParticipant())('nobody');

    expect($report->wasComplete())->toBeTrue()
        ->and($report->redactedDocuments)->toBe([])
        ->and($report->redactedContacts)->toBe(0);

    Event::assertNotDispatched(ParticipantForgotten::class);
});

it('falls back to a usable token when the host configures none', function (): void {
    Config::set('invoices-and-documents.redaction_token', null);

    expect(Redaction::token())->toBe('redacted');

    Config::set('invoices-and-documents.redaction_token', 'gone');

    expect(Redaction::token())->toBe('gone')
        ->and(Redaction::subject('abc'))->toBe('gone:abc');
});

it('leaves a redacted document out of a second erasure', function (): void {
    draft(buyerRef: 'person-1');
    (new ForgetParticipant())('person-1');

    $second = (new ForgetParticipant())('person-1');

    expect($second->redactedDocuments)->toBe([])
        ->and($second->redactedContacts)->toBe(0);
});

it('does not redact a buyer who merely shares a sale reference with another', function (): void {
    bindSale(tenantId: 'tenant-a', saleRef: 'order-1', buyerRef: 'person-1');
    (new DraftDocument())('tenant-a', DocumentKind::Invoice, 'order-1');
    bindSale(tenantId: 'tenant-b', saleRef: 'order-1', buyerRef: 'person-2');
    (new DraftDocument())('tenant-b', DocumentKind::Invoice, 'order-1');

    (new ForgetParticipant())('person-1');

    expect(Document::query()->where('buyer_ref', 'person-2')->count())->toBe(1);
});
