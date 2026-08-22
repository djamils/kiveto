---
title: 'Prescription Bounded Context — the ordonnance as a document'
slug: 'context-prescription-bc'
created: '2026-08-22'
status: 'complete — 2026-08-22, branch feature/prescription-bc'
stepsCompleted: [1, 2, 3, 4, 5, 6]
tech_stack:
  - 'Symfony 7 + Doctrine ORM (DDD monolith, BCs under src/Context)'
  - 'Twig + importmap (page modules via the data-page dispatcher in assets/app.js)'
  - 'Tailwind CSS v4 + hand-written page CSS (assets/styles/pages/**)'
  - 'PHPUnit (unit: mocks; integration: KernelTestCase + Foundry + dama), PHPStan level max'
files_to_modify:
  - 'src/Context/Prescription/** (new bounded context)'
  - 'src/Context/Consultation/Application/Query/ListPrescriptionLinesForConsultation/** (new read query)'
  - 'src/Presentation/Clinic/Controller/Prescription/** (new single-action controllers)'
  - 'templates/clinic/prescription/** (list, detail, print)'
  - 'templates/clinic/consultation/detail/index.html.twig (link out of the cockpit)'
  - 'templates/components/layout/sidebar.html.twig (Ordonnances entry)'
  - 'assets/styles/pages/prescription.css + assets/js/pages/prescription/list.js'
  - 'migrations/Prescription/** (new tables)'
  - 'config/packages/doctrine.yaml, config/packages/doctrine_migrations.yaml, config/services.yaml'
  - 'fixtures/Context/Prescription/** + fixtures/Dataset/ClinicDataset.php'
code_patterns:
  - 'Mirrors the Billing BC: aggregate + snapshot lines + per-clinic numbering + print sheet'
  - 'Aggregate root extends AggregateRoot, private ctor + create()/reconstitute() named constructors'
  - 'Commands: scalar readonly DTOs + #[AsMessageHandler] handlers'
  - 'Cross-BC: Application ports + Dbal/QueryBus adapters in Infrastructure/Adapter/<BC>/'
  - 'Table names come from BoundedContextPrefixNamingStrategy: prescription__*'
test_patterns:
  - 'tests/Unit mirrors src/, handler tests with PHPUnit mocks (exact exception messages)'
  - 'tests/Integration: KernelTestCase + Foundry factories + dama isolation'
  - '100% line coverage on src/Context/Prescription (Presentation excluded)'
---

# Tech-Spec: Prescription Bounded Context

**Created:** 2026-08-22

## Overview

### Problem Statement

The cockpit has an "Ordonnance" panel and a print button, but there is no
ordonnance. The panel holds `consultation__prescription_lines` — rows on the
medical record — and "Imprimer" sets `body[data-print-mode="ordonnance"]` and
calls `window.print()`, which hides a few blocks of the consultation page and
prints the rest. Nothing carries a number, a prescriber, an ordinal
registration number, a validity date or a renewal count, and nothing can be
found again once the consultation is closed.

That is not a document a client can take to a pharmacy, and not something the
clinic can produce five years later when asked. A prescription is a legal act
signed by a named practitioner: it identifies the prescriber and their ordinal
number, the owner, the animal, each medicine with its form and its posology,
how long it is valid and whether it can be renewed. Like an invoice, it is
frozen at issue — the catalog, the price and the plan may all move afterwards,
the prescription may not.

### Solution

A **Prescription** bounded context owning the `Prescription` aggregate, built on
the same spine as Billing: lines snapshotted from the consultation, a per-clinic
per-year number assigned on issue, and a printable A4 sheet in the French
format. The prescriber, the owner and the animal are snapshotted too, so the
document still prints what it printed the day it was signed.

### Scope

**In scope**

- `Prescription` aggregate: draft → issued, plus cancelled; expiry derived from
  the validity date rather than stored.
- Lines snapshotted from the consultation's prescription panel, enriched with
  the pharmaceutical form, the active substances and the controlled-substance
  class from the PharmaceuticalRegistry.
- Prescriber snapshot: display name, ordinal registration number, title. A
  practitioner without a registration number cannot sign.
- Patient and owner snapshots.
- Validity: a default of 12 months, capped at 28 days as soon as one line is a
  controlled substance, and renewals forbidden on those.
- Renewals: an allowance set on the draft, consumed one at a time, each one
  pushing the validity date out.
- Cancellation with a reason, at any point after issue.
- Per-clinic, per-year numbering `ORD-2026-000042`, gap-free.
- List page with filters, detail page, printable sheet, and the way into it from
  the consultation cockpit.
