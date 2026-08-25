<?php

declare(strict_types=1);

use Liberu\Ecommerce\InvoicesAndDocuments\Data\Outcome;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\Recording;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\RefusalReason;

it('says which of the three things happened', function (): void {
    $recorded = Outcome::recorded(7, 'ref');
    $already = Outcome::alreadyRecorded(7, 'ref');
    $refused = Outcome::refused(RefusalReason::SeriesRequired);

    expect($recorded->happened())->toBeTrue()
        ->and($recorded->wasRefused())->toBeFalse()
        ->and($recorded->id)->toBe(7)
        ->and($recorded->reference)->toBe('ref')
        ->and($already->happened())->toBeFalse()
        ->and($already->recording)->toBe(Recording::AlreadyRecorded)
        ->and($refused->happened())->toBeFalse()
        ->and($refused->wasRefused())->toBeTrue()
        ->and($refused->reason)->toBe(RefusalReason::SeriesRequired)
        ->and($refused->id)->toBeNull();
});
