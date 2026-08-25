<?php

declare(strict_types=1);

use Liberu\Ecommerce\InvoicesAndDocuments\Data\Line;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Support\Frozen;

function unit(int $quantityMilli): Line
{
    return new Line('x', $quantityMilli, new Money(1, 'GBP'), new Money(1, 'GBP'), 0, new Money(0, 'GBP'), new Money(1, 'GBP'));
}

it('spells a quantity from thousandths without a float', function (int $milli, string $expected): void {
    expect(unit($milli)->quantity())->toBe($expected);
})->with([
    [1000, '1'],
    [2500, '2.5'],
    [1, '0.001'],
    [1230, '1.23'],
    [-1500, '-1.5'],
    [0, '0'],
]);

it('reports no shared currency for an empty set of lines', function (): void {
    expect(Frozen::currencyOf([]))->toBeNull();
});

it('reports no shared currency when one line disagrees', function (): void {
    $mixed = [
        new Line('a', 1000, new Money(1, 'GBP'), new Money(1, 'GBP'), 0, new Money(0, 'GBP'), new Money(1, 'GBP')),
        new Line('b', 1000, new Money(1, 'GBP'), new Money(1, 'EUR'), 0, new Money(0, 'GBP'), new Money(1, 'GBP')),
    ];

    expect(Frozen::currencyOf($mixed))->toBeNull();
});

it('reports no shared currency when one line uses another exponent', function (): void {
    $mixed = [
        new Line('a', 1000, new Money(1, 'GBP'), new Money(1, 'GBP'), 0, new Money(0, 'GBP'), new Money(1, 'GBP')),
        new Line('b', 1000, new Money(1, 'GBP', 3), new Money(1, 'GBP'), 0, new Money(0, 'GBP'), new Money(1, 'GBP')),
    ];

    expect(Frozen::currencyOf($mixed))->toBeNull();
});

it('summarises per rate in ascending rate order', function (): void {
    $lines = [
        new Line('standard', 1000, new Money(1000, 'GBP'), new Money(1000, 'GBP'), 2000, new Money(200, 'GBP'), new Money(1200, 'GBP')),
        new Line('zero', 1000, new Money(500, 'GBP'), new Money(500, 'GBP'), 0, new Money(0, 'GBP'), new Money(500, 'GBP')),
        new Line('standard again', 1000, new Money(100, 'GBP'), new Money(100, 'GBP'), 2000, new Money(20, 'GBP'), new Money(120, 'GBP')),
    ];

    $summary = Frozen::summarise($lines, 'GBP', 2);

    expect($summary->net->minor)->toBe(1600)
        ->and($summary->tax->minor)->toBe(220)
        ->and($summary->gross->minor)->toBe(1820)
        ->and($summary->byRate)->toHaveCount(2)
        ->and($summary->byRate[0]->rateBasisPoints)->toBe(0)
        ->and($summary->byRate[0]->gross->minor)->toBe(500)
        ->and($summary->byRate[1]->rateBasisPoints)->toBe(2000)
        ->and($summary->byRate[1]->net->minor)->toBe(1100)
        ->and($summary->byRate[1]->tax->minor)->toBe(220)
        ->and($summary->byRate[1]->gross->minor)->toBe(1320);
});
