<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DeliveryState;

/**
 * One attempt to put a document in front of somebody. The row exists before
 * the transmission does, so a transport that never answered leaves a pending
 * fact rather than nothing at all.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $document_id
 * @property string $reference
 * @property string $channel
 * @property string|null $address
 * @property DeliveryState $state
 * @property string|null $detail
 * @property Carbon $attempted_at
 * @property Carbon|null $settled_at
 * @property Carbon|null $redacted_at
 */
class DeliveryAttempt extends Model
{
    protected $table = 'invoicing_deliveries';

    protected $fillable = [
        'tenant_id', 'document_id', 'reference', 'channel', 'address',
        'state', 'detail', 'attempted_at', 'settled_at', 'redacted_at',
    ];

    protected $casts = [
        'state' => DeliveryState::class,
        'attempted_at' => 'datetime',
        'settled_at' => 'datetime',
        'redacted_at' => 'datetime',
    ];
}
