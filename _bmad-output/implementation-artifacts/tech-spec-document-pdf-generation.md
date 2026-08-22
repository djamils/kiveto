---
title: 'Client-facing PDF documents rendered by Gotenberg'
slug: 'document-pdf-generation'
created: '2026-08-22'
status: 'complete — 2026-08-22, branch feature/document-pdf-generation'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - 'Symfony 7.4 + Twig (documents are server-rendered HTML, converted to PDF)'
  - 'Gotenberg 8 (Chromium) as a sidecar container on the compose network'
  - 'symfony/http-client + symfony/mime for the multipart call'
  - 'Doctrine ORM (clinic letterhead columns), PHPStan level max, PHPUnit'
files_to_modify:
  - 'compose.yaml, .env, .env.test, config/services.yaml (Gotenberg service and base URL)'
  - 'src/Shared/Application/Pdf/** + src/Shared/Infrastructure/Pdf/** (port and adapter)'
  - 'src/Context/Clinic/** (letterhead value object, aggregate method, event, command, entity, DTO)'
  - 'migrations/Clinic/** (letterhead columns on clinic__clinics)'
  - 'src/Context/Prescription/** + src/Context/Billing/** (two read queries for the bundle)'
  - 'templates/clinic/document/** (the three documents and their shared shell)'
  - 'src/Presentation/Clinic/Controller/Document/** (four single-action controllers)'
  - 'assets/js/pages/consultation.js (the print modal opens real documents)'
  - 'fixtures/Context/Clinic/** (letterhead demo data)'
code_patterns:
  - 'Ports live in Application, adapters in Infrastructure — mirrors Shared/Infrastructure/Storage'
  - 'Aggregate mutators record a domain event; commands are readonly DTOs with #[AsMessageHandler] handlers'
  - 'Read models are DBAL queries returning readonly DTOs (DbalInvoiceReadRepository as reference)'
  - 'One __invoke() per controller'
test_patterns:
  - 'Unit: MockHttpClient for the Gotenberg adapter, plain PHPUnit for the value object and the aggregate'
  - 'Integration: KernelTestCase + Foundry for the two new read queries'
  - '100% line coverage on src/Shared and src/Context/** (Presentation excluded)'
---

# Tech-Spec: Client-facing PDF documents rendered by Gotenberg

**Created:** 2026-08-22

## Overview

### Problem Statement

Three documents leave the clinic with the client: the consultation report, the
invoice and the prescription. Today none of them is a document. The invoice and
the prescription have an HTML "print" page with a toolbar and a `window.print()`
button, and the consultation cockpit has a print modal that sets
`body[data-print-mode]` and lets the browser print the screen. What comes out
depends on the browser, on the user's print dialog, on their margins and on
their "print backgrounds" setting. Nothing can be attached to an email, filed in
the animal's record, or handed to an insurer.

Three A4 layouts now exist under `docs/frontend-theme/vetsaas-layouts/`
(`vetos-doc-compte-rendu.html`, `vetos-doc-facture.html`,
`vetos-doc-ordonnance.html`). They share a letterhead, a two-column identity
block, section rules, data tables, boxed call-outs, a signature block, legal
mentions and a repeated page footer — and they expect clinic data the
application does not hold: postal address, phone, e-mail, SIRET, VAT number,
RCS, APE code and the establishment's ordinal registration number.

### Solution

A Gotenberg 8 sidecar converts server-rendered HTML into a PDF. A
`PdfRendererInterface` in the shared kernel exposes two operations — render one
HTML document, merge several PDFs — and a Gotenberg adapter implements it. The
three layouts become Twig templates fed by the existing read models, sharing one
shell that carries the stylesheet, the letterhead and the footer. Four routes
stream the results: report, invoice, prescription, and a merged bundle of all
three for one consultation. The Clinic aggregate gains a `Letterhead` value
object so the header prints real, per-clinic legal identity.

### Scope

**In scope**

- `gotenberg` service on the compose network, `GOTENBERG_URL` env var.
- `PdfRendererInterface` (`render`, `merge`) + `GotenbergPdfRenderer`, with the
  failure modes mapped to one `PdfRenderingFailedException`.
- `Letterhead` value object on the Clinic aggregate: legal name and form, share
  capital, SIRET, VAT number, RCS city and number, APE code, ordinal number,
  postal address, phone, e-mail, website. Domain event, command, handler,
  Doctrine embeddable, migration, DTO exposure and demo fixtures for both
  clinics.
- Three documents under `templates/clinic/document/`, faithful to the layouts,
  driven by `ConsultationDetailsDTO`, `InvoiceDetails` and `PrescriptionDetails`.
- Four routes, each a single-action controller, streaming `application/pdf`
  inline with the document's own file name.
