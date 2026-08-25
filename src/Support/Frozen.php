<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Support;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\DocumentSummary;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Line;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\TaxRateTotal;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\Document;
use Liberu\Ecommerce\InvoicesAndDocuments\Models\DocumentLine;

/** Lines are copied, added and never recomputed: every figure here arrived stated. */
final class Frozen
{
    /**
     * @param  list<Line>  $lines
     * @return array{string, int}|null the one currency these lines share, or null if they do not share one.
     */
    public static function currencyOf(array $lines): ?array
    {
        if ($lines === []) {
            return null;
        }

        $currency = $lines[0]->net->currency;
        $exponent = $lines[0]->net->exponent;

        foreach ($lines as $line) {
            foreach ([$line->unitNet, $line->net, $line->tax, $line->gross] as $money) {
                if ($money->currency !== $currency || $money->exponent !== $exponent) {
                    return null;
                }
            }
        }

        return [$currency, $exponent];
    }

    /** @param  list<Line>  $lines */
    public static function summarise(array $lines, string $currency, int $exponent): DocumentSummary
    {
        $net = Money::zero($currency, $exponent);
        $tax = Money::zero($currency, $exponent);
        $gross = Money::zero($currency, $exponent);

        /** @var array<int, array{net: Money, tax: Money, gross: Money}> $byRate */
        $byRate = [];

        foreach ($lines as $line) {
            $net = $net->plus($line->net);
            $tax = $tax->plus($line->tax);
            $gross = $gross->plus($line->gross);

            $rate = $line->taxRateBasisPoints;
            $byRate[$rate] ??= ['net' => Money::zero($currency, $exponent), 'tax' => Money::zero($currency, $exponent), 'gross' => Money::zero($currency, $exponent)];
            $byRate[$rate] = [
                'net' => $byRate[$rate]['net']->plus($line->net),
                'tax' => $byRate[$rate]['tax']->plus($line->tax),
                'gross' => $byRate[$rate]['gross']->plus($line->gross),
            ];
        }

        ksort($byRate);

        $rates = [];

        foreach ($byRate as $rate => $totals) {
            $rates[] = new TaxRateTotal($rate, $totals['net'], $totals['tax'], $totals['gross']);
        }

        return new DocumentSummary($net, $tax, $gross, $rates);
    }

    /** @param  list<Line>  $lines */
    public static function write(Document $document, array $lines): void
    {
        foreach ($lines as $position => $line) {
            DocumentLine::query()->create([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'position' => $position + 1,
                'description' => $line->description,
                'quantity_milli' => $line->quantityMilli,
                'unit_net_minor' => $line->unitNet->minor,
                'net_minor' => $line->net->minor,
                'tax_rate_bp' => $line->taxRateBasisPoints,
                'tax_minor' => $line->tax->minor,
                'gross_minor' => $line->gross->minor,
            ]);
        }
    }

    /** @return list<Line> */
    public static function linesOf(Document $document): array
    {
        $lines = [];

        foreach ($document->lines()->get() as $row) {
            $lines[] = $row->toLine($document->currency, $document->currency_exponent);
        }

        return $lines;
    }
}
