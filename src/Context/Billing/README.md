# Billing Bounded Context

## Purpose

What a client owes the clinic and what they paid: invoices (« factures »),
credit notes (« avoirs »), payments, the cash desk and cheque deposits. Billing
turns a visit (Consultation) or a counter sale (Catalog) into an invoice,
numbers it, takes its payment and corrects it with credit notes. It prepares
professional invoices for French e-invoicing (EN16931): fiscal identities,
invoicing framework, payment terms and a local readiness check.

## Ubiquitous Language

| Term | Definition |
|------|------------|
| **Invoice** | Aggregate. A draft is a working document; issuing numbers and **freezes** it. |
| **CreditNote** | « Avoir »: born issued, cancels out lines of an issued invoice, never changes afterwards. |
| **InvoiceLine** | One line: the price **as entered** (`enteredUnitPrice` + `PriceMode`), quantity, VAT rate, tax category and its `OperationCategory` (goods/services), plus the amounts the document calculated (net BT-131, gross for display, unit net BT-146 at 6 decimals). |
| **PriceMode** | Whether the entered unit price includes VAT (`incl`) or not (`excl`). |
| **CalculationMode** | `INCLUDING_TAX_FIRST` (private clients: they pay exactly the displayed prices) or `EXCLUDING_TAX_FIRST` (professionals: EN16931 strict). |
| **InvoiceAmountCalculator** | Domain service computing every amount **per VAT rate** (`RateBucket`: line total, allowance BT-92, base BT-116, VAT BT-117, gross). |
| **Allowance** | The document-level discount (BG-20), split per rate. Line nets stay undiscounted (BR-CO-10). |
| **Payer** | Who owes the invoice, snapshotted at draft time; carries a `BuyerFiscalIdentity` for a business subject to French VAT. |
| **BuyerFiscalIdentity** | Legal name, SIREN (BT-47), VAT number (BT-48), structured address (BG-8) and the default electronic address (BT-49: scheme `0225` + SIREN). |
| **SellerIdentity** | The clinic as seller, read from the Clinic context (letterhead, billing settings) through `SellerIdentityProviderInterface`. |
| **ProcessingRule** | `B2B` (invoice to a French business, to be transmitted as an e-invoice) or `B2C` (to be e-reported later). Known on the draft, frozen at issue. |
| **InvoicingFramework** | BT-23 « cadre de facturation »: nature (`S` services, `B` goods, `M` mixed — default pending the accountant) + case (`1` to pay, `2` paid at issue). |
| **PaymentTerms** | Due date (BT-9) and note (BT-20): « Paiement à N jours » or « Facture acquittée »; professional mentions PMD / PMT / AAB. |
| **ElectronicInvoiceReadiness** | Local check of a B2B draft before a number is drawn (`ReadinessRequirement`). |

## Amounts

- **No float anywhere.** Money is integer minor units; unit nets are integer
  micro-units (`Shared/Money` `UnitPrice`); splits use the largest remainder
  method (`LargestRemainderAllocator`).
- **B2B — HT-first, EN16931 strict.** Unit net = entered HT price, or entered
  TTC / (1 + rate) to 6 decimals; line net = round(qty × unit net); base per
  rate = Σ line nets − allowance; VAT per rate = round(base × rate) once.
  BR-CO-10 / 13 / 17 and BT-131 = BT-129 × BT-146 hold by construction. A price
  entered TTC may come out one cent off: the counter shows the recalculated
  total before payment.
- **B2C — TTC-first.** Line TTC = round(qty × unit TTC) (an HT price is grossed
  up per unit to the cent); per rate TTC is exact, base = round(TTC / (1 +
  rate)), VAT = TTC − base, line nets share the base by largest remainder.
- **Discount.** Percentage: B2B allowance = round(line total × pct); B2C
  discounted TTC = TTC − round(TTC × pct). Fixed: an amount incl. VAT shared
  over the rates by largest remainder, capped at the document.
- **No BT-114** (rounding amount) is ever produced.
- **Frozen at issue.** Line amounts, the per-rate breakdown (`tax_breakdown`
  JSON) and the totals are stored; an issued invoice is reloaded with them and
  **never recalculated**. Only drafts are recalculated (on every change and on
  reload).
- **Credit notes** (`CreditNoteCalculator`) work from the invoice's frozen
  amounts only: credited lines keep their nets; per rate the note takes its
  share of the allowance and its VAT (B2B: base × rate; B2C: its share of what
  the client paid); the note that credits the last lines of a rate takes
  exactly what is left, so the notes of an invoice add up to it per rate.

## Business Invariants

1. A draft is the only editable state; issuing requires at least one line and,
   for a visit, an identified payer.
