<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Models;

use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Line;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\Money;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\DocumentsAreImmutable;

/**
 * A line as it was, not as the catalogue is. The description is a copy, so
 * renaming a product changes nothing that was ever issued.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $document_id
 * @property int $position
 * @property string $description
 * @property int $quantity_milli
 * @property int $unit_net_minor
 * @property int $net_minor
 * @property int $tax_rate_bp
 * @property int $tax_minor
 * @property int $gross_minor
 */
class DocumentLine extends Model
{
    protected $table = 'invoicing_document_lines';

    protected $fillable = [
        'tenant_id', 'document_id', 'position', 'description', 'quantity_milli',
        'unit_net_minor', 'net_minor', 'tax_rate_bp', 'tax_minor', 'gross_minor',
    ];

    protected $casts = [
        'position' => 'integer',
        'quantity_milli' => 'integer',
        'unit_net_minor' => 'integer',
        'net_minor' => 'integer',
        'tax_rate_bp' => 'integer',
        'tax_minor' => 'integer',
        'gross_minor' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $line): void {
            throw DocumentsAreImmutable::forLine($line->document_id);
        });

        static::deleting(function (self $line): void {
            throw DocumentsAreImmutable::forLine($line->document_id);
        });
    }

    public function toLine(string $currency, int $exponent): Line
    {
        return new Line(
            $this->description,
            $this->quantity_milli,
            new Money($this->unit_net_minor, $currency, $exponent),
            new Money($this->net_minor, $currency, $exponent),
            $this->tax_rate_bp,
            new Money($this->tax_minor, $currency, $exponent),
            new Money($this->gross_minor, $currency, $exponent),
        );
    }
}