- Two read queries so the bundle knows what exists:
  `ListPrescriptionsForConsultation` (Prescription) and `FindInvoiceForAdmission`
  (Billing).
- The cockpit's "Imprimer / Télécharger" modal opens the real documents instead
  of calling `window.print()`; the old `/print` HTML pages and their page CSS go.

**Out of scope (stated, not silently dropped)**

- A settings screen to edit the letterhead. The command and the handler exist and
  are covered; the demo data comes from fixtures. Nothing in the UI writes it yet.
- A consultation number. The layout shows `CR-2026-004182`; consultations carry
  no human-readable number and inventing one at print time would produce a
  different reference on every render. The report is identified by its date, its
  motif and its practitioner until Consultation gets a real numbering sequence.
- The QR code of the prescription layout. It is a verification code, and there is
  no verification endpoint to point it at.
- The laboratory results table of the report layout (parameter, result, reference
  range, interpretation). There is no laboratory module; the structured data of
  the examined systems is printed instead.
- Storing the generated files. Documents are rendered on demand; nothing is
  written to `var/share`.
- Insurance blocks (contract number, reimbursement estimate) — no insurance data
  is modelled.

## Context for development

### Codebase patterns

| Concern | Reference |
| --- | --- |
| Port + adapter in the shared kernel | `src/Shared/Infrastructure/Storage/LocalFileSystemStorage.php` |
| Value object with `create()` + `validate()` | `src/Shared/Domain/ValueObject/PostalAddress.php` |
| Doctrine embeddable for a value object | `src/Context/Client/Infrastructure/Persistence/Doctrine/Embeddable/PostalAddressEmbeddable.php` |
| Aggregate mutator recording an event | `App\Context\Clinic\Domain\Clinic::rename()` |
| Command + `#[AsMessageHandler]` | `src/Context/Clinic/Application/Command/Clinic/RenameClinic/` |
| DBAL read query returning a readonly DTO | `src/Context/Billing/Infrastructure/Persistence/Doctrine/Repository/DbalInvoiceReadRepository.php` |
| Single-action controller reading the current clinic | `src/Presentation/Clinic/Controller/Billing/PrintInvoiceController.php` |

### Data already available

| Document | Source | Notes |
| --- | --- | --- |
| Report | `ConsultationDetailsDTO` | motifs, `subjectiveText`, `typedVitals`, `examSystems`, `objectiveObservations`, `diagnoses`, `planActions`, `prescriptionLines`, `summary` |
| Report — patient and owner | `CockpitPatientBuilder` | already used by the cockpit; returns species, breed, sex, birth date, microchip, owner name, address, phone, e-mail |
| Invoice | `InvoiceDetails` | lines with tax rate and category, tax breakdown, payments, discount, balance |
| Prescription | `PrescriptionDetails` | prescriber and ordinal number, patient and owner snapshots, lines with form, substances, posology, controlled class, validity, renewals |
| Letterhead | **missing** | added by this spec |

### Technical decisions

1. **Gotenberg over a PHP renderer.** The layouts use `@page`, `position: fixed`
   footers, `mm` units and CSS the PHP renderers do not support. Chromium prints
   them exactly as the browser shows them.
2. **The port takes HTML, not a template name.** Rendering stays in the
   Presentation layer where Twig lives; the shared kernel only knows bytes in,
   bytes out. That keeps `Shared` free of any knowledge about documents.
3. **Margins are passed to Gotenberg, not left to CSS.** `preferCssPageSize` is
   off and the four margins are explicit, so the same HTML renders identically
   whatever the `@page` rule says. The repeated footer is Gotenberg's
   `footer.html`, which is the only way to get real page numbers.
4. **The letterhead lives on the Clinic aggregate**, not in a config file: it is
   per-clinic data, it changes over time, and a group can hold several clinics.
   All its fields are optional — a clinic that has not filled it in still prints,
   with the blocks it cannot fill left out.
5. **`/print` is replaced, not doubled.** Two ways to produce the same document
   would drift apart. The HTML print pages, their controllers and their page CSS
   are removed in the same change.

## Implementation plan

### Tasks

1. **Infrastructure** — add the `gotenberg` service (image `gotenberg/gotenberg:8`)
   to `compose.yaml` on `kiveto-network`; add `GOTENBERG_URL=http://gotenberg:3000`
   to `.env` and `.env.test`; bind the parameter in `config/services.yaml`.
2. **Shared port** — `PdfRenderRequest` (html, footer html, four margins, print
   background), `PdfRendererInterface::render()` and `::merge()`,
   `PdfRenderingFailedException`.
3. **Gotenberg adapter** — `GotenbergPdfRenderer` posting a `FormDataPart` to
   `/forms/chromium/convert/html` and `/forms/pdfengines/merge`; every transport
   or HTTP failure becomes a `PdfRenderingFailedException` carrying the status.
