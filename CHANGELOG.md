# Changelog

## 0.1.0

Extracted the document a sale produces out of the host application, where it was a live view of the
catalogue and the customer rather than a record.

### The boundary

- Invoice, credit note, receipt and proforma in one table behind one state machine:
  `draft → issued → delivered`, and `void` from any of them.
- Issue copies the seller, the buyer, every line and the currency into rows this module owns. After
  issue the module reads nothing from the sale. The host read line descriptions and the customer
  name through live relations at render time.
- An issued document has no update path and no delete path. The frozen columns are guarded on the
  model; a correction is a credit note that references the original.
- The document's customer-facing handle is a minted reference, not the primary key. The host showed
  people `#{{ $invoice->id }}`, which enumerates every document on the deployment and is not a valid
  invoice number in any jurisdiction that regulates invoicing.

### Numbering

- A series belongs to one tenant and is allocated under a row lock inside the transaction that
  writes the document, so a rollback returns the number. `commerce-core`'s `AllocateOrderNumber`
  spends on allocation and documents why; that trade-off is right for an order number and wrong for
  a fiscal series. See `docs/adr/0001-document-numbering-and-the-freeze.md`.
- Gaplessness is a **per-series policy**, not a universal invariant: a gapless series refuses to burn
  a number, and any other series records the burn. Nothing in this module ever leaves a silent hole.

### Money and tax

- Minor units, an ISO 4217 code and a stored exponent, together, on every figure. The host had no
  currency column anywhere on the money path and hard-coded a dollar sign in both views.
- Totals are summed from the frozen lines rather than stored. A draft whose stated totals disagree
  with the sum of its lines is refused, so a document cannot issue with a header its lines
  contradict — which the host's did on day one, before any product was deleted.
- Net, rate, tax and gross are recorded per line and summarised per distinct rate. The module adds;
  it never divides, rounds or recomputes a tax figure.

### Delivery and rendering

- Rendering is an unbound seam. With nothing bound the module still issues, numbers, stores and
  lists documents, and reports that no renderer is configured. A renderer that declined is a
  different answer from one that was never asked. No PDF library is installed.
- A delivery attempt persists before it transmits, and sent, failed or suppressed is a row.
  "Delivered" is what a transport answered. The host had a mailable naming a view that does not
  exist and nothing that ever constructed it.

### Data protection

- Retention outranks erasure and the conflict is returned, not resolved silently. Contact details,
  delivery addresses and notes are always redacted; the buyer identity on a document still inside
  its retention window is refused, by name, with the date. A window the host never configured is
  unknown rather than zero.
- A redacted buyer reference stays distinct per document rather than collapsing every subject onto
  one shared token.
- Export walks the same person-wide set across every tenant that erasure walks, so the two agree
  about what "everything" is.

### Deliberately not shipped

- **No PDF or HTML rendering.** A domain package that installed a PDF library would make every
  consumer install one.
- **No document-level discount, rounding or payment-terms arithmetic.** Nothing in the survey
  exercised them, and an unexercised money rule is a lying constraint.
- **No dunning, statements or packing slips.** Not in the epic's boundary.
- **No `current_state`-style mutable columns and no stored totals.** Both are derivations.
