# Adoption

## Install

```bash
composer require liberusoftware/ecommerce-invoices-and-documents
```

Installing boots nothing. `extra.laravel.providers` is absent on purpose; the host's module manager
registers `InvoicesAndDocumentsServiceProvider` when `ecommerce-invoices-and-documents` is named in
`MODULES_ENABLED`. Then:

```bash
php artisan migrate
php artisan vendor:publish --tag=invoices-and-documents-config
```

## What the host must bind

Nothing is bound by default, and each unbound seam removes exactly one claim.

```php
// config/invoices-and-documents.php
'seams' => [
    'sale'      => App\Invoicing\OrderSaleSource::class,
    'renderer'  => App\Invoicing\PdfDocumentRenderer::class,
    'transport' => App\Invoicing\MailDocumentTransport::class,
],
'retention' => ['years' => 6],
```

A binding may be a class name resolved through the container, an instance, or a container binding of
the contract itself. It is resolved **at the moment of use**, so a rebinding takes effect on the next
call rather than the next deploy.

### `SaleSource` — required to issue anything

```php
public function sale(string $tenantId, string $saleReference): ?Sale;
```

Return `null` for a sale you cannot describe; the module refuses to draft rather than inventing
lines. The `Sale` you return must state its own net, tax and gross totals, and they must equal the
sum of the lines — the module refuses the draft otherwise, and that refusal is the guard the host
never had.

Give the module net, rate, tax and gross **already computed** for each line. It never divides,
rounds or recomputes a tax.

### `DocumentRenderer` — optional

Unbound, the module still issues, numbers, stores and lists documents; `RenderDocument` returns the
full render model with `unavailable = NoRendererBound`. Return `null` from a bound renderer to
decline one document — that is reported as `RendererDeclined`, a different answer.

### `DocumentTransport` — optional

Unbound, `RecordDelivery` writes a `pending` attempt row and refuses the transmission. The document
stays `issued`. Do not build a mailer here: the module records the intent and the outcome, the
foundation owns the transport.

### `retention.years`

Null means the host has not said. Erasure treats that as **unknown, not zero**, and refuses to redact
buyer identity on any issued document. Set it, or expect every subject-access erasure to come back
with refusals.

## What the host deletes, and why each is not adopted

| Host artefact | Adopted? |
|---|---|
| `app/Models/Invoice.php` | **No.** Its `products()` `belongsToMany` onto the live catalogue is the defect this module exists to remove. `generateForOrder` also creates a `Customer` named "Guest" as a side effect of a payment succeeding |
| `database/migrations/…_create_invoices_table.php` | **No.** No number, no currency, no tax breakdown, no seller, `onDelete('cascade')` from both `customers` and `orders`, and a `down()` that drops `invoice` rather than `invoices` |
| `database/migrations/…_create_invoice_product_table.php` | **No.** `product_id` is `cascadeOnDelete`: deleting a product deletes invoice lines |
| `app/Http/Controllers/InvoiceController.php` | **No.** Presentation, and it drops the ownership filter for `hasRole(['super_admin','admin'])`. The `-api` and `-livewire` packages replace it against `CustodyPolicy` |
| `app/Filament/App/Resources/Invoices/**` | **No.** Presentation, and it ships `CreateAction`, `EditAction`, `DeleteAction` and `DeleteBulkAction` on financial documents with no audit row and no soft delete. The `-filament` package replaces it; there is no update or delete path to expose |
| `app/Http/Livewire/InvoicePdf.php` | **No.** It returns a view that does not exist, sits in a directory Livewire 4 does not discover, has no authorization, and no PDF library is installed. Rendering is a seam |
| `app/Mail/InvoiceMail.php` | **No.** It names a view that does not exist and nothing constructs it. Delivery is `RecordDelivery` plus a bound transport |
| `database/factories/InvoiceFactory.php` | **No.** `definition()` returns `[]` |
| `OrderNumberSequence` / `AllocateOrderNumber` in `commerce-core` | **No, and deliberately.** Right for an order number, wrong for a fiscal series. See the ADR |

## Migrating existing invoices

There is no import path and that is deliberate: the host's rows do not contain a document. They have
no number, no currency, no tax breakdown, no seller identity, a buyer name that renders blank, and a
header total that does not match their own lines. Anything reconstructed from them would be a
document this module then promises never changes.

Issue forward. Keep the old table read-only for as long as the host's retention requires, and cite
the old primary key in the new document's `source_ref` if you need the link.

## Consuming it

```php
use Liberu\Ecommerce\InvoicesAndDocuments\Actions\{DraftDocument, IssueDocument, RecordDelivery};
use Liberu\Ecommerce\InvoicesAndDocuments\Enums\DocumentKind;

$drafted = (new DraftDocument())($tenantId, DocumentKind::Invoice, $order->reference);

if ($drafted->wasRefused()) {
    return $this->tellTheMerchant($drafted->reason);
}

$document = Document::query()->findOrFail($drafted->id);
$issued = (new IssueDocument())($tenantId, $document, 'INV');
```

Every mutation returns an `Outcome`. Check it. `happened()` is false for both *already recorded* and
*refused*, and they mean different things.
