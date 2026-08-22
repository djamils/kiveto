---
title: 'Billing Bounded Context — invoices, payments and checkout'
slug: 'context-billing-bc'
created: '2026-08-22'
status: 'complete — 2026-08-22, branch feature/billing-bc'
stepsCompleted: [1, 2, 3, 4, 5, 6]
tech_stack:
  - 'Symfony 7 + Doctrine ORM (DDD monolith, BCs under src/Context)'
  - 'Twig + importmap (page modules via the data-page dispatcher in assets/app.js)'
  - 'Tailwind CSS v4 + hand-written page CSS (assets/styles/pages/**)'
  - 'PHPUnit (unit: mocks; integration: KernelTestCase + Foundry + dama), PHPStan level max'
files_to_modify:
  - 'src/Context/Billing/** (new bounded context)'
  - 'src/Context/Consultation/Application/Query/ListBillableLinesForAdmission/** (new read query)'
  - 'src/Presentation/Clinic/Controller/Billing/** (new single-action controllers)'
  - 'templates/clinic/billing/** (list, detail, checkout, print)'
  - 'templates/components/layout/sidebar.html.twig (Facturation entry)'
  - 'assets/styles/pages/billing/** + assets/js/pages/billing/**'
  - 'migrations/Billing/** (new tables)'
  - 'config/packages/doctrine.yaml, config/packages/doctrine_migrations.yaml, config/services.yaml'
  - 'fixtures/Context/Billing/** + fixtures/Dataset/ClinicDataset.php'
code_patterns:
  - 'Aggregate root extends AggregateRoot, private ctor + create()/reconstitute() named constructors'
  - 'Commands: scalar readonly DTOs + #[AsMessageHandler] handlers'
  - 'Cross-BC: Application ports + Dbal/Messenger adapters in Infrastructure/Adapter/<BC>/'
  - 'Doctrine entities are dumb rows, a Mapper converts to/from the aggregate'
  - 'Table names come from BoundedContextPrefixNamingStrategy: billing__*'
  - 'Single-action controllers, one __invoke() per route'
test_patterns:
  - 'tests/Unit mirrors src/, handler tests with PHPUnit mocks (exact exception messages)'
  - 'tests/Integration: KernelTestCase + Foundry factories + dama isolation'
  - '100% line coverage on src/Context/Billing (Presentation excluded)'
---

# Tech-Spec: Billing Bounded Context

**Created:** 2026-08-22

## Overview

### Problem Statement

The clinic can treat a patient but cannot charge for it. Consultations produce
`consultation__billing_lines` — a draft, mutable trace of what was performed and
prescribed — and the daily board sums them into an "à encaisser" amount, but that
amount is computed on the fly and nothing records that money changed hands. The
"Encaisser" button closes the admission and records no payment, no invoice, no
number, no VAT breakdown. There is no invoice list, no receipt to hand to the
client, and no way to answer "what did we take in today?".

Consultation billing lines cannot themselves become the invoice: they belong to
the medical record, they change whenever the practitioner edits the plan, and
they carry no tax, no number and no payer. An invoice is the opposite — frozen
at issue, legally numbered, and owned by the person who pays, not by the animal.

### Solution

A new **Billing** bounded context owning the `Invoice` aggregate: draft lines
snapshotted from the visit, taxes resolved through the Taxation BC at snapshot
time, a per-clinic sequential number assigned on issue, and payments recorded
against it until the balance reaches zero. The daily board's "Encaisser" opens a
checkout screen backed by that aggregate; paying it in full closes the admission
through a port, exactly as the consultation closure already sends the patient to
the desk.

### Scope

**In scope**

- `Invoice` aggregate: draft → issued → partially paid → paid, plus cancelled.
- Lines snapshotted from every consultation of an admission, with per-line net,
  tax rate, tax amount and gross frozen at snapshot time.
- Free (manual) lines and a global discount on a draft.
- Payments: cash, card, transfer, cheque, insurance, other — partial payments
  allowed, overpayment refused.
- Per-clinic, per-year sequential numbering `FAC-2026-000042`, gap-free.
- Checkout flow from the daily board: draft → issue → pay → close the admission.
- Invoice list page with filters and totals, invoice detail page, print view.
- Demo fixtures covering every status in both clinics.

**Out of scope (stated, not silently dropped)**

- Credit notes and refunds. Cancelling an unpaid invoice is supported; reversing
  a paid one needs a credit-note aggregate and its own numbering sequence.
- Insurance third-party billing, instalment plans, dunning/reminders.
- Accounting export (FEC), cash-drawer sessions, card-terminal integration.
- Multi-currency: the invoice keeps the currency of its lines and refuses to mix.

## Domain model

### Aggregate: Invoice

