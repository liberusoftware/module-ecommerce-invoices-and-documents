<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\DocumentsAreImmutable;

/**
 * One entry in a document's history. The unique `(document_id, sequence)`
 * index arbitrates a concurrent append; this guard stops an existing row being
 * edited. Voiding writes here rather than erasing anything.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $document_id
 * @property int $sequence
 * @property EventKind $kind
 * @property string|null $from_state
 * @property string $to_state
 * @property string|null $actor_ref
 * @property array<string, mixed>|null $detail
 * @property Carbon $occurred_at
 */
class DocumentEvent extends Model
{
    protected $table = 'invoicing_document_events';

    protected $fillable = [
        'tenant_id', 'document_id', 'sequence', 'kind', 'from_state', 'to_state',
        'actor_ref', 'detail', 'occurred_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'kind' => EventKind::class,
        'detail' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $event): void {
            throw DocumentsAreImmutable::forEvent($event->document_id);
        });

        static::deleting(function (self $event): void {
            throw DocumentsAreImmutable::forEvent($event->document_id);
        });
    }
}
