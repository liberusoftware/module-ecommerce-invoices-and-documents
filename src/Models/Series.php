<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A numbering series, per tenant. `commerce-core`'s sequence is per store and
 * spends a number on allocation; a fiscal series cannot afford either.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $code
 * @property string $prefix
 * @property int $pad
 * @property int $next_value
 * @property bool $fiscal
 * @property bool $gapless
 */
class Series extends Model
{
    use RestatesTenant;

    protected $table = 'invoicing_series';

    protected $fillable = ['tenant_id', 'code', 'prefix', 'pad', 'next_value', 'fiscal', 'gapless'];

    protected $attributes = [
        'prefix' => '',
        'pad' => 0,
        'next_value' => 1,
        'fiscal' => true,
        'gapless' => true,
    ];

    protected $casts = [
        'pad' => 'integer',
        'next_value' => 'integer',
        'fiscal' => 'boolean',
        'gapless' => 'boolean',
    ];

    /** How a number is spelled once spent. */
    public function format(int $value): string
    {
        return $this->prefix.str_pad((string) $value, $this->pad, '0', STR_PAD_LEFT);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        /** @var HasMany<Document, $this> */
        return $this->scopedToTenant($this->hasMany(Document::class, 'series_id'));
    }

    /** @return HasMany<BurnedNumber, $this> */
    public function burnedNumbers(): HasMany
    {
        /** @var HasMany<BurnedNumber, $this> */
        return $this->scopedToTenant($this->hasMany(BurnedNumber::class, 'series_id'));
    }
}