2. A B2B draft is numbered only when `ElectronicInvoiceReadiness` finds nothing
   missing (buyer legal name, SIREN, address; every line with a resolved tax
   category and a non-zero rate; seller SIREN, VAT number, address, legal name,
   unique numbering per SIREN). The check runs **before** `numbers->next()`:
   a refusal leaves the sequence and the draft untouched.
3. **An issued B2B invoice is never cancelled** — « Une facture professionnelle
   émise ne s'annule pas : créez un avoir. » B2C cancellation is unchanged.
4. A line is credited once; credit notes never credit more than the invoice.
5. A tax category Taxation cannot resolve leaves a B2C line at 0 % with no
   nature; a B2B draft refuses it. A manual line requires a Taxation category.

## Use Cases

| Command / Query | What it does |
|---|---|
| `PrepareInvoiceForAdmission` | Rebuilds the draft of a visit from its billable consultation lines (price + mode snapshotted by Consultation). |
| `CheckoutAdmission` | Checks readiness, issues the visit's invoice and records the payment. |
| `RecordCounterSale` | Bills articles at the desk (to a client, a newcomer — a professional with its address — or nobody), checks readiness, issues and pays. |
| `AddManualInvoiceLine` | Adds a line typed VAT included with a mandatory Taxation category. |
| `IssueCreditNote` | Credits lines of an issued invoice (type 381, BT-25/26 of the invoice). |
| `GetInvoiceDetails`, `SearchInvoices`, … | Read models built on the frozen totals, with the transmission status of professional documents. |
| `RequestDocumentTransmission` | Reloads an issued B2B invoice or credit note and hands it to ElectronicInvoicing. |
| `RecordTransmissionStatus` | Projects a status reported by ElectronicInvoicing (newer sequence only). |
| `ReconcileProfessionalTransmissions` | Hourly (schedule `billing`, minute 23): for each document still « requested » after an hour, projects its status when ElectronicInvoicing has it, otherwise re-requests it, and after 24 hours marks it `request_failed` and logs an error. Each document on its own: a failure is logged and the next one goes on. |

## Electronic invoicing bridge

Professional (B2B) invoices and credit notes are transmitted to the clinic's
approved platform by the `ElectronicInvoicing` system context
(`src/System/ElectronicInvoicing`). Private (B2C) documents go nowhere
(e-reporting comes later).

1. **Issue.** `InvoiceIssued` / `CreditNoteIssued` carry the processing rule.
   For B2B, `InvoiceIssuedIntegrationBridge` / `CreditNoteIssuedIntegrationBridge`
   **freeze the neutral document** (`ElectronicInvoiceTransmitterInterface::freeze*`,
   stored in `transmission_document`), mark the projection `requested` and
   write a `ProfessionalDocumentIssuedIntegrationEvent` to the outbox — in the
   issuing transaction: a rollback leaves none of them, and the issue never
   depends on the transmission. `TransmittableInvoiceMapper` builds the
   document from the frozen amounts, with the seller's identity and the amount
   paid at the desk (BT-113) as they are at that moment, dates as local dates
   in Europe/Paris (as on the PDF): lines named as on the PDF (BT-153), the VAT
   breakdown, allowances, the professional mentions (PMD, PMT, AAB) and the
   seller's legal sentence (REG). Nothing else of the visit leaves: not the
   animal, not the invoice notes. Payments received after the issue never
   change it (they will be reported by status 212).
2. **Request.** `OnProfessionalDocumentIssuedRequestTransmission` (async)
   dispatches `RequestDocumentTransmission`, which hands the frozen document
   as is to `CommandBusElectronicInvoiceTransmitter`
   (`Infrastructure/Adapter/ElectronicInvoicing/`) — nothing is read again.
3. **Status.** `OnInvoiceTransmissionStatusChanged` receives
   `InvoiceTransmissionStatusChangedIntegrationEvent` and dispatches
   `RecordTransmissionStatus`; `DbalTransmissionStatusProjection` writes the
   `transmission_*` columns of `billing__invoices` / `billing__credit_notes`
   only when the event's sequence is greater than the stored one (duplicates and
   late deliveries change nothing). Reads never call ElectronicInvoicing.

States shown on the list and the sheet (`TransmissionBadge`, Presentation):
`requested` « Transmission demandée », `request_failed` « Transmission non
demandée » (Action requise), `pending_connection` « Plateforme non connectée »,
`queued` / `submitting` « En attente d'envoi », `awaiting_invoice` « En
attente de la facture d'origine » (a credit note), `submitted` « Transmise »,
`not_required` « Avoir interne — facture refusée / rejetée », `blocked` (one
label per reason), then the French label of the latest lifecycle status
(« Approuvée », « Refusée », « En litige », …; 200 `NON_TRANSMISE`
« Déposée — client sans plateforme »). « Action requise » gives the next step
with its link: « Créez un avoir puis une nouvelle facture. » (210, 213, 220 —
opens the credit note), « Contactez le client. » (207, 208 — the client's
sheet), « Contactez le support Kiveto. » (221, 501, blocked, request failed).

