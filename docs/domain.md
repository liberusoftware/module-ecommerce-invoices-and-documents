# Domain

What is modelled, and every decision behind it — including the ones rejected.

## 1. Issue freezes

The host's invoice printed `$product->name` at render time and read the buyer through
`$invoice->customer`. Four consequences, all verified in the host tree: renaming a product rewrote
past invoices; deleting one deleted invoice lines under a header total still printing; the line query
carried a store global scope, so an invoice read on a different channel returned fewer lines than it
was issued with; and erasure rewrote the buyer on every historical invoice.

So the module copies, at draft, everything the document will ever display: seller identity, buyer
identity, each line's description, quantity, unit price, tax rate and tax amount, and the currency.
After that it reads the sale never again. `IssueTest` asserts the seam is asked exactly once and that
emptying it afterwards changes nothing the document says.

**Rejected:** storing a foreign key to the sale and resolving lazily "for freshness". Freshness is
the defect.

## 2. Draft and issue are two moments

`draft → issued → delivered`, plus `void`. Drafting freezes; issuing spends the number. They are
separate because the number must be spent only when the document is certain to exist, and because a
merchant reviewing a document before it becomes a fiscal record is a real workflow.

`void` is reachable from **every** state but `void` itself. The addendum's decision 4 names only
`issued → void`. Extended deliberately: there is no delete path, so void is the only way to discard
a draft, and a delivered document found to be wrong must still be voidable. Voiding records — the
reason, the actor and the previous state go in an event row — and keeps the number, which is what
keeps a gapless series gapless.

## 3. Numbering

A series belongs to **one tenant**, has a prefix, a pad width, a next value and two policies.
Allocation takes `lockForUpdate` on the series row and happens inside the transaction that writes the
document.

`module-ecommerce-commerce-core` already ships an allocator, and its own docblock explains why it
spends the number on allocation rather than on the order being saved: a gap is fine, a duplicate is
not, and holding the lock until the order commits would put the payment gateway's latency inside a
lock every other checkout waits on. Both halves are correct for an order number. Neither survives
contact with a fiscal series, where several EU jurisdictions require the sequence to be gapless and
where there is no gateway inside the lock to worry about — the document write is local. The ADR
carries this in full.

Its sequence is also **per store**, which is the wrong grain: the filing obligation belongs to the
merchant, and one merchant with three storefronts must not file three interleaved series unless it
chooses to.

**Gaplessness is a policy, not an invariant.** The addendum stated it more confidently than a survey
can support, and that turned out to be the right doubt: jurisdictions differ. So `invoicing_series`
carries `gapless`. A gapless series refuses `BurnNumber`; any other series records the burn as a row.
Either way the module never leaves a hole nobody can explain. `CheckSeriesContinuity` reconciles the
numbers a series has spent against the documents and burns that account for them, and names anything
missing.

`fiscal` is the second policy. A proforma may not be filed under a fiscal series — it is a quotation
with a document's shape. A proforma with a non-fiscal series is numbered from it; a proforma with no
series is issued unnumbered. Both are exercised.

**Rejected:** a `sequence` column on the document alone, with the "next" value derived by
`MAX(sequence) + 1`. That is a read-then-write with a lock held by nobody.

## 4. Money

Minor units (int), an ISO 4217 alphabetic code, and the exponent that relates them. `Money` validates
both at construction and refuses to combine two currencies or two exponents. `decimal()` spells an
amount with integer division, so no float ever holds it.

The currency and its exponent live on the **document**, not on each line: a document has exactly one
currency by law, and a second copy on the line is a second thing that can disagree. `DocumentLine`
carries integers and `toLine()` re-attaches the document's currency.

Quantities are thousandths (`quantity_milli`), so 2.5 kg is 2500 and no float touches a document.

The host had no currency column on `orders` or `invoices` and hard-coded a dollar sign in both views,
while `gift_cards`, `abandoned_carts` and `shipping_quotes` all carried one — an omission, not a
convention.

## 5. Nothing derived is stored

There is no `total_amount` column. Totals are summed from the frozen lines each time they are asked
for. The host's stored header total was copied from `orders.total_amount` — gross, including
shipping, tax and discount — while only product lines were copied, so it disagreed with its own lines
from the moment it was written, before any product was deleted.

This module can afford the derivation because lines cannot vanish: `invoicing_document_lines` is
`restrictOnDelete` from the document, the document has no delete path, and a line has neither an
update nor a delete path.

The per-rate tax block is derived the same way — a grouping and a summation of figures that arrived
stated. **The module never divides, multiplies or rounds.** That is what lets it derive without
recomputing a tax the tax module computed: addition of recorded integers cannot introduce a rounding
difference.

