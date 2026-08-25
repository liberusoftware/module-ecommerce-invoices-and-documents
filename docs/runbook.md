# Runbook

## A gapless series reports missing numbers

```php
$report = (new CheckSeriesContinuity())($tenantId, 'INV');
$report->missing;   // [2, 3]
$report->gapless;   // true
```

**This is the alarm that cannot wait.** Nothing in the domain can fill a hole afterwards: a number is
spent under a row lock inside the issuing transaction, so a rollback returns it and a void keeps it.
A hole in a gapless series means something outside the module moved `next_value` — a hand-edited row,
a restored backup, a migration run against live data.

Do not issue documents to close the gap; the numbers would carry today's date. Record the
discrepancy, take it to whoever files the return, and if the series must continue, open a new series
rather than papering over the old one.

## A series reports missing numbers and is not gapless

Expected only if somebody moved `next_value` by hand. `BurnNumber` records the hole as a row and
`CheckSeriesContinuity` counts it as accounted for, so a burn never shows as missing.

## `BurnNumber` refuses with `SeriesIsGapless`

Working as designed. That series promises no holes. If the jurisdiction genuinely permits holes,
the series was opened with the wrong policy — open a new one; do not flip the flag on a series that
has already issued documents under the old promise.

## Delivery attempts pile up in `pending`

No `DocumentTransport` is bound. The attempts are real facts — the module was asked, wrote the row,
and had nobody to hand it to. Bind a transport; then call `RecordDelivery` again **with a new
reference**, because the old reference is already spent and will answer `alreadyRecorded`.

```sql
SELECT channel, count(*) FROM invoicing_deliveries WHERE state = 'pending' GROUP BY channel;
```

## Everything renders with `unavailable = NoRendererBound`

No `DocumentRenderer` is bound. Documents are still issued, numbered, stored and listed, and
`RenderResult->model` carries everything a renderer would need. Bind one, or render from the model
in the presentation layer. This module ships no PDF library and will not.

## `RendererDeclined` on some documents and not others

A bound renderer returned `null` for that document. That is the renderer's answer, not the module's
— look at the renderer.

## Erasure comes back with refusals

```php
$report = (new ForgetParticipant())($subjectRef);
$report->wasComplete();       // false
$report->refusedDocuments;    // list<RetentionRefusal>
```

Check `windowIsUnknown()` first. **True** means `invoices-and-documents.retention.years` is not
configured, and the module refuses to guess. Configure it and run again.

**False** means the document is genuinely inside its window; `retainUntil` says until when. Contact
details, delivery addresses and notes were already redacted on those documents. The remaining
identity is being kept because the law says so — hand the list to whoever answers the subject, do not
work around it, and do not delete the row: there is no delete path and there is not meant to be.

## A caller says a document "already exists" but cannot find it

`alreadyRecorded` carries the existing `id` and `reference`. The natural key is
`(tenant_id, kind, source_ref)`. A caller looking under a different tenant or a different kind will
not find it. `FindDocument` takes the reference, never the primary key.

## `DocumentsAreImmutable` in the logs

Something tried to change a frozen attribute on an issued document, or edit or delete a line or a
history row. The exception message names the attributes. This is the guard working — find the caller.
A document is corrected by `DraftCreditNote`, never by an edit.

## Totals look wrong

They are summed from `invoicing_document_lines` every time. If a total looks wrong, the lines are
wrong, and they were wrong at draft: `DraftDocument` refuses a sale whose stated totals disagree with
its lines, so a mismatch means the `SaleSource` stated a total that matched lines it then supplied
differently. Look at the seam.

The module never divides, multiplies or rounds. A rounding difference did not come from here.

## A merchant sees another merchant's document

They cannot, from this module: every action and query takes the tenant and `CustodyPolicy` answers
standing in one place. If it happens, a caller passed the wrong tenant — check the caller, not the
module, and check that the caller is not deciding tenancy from a role name.
