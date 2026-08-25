<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentState;
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\EventKind;
use Liberu\Ecommerce\InvoicesAndDocuments\Exceptions\DocumentsAreImmutable;

/**
 * An issued document, and everything it will ever say. The host's invoice read
 * its lines and its buyer through live relations, so renaming a product
 * rewrote history and deleting one removed lines from a total still printing.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $reference
 * @property DocumentKind $kind
 * @property DocumentState $state
 * @property string $source_ref
 * @property int|null $series_id
 * @property string|null $number
 * @property int|null $number_sequence
 * @property string $currency
 * @property int $currency_exponent
 * @property int|null $corrects_document_id
 * @property string $seller_ref
 * @property string $seller_name
 * @property string $seller_address
 * @property string|null $seller_tax_id
 * @property string $buyer_ref
 * @property string $buyer_name
 * @property string $buyer_address
 * @property string|null $buyer_tax_id
 * @property string|null $buyer_email
 * @property string|null $note
 * @property Carbon|null $issued_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property Carbon|null $retain_until
 * @property Carbon|null $redacted_at
 */
class Document extends Model
{
    use RestatesTenant;

    /**
     * What issue freezes. Erasure may still redact the buyer and the note, and
     * the state machine still moves; nothing here changes again, ever.
     */
    public const FROZEN = [
        'tenant_id', 'reference', 'kind', 'source_ref', 'series_id', 'number',
        'number_sequence', 'currency', 'currency_exponent', 'corrects_document_id',
        'seller_ref', 'seller_name', 'seller_address', 'seller_tax_id', 'issued_at',
    ];

    protected $table = 'invoicing_documents';

    protected $fillable = [
        'tenant_id', 'reference', 'kind', 'state', 'source_ref', 'series_id', 'number',
        'number_sequence', 'currency', 'currency_exponent', 'corrects_document_id',
        'seller_ref', 'seller_name', 'seller_address', 'seller_tax_id',
        'buyer_ref', 'buyer_name', 'buyer_address', 'buyer_tax_id', 'buyer_email',
        'note', 'issued_at', 'delivered_at', 'voided_at', 'void_reason',
        'retain_until', 'redacted_at',
    ];

    protected $casts = [
        'kind' => DocumentKind::class,
        'state' => DocumentState::class,
        'currency_exponent' => 'integer',
        'number_sequence' => 'integer',
        'issued_at' => 'datetime',
        'delivered_at' => 'datetime',
        'voided_at' => 'datetime',
        'retain_until' => 'datetime',
        'redacted_at' => 'datetime',
    ];

    public static function mintReference(): string
    {
        return (string) Str::ulid();
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            $was = $document->getOriginal('state');
            $state = $was instanceof DocumentState ? $was : (is_string($was) ? DocumentState::tryFrom($was) : null);

            if (! $state instanceof DocumentState || ! $state->isIssued()) {
                return;
            }

            $frozen = array_values(array_intersect(array_keys($document->getDirty()), self::FROZEN));

            if ($frozen !== []) {
                throw DocumentsAreImmutable::forDocument($document->reference, $frozen);
            }
        });

        static::deleting(function (self $document): void {
            throw DocumentsAreImmutable::forDeletion($document->reference);
        });
    }

    /** @return BelongsTo<Document, $this> */
    public function corrects(): BelongsTo
    {
        /** @var BelongsTo<Document, $this> $relation */
        $relation = $this->scopedToTenant($this->belongsTo(self::class, 'corrects_document_id'));

        return $relation;
    }

    /** @return HasMany<Document, $this> */
    public function corrections(): HasMany
    {
        /** @var HasMany<Document, $this> $relation */
        $relation = $this->scopedToTenant($this->hasMany(self::class, 'corrects_document_id'));

        return $relation;
    }

    /** @return HasMany<DocumentLine, $this> */
    public function lines(): HasMany
    {
        /** @var HasMany<DocumentLine, $this> $relation */
        $relation = $this->scopedToTenant($this->hasMany(DocumentLine::class, 'document_id'));

        return $relation->orderBy('position');
    }

    /** @return HasMany<DocumentEvent, $this> */
    public function events(): HasMany
    {
        /** @var HasMany<DocumentEvent, $this> */
        return $this->scopedToTenant($this->hasMany(DocumentEvent::class, 'document_id'));
    }

    /** @return HasMany<DeliveryAttempt, $this> */
    public function deliveries(): HasMany
    {
        /** @var HasMany<DeliveryAttempt, $this> */
        return $this->scopedToTenant($this->hasMany(DeliveryAttempt::class, 'document_id'));
    }

    /** @param  array<string, mixed>  $detail */
    public function recordEvent(EventKind $kind, DocumentState $to, ?DocumentState $from = null, ?string $actorRef = null, array $detail = []): DocumentEvent
    {
        return DocumentEvent::query()->create([
            'tenant_id' => $this->tenant_id,
            'document_id' => $this->id,
            'sequence' => $this->nextEventSequence(),
            'kind' => $kind,
            'from_state' => $from?->value,
            'to_state' => $to->value,
            'actor_ref' => $actorRef,
            'detail' => $detail === [] ? null : $detail,
            'occurred_at' => Carbon::now(),
        ]);
    }

    private function nextEventSequence(): int
    {
        $highest = $this->events()->max('sequence');

        return (is_int($highest) ? $highest : 0) + 1;
    }

    public function isRedacted(): bool
    {
        return $this->redacted_at instanceof Carbon;
    }
}