**The one place the arithmetic is checked rather than derived:** `DraftDocument` refuses a sale whose
stated net, tax or gross disagrees with the sum of its lines. That is the guard the host never had.

## 6. Idempotency

The natural key is **the cause**: `(tenant_id, kind, source_ref)`, unique in the database. The cause
exists before the module does, so there is nothing to mint and nothing for a client to hold. A retry
returns `alreadyRecorded` with the same id.

`kind` is in the key on purpose, so one sale can carry an invoice and a receipt. `tenant_id` is in
the key on purpose, and `CustodyTest` asserts that a second merchant with a deliberately identical
`source_ref` gets its own document rather than the first merchant's.

A credit note's natural key is the same shape, where `source_ref` is the refund reference: the refund
exists before the credit note does. Delivery attempts key on `(tenant_id, reference)`, so a retried
send cannot transmit twice.

**No client-supplied idempotency key exists anywhere in this module**, because a natural key exists
everywhere.

Each unique index has a matching guard, because nothing about a unique index stops an `UPDATE` of a
row that already exists: `DocumentEvent` and `DocumentLine` refuse `updating` and `deleting`
outright, and `Document` refuses any change to a frozen attribute once issued.

## 7. Seams

`SaleSource`, `DocumentRenderer` and `DocumentTransport`. All three are resolved at the moment of use
— from a config key, a class name through the container, or a container binding of the contract —
and **none is bound by default**. `null` means nobody answered, which is not an answer of nothing.

The blast radius of each unbound seam is exactly the claim it controls:

| Unbound | What stops | What still works |
|---|---|---|
| `SaleSource` | Drafting, refused by name. Nothing is written | Everything already issued |
| `DocumentRenderer` | There is no file | Issue, number, store, list, and the full render model |
| `DocumentTransport` | The transmission, refused by name. The attempt is a `pending` row | The document stays `issued` rather than becoming `delivered` |

Nothing clamps, substitutes or passes zero through as an answer.

## 8. References

Every reference from outside the module — `source_ref`, `buyer_ref`, `seller_ref`, a delivery
reference — is an opaque string this module never resolves. There is no foreign key to `users`,
`orders`, `products` or another module's table.

## 9. Retention and erasure

The host had no retention rule at all, so it made the choice by accident and in the wrong direction:
`GdprErasureService` redacted the customer row in place and never named `invoices`, silently mutating
documents the law requires be kept unchanged.

`ForgetParticipant` splits what it may do from what it may not:

- **Always redacted:** delivery addresses, the buyer's contact email, and free-text notes.
- **Retention-gated:** buyer name, address, tax id and the buyer reference itself.

A document that never issued has no retention and is redacted outright. A document that issued under
a configured window is redacted once that window has passed. A document that issued with **no**
configured window is refused: unknown is not zero, and guessing in either direction is how the host
got here. Every refusal is returned as a `RetentionRefusal` naming the document, its number and its
retention date, so the caller can act on the conflict rather than discover it later.

The redacted buyer reference is `<token>:<document reference>` — distinct per document. Collapsing
every subject onto one shared token is the wave 19 defect: it makes two strangers look like the same
person to everything downstream.

Money never changes. `ErasureTest` asserts the summary is byte-identical before and after.

`ExportParticipantRecord` walks the same person-wide set across every tenant, and the suite asserts
the two agree about what "everything" is.

## 10. Events

Past tense, references and scalars only, never a model, and always the tenant.
`ParticipantForgotten` is dispatched **once per tenant touched** rather than once for a cross-tenant
run, because an event without a tenant is unactionable.

## 11. Standing

`CustodyPolicy` answers who may, in one place, and every check takes the tenant. The host dropped the
ownership filter entirely for `hasRole(['super_admin', 'admin'])`, so one merchant's admin read every
merchant's invoices on any host. `buyerMayRead` answers the customer side from the buyer's own
reference — never a role name — and refuses on a redacted document.

## 12. Rejected placements

- **Refunds.** A refund is money moving and belongs to `refunds`. A credit note is a document about
  money having moved, and is this module's.
- **Payments.** The payment belongs to `payment-operations`; the receipt is a document.
- **Tax.** `tax` ships `Quote`, `QuoteLine`, `RateVersion` and `QuoteSupersession`, where a figure is
  a claim about the past that can be superseded. A document records the figure actually charged and
  never supersedes it. A superseded quote does not change an issued document; it justifies a credit
  note.
- **Transports.** The foundation owns mail and its queue. This module records intent and outcome.
- **VAT reporting.** `OssReportService` and `EcSalesListService` are the tax module's territory. What
  matters here is that they report from orders, and the document a tax authority asks for is the
  invoice — which is now citable, because it has a number.
