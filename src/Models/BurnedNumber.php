<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A number a series spent on nothing. It exists so that a hole in a series is
 * a record rather than a silence somebody has to explain to an auditor.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $series_id
 * @property string $number
 * @property int $number_sequence
 * @property string $reason
 * @property Carbon $burned_at
 */
class BurnedNumber extends Model
{
    protected $table = 'invoicing_burned_numbers';

    protected $fillable = ['tenant_id', 'series_id', 'number', 'number_sequence', 'reason', 'burned_at'];

    protected $casts = [
        'number_sequence' => 'integer',
        'burned_at' => 'datetime',
    ];
}
