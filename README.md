# Ecommerce — Invoices and Documents

The document a sale produces, and the number that document is filed under.

## The fact that shaped it

The host application's invoice was a live view of the catalogue and the customer, not a document.
Its lines were a `belongsToMany` straight onto `Product`, so renaming a product rewrote every past
invoice that contained it; deleting one removed lines from a header total that carried on printing;
and GDPR erasure redacted the customer row in place, so every invoice ever issued to that person
resolved to "Deleted / User". There was no number column at all — the customer-facing "Invoice #"
was the `invoices` primary key, shared across every merchant on the deployment.

A document that changes after it is issued is not a document. **Issue freezes.** Everything a
document will ever display is copied at issue into rows this module owns, and after that no other
module's data can alter what it says.

## What it owns

| | |
|---|---|
| `invoicing_series` | A numbering series per tenant: prefix, pad, next value, fiscal, gapless |
| `invoicing_documents` | The issued document, its frozen parties, its currency, its number, its state |
| `invoicing_document_lines` | The frozen lines: description, quantity, unit price, net, rate, tax, gross |
| `invoicing_document_events` | Append-only history; every state move writes one |
| `invoicing_deliveries` | One row per attempt to put the document in front of somebody |
| `invoicing_burned_numbers` | A number spent on nothing, on the record |

## What it does not own

Orders, payments, refunds, tax calculation, customers, mail transports and PDF libraries. A refund
is money moving and belongs to `refunds`; a **credit note is a document about money having moved**
and is this module's. A payment belongs to `payment-operations`; the **receipt** is this module's.
A tax figure in `tax` is a claim that can be superseded; the figure a document records was what was
actually charged and is never superseded — a superseded quote justifies a credit note instead.

## What it publishes

| Kind | Names |
|---|---|
| Actions | `OpenSeries` `DraftDocument` `DraftCreditNote` `IssueDocument` `VoidDocument` `RecordDelivery` `BurnNumber` `ForgetParticipant` |
| Queries | `FindDocument` `ListDocuments` `SummariseDocument` `BuildRenderModel` `RenderDocument` `CheckSeriesContinuity` `ExportParticipantRecord` |
| Contracts | `SaleSource` `DocumentRenderer` `DocumentTransport` — none bound by default |
| Data | `Money` `Line` `Party` `Sale` `Outcome` `DocumentSummary` `TaxRateTotal` `RenderModel` `RenderResult` `Rendered` `TransportOutcome` `ParticipantRecord` `ExportedDocument` `ForgetReport` `RetentionRefusal` `ContinuityReport` |
| Events | `DocumentIssued` `DocumentVoided` `DocumentDelivered` `DeliveryFailed` `NumberBurned` `ParticipantForgotten` |
| Policy | `CustodyPolicy` — every check takes the tenant |

Every mutation returns an `Outcome` that says which of three things happened: recorded, already
recorded, or refused and why. There is no `void` return and no silent no-op.

`docs/domain.md` carries the decisions, `docs/adoption.md` how a host installs it and what it
deletes, `docs/runbook.md` what breaks and what to do about it.