4. **Letterhead value object** — `Letterhead::create(...)`, `isEmpty()`,
   `equals()`, trimming empty strings to null, e-mail and country validated.
5. **Clinic aggregate** — `updateLetterhead()` recording `ClinicLetterheadUpdated`;
   `getLetterhead()`; `Clinic::create()` starts with an empty letterhead.
6. **Persistence** — `LetterheadEmbeddable` on `ClinicEntity`, migration adding
   the nullable `letterhead_*` columns to `clinic__clinics`.
7. **Command** — `UpdateClinicLetterhead` + handler.
8. **Read side** — `LetterheadDto` exposed by `ClinicDto` (nullable, last
   constructor argument so existing callers keep working).
9. **Fixtures** — fill the letterhead of both demo clinics.
10. **Read queries** — `ListPrescriptionsForConsultation` (Prescription) and
    `FindInvoiceForAdmission` (Billing), each a query, a handler, a port method
    and a DBAL implementation.
11. **Document templates** — `templates/clinic/document/_shell.html.twig`
    (doctype, stylesheet, letterhead, identity blocks), `_footer.html.twig`
    (Gotenberg footer), then `report.html.twig`, `invoice.html.twig`,
    `prescription.html.twig`.
12. **Controllers** — `ConsultationReportPdfController`, `InvoicePdfController`,
    `PrescriptionPdfController`, `ConsultationBundlePdfController`.
13. **UI** — repoint the invoice list and detail, the prescription list and
    detail, and the cockpit print modal; delete the old print pages, controllers,
    templates and CSS.
14. **Tests** — unit tests for the renderer, the value object, the aggregate and
    the handler; integration tests for the two read queries.

### Acceptance criteria

- **Given** a closed consultation with motifs, vitals, exam systems, diagnoses and
  a plan, **when** its report route is opened, **then** a PDF is streamed inline
  whose first page carries the clinic letterhead, the owner and animal blocks and
  the SOAP sections, and whose every page carries the footer with `n / total`.
- **Given** an issued invoice, **when** its PDF route is opened, **then** the
  lines, the VAT recap, the payments and the totals match the invoice page to the
  cent, and a paid invoice shows the "Acquittée" stamp.
- **Given** an issued prescription carrying a controlled substance, **when** its
  PDF route is opened, **then** each medicine prints its form, substances,
  posology, route, duration and renewal rule, and the regulatory mentions block
  states the controlled substance and the 28-day validity.
- **Given** a consultation with a prescription and an invoice, **when** the bundle
  route is opened, **then** one PDF comes back containing the report, then the
  prescription, then the invoice.
- **Given** a clinic with no letterhead, **when** any document is opened, **then**
  it still renders, with the clinic name alone in the header.
- **Given** Gotenberg is unreachable, **when** a document is opened, **then** the
  request fails with a 502 whose message says the printing service is
  unavailable, the renderer's own error being kept as the previous exception.
- **Given** `make ci`, **then** it is green, and `src/Shared` and every touched
  bounded context stay at 100% line coverage.

## Additional context

### Dependencies

- `gotenberg/gotenberg:8` image, pulled on `make up`.
- No new composer package: `symfony/http-client` and `symfony/mime` are already
  installed.

### Testing strategy

- **Unit** — `GotenbergPdfRenderer` against `MockHttpClient`: a successful
  render, a 4xx, a 5xx, a transport exception, and a merge. `Letterhead`
  validation and normalisation. `Clinic::updateLetterhead()` and its event.
  `UpdateClinicLetterheadHandler` with a mocked repository.
- **Integration** — the two new read queries against Foundry fixtures.
- **Manual** — each document opened in the browser against the demo fixtures and
  checked page by page.

### Notes

The three layouts share one stylesheet, byte for byte. It is copied once into
`_shell.html.twig` rather than into each document, and the document-specific
rules (`.rx`, `.totals`, `.vitals`) stay in it too — they are small, and keeping
one stylesheet means the three documents cannot drift apart.

## Outcome

Delivered on `feature/document-pdf-generation`. Verified against the demo
fixtures, page by page, on rasterised output of the real PDFs:

- ordonnance, facture and compte rendu render with the clinic letterhead, the
  repeated footer and its `n / total` page numbers;
- the bundle merges report + ordonnance + facture for a visit that produced all
  three (the Billing story now creates one such visit per clinic);
- with Gotenberg stopped, the four routes answer 502.

Two deviations from the layouts, both noted above and both data-driven: the
report carries no `CR-…` number (consultations have no numbering sequence), and
the prescription has no QR code (nothing to verify against yet). The laboratory
results table and the insurance blocks are not printed either — no module holds
that data.
