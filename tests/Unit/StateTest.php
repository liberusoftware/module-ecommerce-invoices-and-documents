<?php

declare(strict_types=1);

use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;

it('allows exactly the moves the document has', function (): void {
    expect(DocumentState::Draft->allowed())->toBe([DocumentState::Issued, DocumentState::Void])
        ->and(DocumentState::Issued->allowed())->toBe([DocumentState::Delivered, DocumentState::Void])
        ->and(DocumentState::Delivered->allowed())->toBe([DocumentState::Void])
        ->and(DocumentState::Void->allowed())->toBe([]);
});

it('refuses to reopen or reissue anything', function (): void {
    expect(DocumentState::Void->canTransitionTo(DocumentState::Issued))->toBeFalse()
        ->and(DocumentState::Void->canTransitionTo(DocumentState::Void))->toBeFalse()
        ->and(DocumentState::Delivered->canTransitionTo(DocumentState::Issued))->toBeFalse()
        ->and(DocumentState::Draft->canTransitionTo(DocumentState::Delivered))->toBeFalse()
        ->and(DocumentState::Draft->canTransitionTo(DocumentState::Issued))->toBeTrue();
});

it('counts issued and delivered as existing in the world', function (): void {
    expect(DocumentState::Issued->isIssued())->toBeTrue()
        ->and(DocumentState::Delivered->isIssued())->toBeTrue()
        ->and(DocumentState::Draft->isIssued())->toBeFalse()
        ->and(DocumentState::Void->isIssued())->toBeFalse();
});

it('files every kind but a proforma under a series', function (): void {
    expect(DocumentKind::Invoice->isFiscal())->toBeTrue()
        ->and(DocumentKind::Receipt->isFiscal())->toBeTrue()
        ->and(DocumentKind::CreditNote->isFiscal())->toBeTrue()
        ->and(DocumentKind::Proforma->isFiscal())->toBeFalse()
        ->and(DocumentKind::CreditNote->correctsAnother())->toBeTrue()
        ->and(DocumentKind::Invoice->correctsAnother())->toBeFalse();
});
