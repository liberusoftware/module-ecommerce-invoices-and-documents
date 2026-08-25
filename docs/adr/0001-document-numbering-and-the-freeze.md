# 0001 — Document numbering, and why the freeze is the module

Status: accepted, 0.1.0

## Context

Three things in the host were called an invoice number and none of them was one. The customer-facing
"Invoice #" was the `invoices` primary key, shared across every merchant on the deployment. The
Filament resource's "Order" column was the `orders` primary key; the host has no `order_number`
column at all. And `module-ecommerce-commerce-core` ships a real allocated sequence — for orders, in
a module nothing consumes yet.

Meanwhile the host's invoice read almost everything it printed through a live relation: the lines
were a `belongsToMany` onto `Product`, the description was the product's current name, and the buyer
was the current `Customer` row.

## Decision 1: issue freezes, and the module is that sentence

Everything a document will ever display is copied at draft into rows this module owns. After that the
module reads the sale never again. This is not an optimisation; it is the definition of a document.
A credit note copies its identities from the document it corrects rather than re-reading the sale,
for the same reason — otherwise a credit note raised a year later would carry a buyer name the
invoice never had.

## Decision 2: document numbering is this module's, not `commerce-core`'s

`commerce-core` already ships `OrderNumberSequence` and `AllocateOrderNumber`, and the latter writes
its trade-off down:

> A gap is fine and a duplicate is not, which is why the number is spent on allocation rather than on
> the order being saved: a failed checkout burning a number costs nothing, and holding the lock until
> the order commits would put the payment gateway's latency inside a lock every other checkout waits
> on.

Both halves are correct for an order number and neither survives contact with a fiscal series.

**The first half is inverted here.** Several EU jurisdictions require an invoice series to be
gapless. A burned number does not cost nothing; it costs an explanation to an auditor.

**The second half does not apply here.** The reason `AllocateOrderNumber` will not hold the lock is
that a payment gateway sits inside the transaction it would have to wait for. Nothing of the kind
sits inside issuing a document: the sale was already read at draft, the lines are already rows, and
the issuing transaction is a local `UPDATE` and one `INSERT`. The latency this module puts inside the
lock is microseconds, and it buys a guarantee the other module could not afford.

So: allocation takes `lockForUpdate` on the series row **inside the transaction that writes the
document**. A rollback returns the number.

There is a third difference the addendum did not name. `commerce-core`'s sequence is keyed on
`(store_id, prefix)` — **per store**. The obligation to file belongs to the merchant, so a series
here belongs to one tenant. A merchant running three storefronts files one series unless it chooses
otherwise, which is the opposite default.

This is a boundary statement, not a criticism: `AllocateOrderNumber` is right about order numbers.
`commerce-core`'s epic is closed, so the notification is a comment rather than a reopen.

## Decision 3: gaplessness is a per-series policy

The addendum stated the gapless requirement more confidently than a survey supports, and flagged its
own doubt. The doubt is right: jurisdictions differ, and a module that hard-codes one answer is wrong
in half the world.

`invoicing_series.gapless` decides. A gapless series **refuses** `BurnNumber`. Any other series
records the burn as a row with a reason. Either way the module never leaves a hole nobody can
account for — the difference is whether a hole may exist at all, not whether it may be silent.
`CheckSeriesContinuity` reconciles what a series has spent against what accounts for it.

`fiscal` is the second policy, and it exists for one rule: a proforma may not be filed under a fiscal
series.

## Decision 4: void from every state, not only from issued

There is no delete path anywhere in this module. A draft therefore needs a way to be discarded, and a
delivered document found to be wrong still needs to be voidable. Void records — reason, actor and
previous state go in an event row — and keeps the number, which is what keeps a gapless series
gapless. Void from void is `alreadyRecorded`, which is the only state the enum forbids.

This extends the addendum's "plus `void` from `issued`", deliberately.

## Decision 5: no stored totals, and no recomputed tax

Both rules seem to conflict. They do not, because of one property: **the module only ever adds.**

Net, rate, tax and gross arrive per line already computed by the tax module and are stored exactly as
given. Every figure the module produces — the document totals, the per-rate summary — is a sum of
those integers. Addition of recorded integers cannot introduce a rounding difference, so deriving is
not recomputing.

Storing the totals instead would reintroduce the host's defect: its header total was copied from
`orders.total_amount` (gross, with shipping and discount) while only product lines were copied, so it
disagreed with its own lines from the day it was written.

The one place arithmetic is checked rather than derived is `DraftDocument`, which refuses a sale whose
stated totals disagree with the sum of its lines. That refusal is the guard the host never had.

## Consequences

- A number cannot be spent on a document that does not exist, and a hole in a gapless series is
  therefore an external event. The runbook treats it as an alarm rather than a cleanup.
- A `SaleSource` that computes its own totals inconsistently is refused at the boundary instead of
  producing a document that contradicts itself.
- The module cannot reuse `commerce-core`'s allocator, and never will; the two answer different
  questions.