| Field | Type | Notes |
|---|---|---|
| `id` | `InvoiceId` | uuid v7 |
| `clinicId` | `ClinicId` | tenant scope |
| `number` | `?InvoiceNumber` | null until issued |
| `status` | `InvoiceStatus` | DRAFT, ISSUED, PARTIALLY_PAID, PAID, CANCELLED |
| `admissionId` | `?AdmissionId` | the visit it charges, null for a standalone invoice |
| `payer` | `Payer` | snapshot: client id (nullable), display name, address lines |
| `lines` | `list<InvoiceLine>` | frozen amounts |
| `payments` | `list<Payment>` | append-only |
| `discount` | `?Discount` | percentage or fixed, applied to the net total |
| `currency` | `CurrencyCode` | every line shares it |
| `notes` | `?string` | free text printed on the invoice |
| `issuedAt`, `paidAt`, `cancelledAt` | `?DateTimeImmutable` | |

**Invariants**

- Lines and discount change only while DRAFT.
- Issuing requires at least one line and a non-negative gross total.
- A payment is refused unless the invoice is ISSUED or PARTIALLY_PAID.
- A payment is refused when it exceeds the balance due.
- A payment must be positive and share the invoice currency.
- The status follows the balance: zero → PAID, otherwise PARTIALLY_PAID.
- Cancelling is refused once a payment exists, and on an already cancelled one.
- A line's currency must match the invoice's.

**Events**: `InvoiceDrafted`, `InvoiceIssued`, `PaymentRecorded`, `InvoicePaid`,
`InvoiceCancelled`.

### Value objects

`InvoiceId`, `ClinicId`, `AdmissionId`, `PayerId`, `InvoiceLineId`, `PaymentId`,
`InvoiceNumber` (`FAC-YYYY-NNNNNN`), `InvoiceStatus`, `PaymentMethod`,
`InvoiceLine`, `Payment`, `Payer`, `Discount`, `DiscountKind`.

`InvoiceLine` holds label, optional catalog code, quantity (decimal string),
unit net price, tax category code, applied rate percent, and the three resolved
amounts (net, tax, gross). Nothing is recomputed after the snapshot: the price
list, the VAT rate or the catalog may change tomorrow, the invoice may not.

## Cross-BC contracts

| Port (in Billing) | Adapter | Talks to |
|---|---|---|
| `ConsultationBillingProviderInterface` | `QueryBusConsultationBillingProvider` | Consultation — `ListBillableLinesForAdmission` |
| `TaxResolverInterface` | `QueryBusTaxResolver` | Taxation — `ResolveTax` + Clinic `GetClinic` for the regime |
| `PayerDirectoryInterface` | `DbalPayerDirectory` | Admission/Patient/Animal/Client — resolves the visit's owner |
| `AdmissionCoordinatorInterface` | `MessengerAdmissionCoordinator` | Admission — `CloseAdmission` once fully paid |

Consultation gains one read query, `ListBillableLinesForAdmission`, returning the
billing lines of every consultation of a visit. Nothing else in Consultation
changes; Billing never touches its tables directly.

## Persistence

- `billing__invoices` — one row per invoice, `UNIQUE(clinic_id, number)`,
  indexes on `(clinic_id, status)`, `(clinic_id, issued_at)`, `(admission_id)`.
- `billing__invoice_lines` — ordered by `position`, cascade with the invoice.
- `billing__payments` — append-only, indexed by `(clinic_id, received_at)`.
- `billing__number_sequences` — `(clinic_id, year)` → `last_value`, incremented
  under `SELECT … FOR UPDATE` so two simultaneous checkouts cannot collide.

## Presentation

| Route | Name | Purpose |
|---|---|---|
| `GET /invoices` | `clinic_invoices` | list: filters (status, period, search), totals header |
| `GET /invoices/{invoiceId}` | `clinic_invoice_details` | detail: lines, VAT breakdown, payments, actions |
| `GET /invoices/{invoiceId}/print` | `clinic_invoice_print` | printable A4 view |
| `POST /invoices/{invoiceId}/payment` | `clinic_invoice_pay` | record a payment |
| `POST /invoices/{invoiceId}/cancel` | `clinic_invoice_cancel` | cancel an unpaid invoice |
| `GET /checkout/{admissionId}` | `clinic_checkout` | checkout screen for a visit |
| `POST /checkout/{admissionId}` | `clinic_checkout_submit` | issue + pay + close the admission |

The board's "Encaisser" button becomes a link to `clinic_checkout` instead of
posting straight to `clinic_admission_close`.

## Test strategy

- Unit: every aggregate transition and every guard, every handler with mocks,
  every value object's validation. Exact exception messages asserted.
- Integration: the Doctrine repository round-trip, the number generator under a
  second call, the DBAL payer directory against Foundry fixtures.
- 100% line coverage on `src/Context/Billing`.