- Demo fixtures covering issued, renewed, controlled and cancelled.

**Out of scope (stated, not silently dropped)**

- Electronic signature and transmission to a pharmacy or a national
  e-prescription registry. The sheet is printed and signed by hand.
- Withdrawal periods ("temps d'attente"). They only apply to food-producing
  animals, and the Animal BC models companion species only (dog/cat/nac/other).
  The registry already exposes them, so the line VO leaves room for it.
- Dispensing and stock movements: handing the box over belongs to Inventory.
- Automatic dose computation from the animal's weight.

## Domain model

### Aggregate: Prescription

| Field | Type | Notes |
|---|---|---|
| `id` | `PrescriptionId` | uuid v7 |
| `clinicId` | `ClinicId` | tenant scope |
| `number` | `?PrescriptionNumber` | null until issued |
| `status` | `PrescriptionStatus` | DRAFT, ISSUED, CANCELLED |
| `consultationId` | `ConsultationId` | the act it comes from |
| `prescriber` | `Prescriber` | name, ordinal number, title — snapshotted |
| `patient` | `PatientSnapshot` | name, species, breed, birth date, weight, chip |
| `owner` | `OwnerSnapshot` | name and address, nullable client id |
| `lines` | `list<PrescriptionLine>` | frozen |
| `validity` | `?Validity` | issued date + valid-until, set at issue |
| `renewals` | `Renewals` | allowed, used |
| `notes` | `?string` | printed under the lines |

**Invariants**

- Lines, renewal allowance and notes change only while DRAFT.
- Issuing requires at least one line and a prescriber holding a registration
  number.
- A prescription carrying a controlled substance is valid 28 days at most and
  may not be renewed; setting a renewal allowance on such a draft is refused.
- Renewing requires an issued, non-cancelled, non-expired prescription with a
  remaining allowance; each renewal extends the validity by the original span.
- Cancelling is refused on an already cancelled prescription and needs a reason.
- Expiry is derived from `validUntil` against the clock, never stored: a
  prescription does not change state on its own.

**Events**: `PrescriptionDrafted`, `PrescriptionIssued`, `PrescriptionRenewed`,
`PrescriptionCancelled`.

## Cross-BC contracts

| Port (in Prescription) | Adapter | Talks to |
|---|---|---|
| `ConsultationPrescriptionProviderInterface` | `QueryBusConsultationPrescriptionProvider` | Consultation — `ListPrescriptionLinesForConsultation` |
| `PrescriberDirectoryInterface` | `DbalPrescriberDirectory` | Clinic — staff profile of the practitioner |
| `PatientDirectoryInterface` | `DbalPatientDirectory` | Patient/Animal/Client — animal and owner of a consultation |
| `DrugRegistryInterface` | `QueryBusDrugRegistry` | Catalog + PharmaceuticalRegistry — form, substances, controlled class |

Consultation gains one read query, `ListPrescriptionLinesForConsultation`.
Nothing else in Consultation changes.

## Persistence

- `prescription__prescriptions` — `UNIQUE(clinic_id, number)`, indexes on
  `(clinic_id, status)`, `(clinic_id, issued_at)`, `(consultation_id)`.
- `prescription__prescription_lines` — ordered by `position`, cascade with the
  prescription.
- `prescription__renewals` — one row per renewal, append-only.
- `prescription__number_sequences` — `(clinic_id, year)` → `last_value`, read
  back under `FOR UPDATE`.

## Presentation

| Route | Name | Purpose |
|---|---|---|
| `GET /prescriptions` | `clinic_prescriptions` | list: filters (status, period, search), counters |
| `GET /prescriptions/{prescriptionId}` | `clinic_prescription_details` | detail: lines, validity, renewals, actions |
| `GET /prescriptions/{prescriptionId}/print` | `clinic_prescription_print` | printable A4 sheet |
| `POST /prescriptions/{prescriptionId}/renew` | `clinic_prescription_renew` | consume one renewal |
| `POST /prescriptions/{prescriptionId}/cancel` | `clinic_prescription_cancel` | cancel with a reason |
| `POST /prescriptions/{prescriptionId}/issue` | `clinic_prescription_issue` | sign it: renewals, note, number |
| `GET /consultations/{consultationId}/prescription` | `clinic_prescription_prepare` | draft it from the cockpit and open it |

## Test strategy

- Unit: every transition and guard, every handler with mocks, every value
  object's validation. Exact exception messages asserted.
- Integration: the Doctrine repository round-trip, the number generator, the
  two DBAL directories against Foundry fixtures.
- 100% line coverage on `src/Context/Prescription`.