Billing imports from ElectronicInvoicing only its `Application\Command\…`,
`Application\Query\…` and `Domain\Event\*IntegrationEvent`
(`tests/Unit/Architecture/ElectronicInvoicingBoundaryTest.php`).

## Sending and reminding invoices

« Envoyer » and « Relancer » on the Facturation page only validate, log and
queue; a worker renders the PDF and asks Notification for the messages.

| Command / Query | What it does |
|---|---|
| `SendInvoiceToClient` (invoice ids, actor) | Per invoice: issued, partly paid or paid, payer not archived with an e-mail (« Envoyer » is e-mail only, whatever the consents) → logs a `SEND` dispatch and queues `SendInvoiceEmail` (`async`) in one transaction. Returns `InvoiceDispatchOutcome` (queued, skipped with reason). |
| `SendInvoiceEmail` | Re-checks, ensures the stored PDF, requests `INVOICE_SENT` (variant « Reste dû » with the amount, or « Réglée ») under `invoice:<id>:send:<dispatchId>` and records the message id on the dispatch — or records it skipped with the reason (cancelled, no e-mail any more). |
| `PrepareUnpaidReminder` | Before confirmation: the invoice ids (the oldest 500 at most, `RemindUnpaidInvoices::MAX_INVOICES`; the others counted as remaining), clients, SMS and SMS segments (estimated by Notification's `PreviewSmsSegments`), skipped count. |
| `RemindUnpaidInvoices` (invoice ids — at most 500 —, actor) | « Relancer tous les impayés » posts the ids its confirmation listed. Locks the invoice rows first (a double click ends with one reminder), re-selects the eligible invoices, logs one `REMINDER` dispatch per invoice with its channels and queues `SendUnpaidReminder` (`async`). |
| `SendUnpaidReminder` | Nothing if settled meanwhile, on a channel whose address or « Facturation » consent is gone, or for an archived payer — the dispatch is then recorded skipped with the reason; e-mail with the PDF and / or SMS (`INVOICE_REMINDER`, keys `invoice:<id>:reminder:<dispatchId>:<channel>`). The SMS is non-critical: its e-mail fallback is passed only when e-mail is not already a channel. |
| `EnsureSentInvoiceFile` | Public: the PDF of an issued invoice as sent to the client (rendered and stored at its first send, the same file afterwards). Used by Consultation's documents e-mail; the file stays Billing's (`owner = REQUESTER`). |
| `ListInvoiceDispatches` | The invoice's sends and reminders with their messages' delivery status (one `GetMessageStatuses` query): « Envoyée le… », « relancé 2 fois », a bounced e-mail reads « Non délivré ». |

Rules:

- **Reminder eligibility** (`UnpaidReminderSelection`): an issued or partly
  paid invoice with a balance due — **credit notes are never reminded** —, not
  reminded yet today (Europe/Paris day, D10 same-day guard), payer reachable on
  the « Facturation » (`billing`) consent's channels, else on the transactional
  channel (preferred channel when reachable, else e-mail, else SMS).
- **Dispatch log** (D10): `billing__invoice_dispatches` (invoice, kind `SEND` |
  `REMINDER`, channels, Notification message ids or skipped reason, author,
  date) feeds the history and the same-day guard; a dispatch already sent or
  skipped is never sent again, and its recorded outcome is never overwritten
  (guard in the upsert). A skipped reminder shows « non envoyée (motif) » and
  neither counts as a reminder nor holds back the next one.
- **Stored PDF**: `billing__sent_document_files` records the file written at
  `billing/sent/invoice/<id>.pdf` (deterministic key, network first, writes
  last; two concurrent first sends write the same file). Every later send and
  reminder attaches that file; the retention purge never deletes it.

Ports: `ClientContactInterface` (Client `GetClientNotificationContact` /
`GetClientsNotificationContacts`, « Facturation » consent applied),
`NotificationRequesterInterface`, `SmsSegmentEstimatorInterface`,
`DeliveryStatusReaderInterface` (Notification, under
`Infrastructure/Adapter/{Client,Notification}/`), `VisitDirectoryInterface`
(visit label), `InvoicePdfRendererInterface` (D5: invoice and credit-note
PDFs, `Infrastructure/Document/InvoicePdfRenderer`), `ClinicDocumentContextInterface`,
`SentDocumentFileStoreInterface`, `DispatchJobsInterface` (Messenger).

## Fixture examples

Demo invoices are built by `fixtures/Context/Billing/Story/*`: visits of the
demo admissions, counter sales, debts for the directory balances, and B2B
invoices to the demo professionals. `fixtures/System/ElectronicInvoicing/Story/ElectronicInvoicingDemoStory.php`
transmits them through the local platform simulator (Paris connected, Lyon
and Cassis not), so every transmission badge appears.
