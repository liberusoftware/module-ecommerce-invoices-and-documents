<?php

declare(strict_types=1);

use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\MoneyMismatch;

it('adds and compares amounts of the same currency', function (): void {
    $a = new Money(1050, 'GBP');
    $b = new Money(250, 'GBP');

    expect($a->plus($b)->minor)->toBe(1300)
        ->and($a->equals(new Money(1050, 'GBP')))->toBeTrue()
        ->and($a->exceeds($b))->toBeTrue()
        ->and($b->exceeds($a))->toBeFalse()
        ->and(Money::zero('GBP')->minor)->toBe(0);
});

it('refuses to combine two currencies', function (): void {
    expect(fn () => (new Money(100, 'GBP'))->plus(new Money(100, 'EUR')))
        ->toThrow(MoneyMismatch::class, 'Cannot combine GBP with EUR');
});

it('refuses two exponents for one currency', function (): void {
    expect(fn () => (new Money(100, 'JPY', 0))->equals(new Money(100, 'JPY', 2)))
        ->toThrow(MoneyMismatch::class, 'exponents 0 and 2');
});

it('rejects anything that is not an ISO 4217 alphabetic code', function (string $code): void {
    expect(fn () => new Money(1, $code))->toThrow(MoneyMismatch::class, 'not an ISO 4217');
})->with(['gbp', 'GB', 'GBPP', '', '840']);

it('rejects an exponent no currency has', function (int $exponent): void {
    expect(fn () => new Money(1, 'GBP', $exponent))->toThrow(MoneyMismatch::class, 'outside 0-4');
})->with([-1, 5]);

it('spells an amount without a float ever holding it', function (int $minor, int $exponent, string $expected): void {
    expect((new Money($minor, 'GBP', $exponent))->decimal())->toBe($expected);
})->with([
    [1050, 2, '10.50'],
    [5, 2, '0.05'],
    [-1250, 2, '-12.50'],
    [1234, 0, '1234'],
    [-7, 0, '-7'],
    [123456, 4, '12.3456'],
]);
