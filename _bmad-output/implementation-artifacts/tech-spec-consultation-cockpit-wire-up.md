---
title: 'Consultation Cockpit — Wire Up All Functionality'
slug: 'consultation-cockpit-wire-up'
created: '2026-08-20'
status: 'in-progress — Phase 0 (Tasks 1-5) complete 2026-08-20, branch feature/consultation-cockpit-phase-0'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - 'Symfony 7 + Doctrine ORM (DDD monolith, BCs under src/Context)'
  - 'Twig + importmap (page modules via data-page dispatcher in assets/app.js)'
  - 'Vanilla JS page modules (assets/js/pages/consultation.js) + Stimulus controllers'
  - 'Tailwind CSS v4 + hand-written page CSS (assets/styles/pages/consultation.css)'
  - 'PHPUnit (unit: mocks; integration: KernelTestCase + Foundry + dama), PHPStan level max'
files_to_modify:
  - 'src/Context/Consultation/Domain/** (aggregate + new VOs + events)'
  - 'src/Context/Consultation/Application/** (new commands/queries + DTO extension)'
  - 'src/Context/Consultation/Infrastructure/** (entities, mappers, repos, ports/adapters)'
  - 'src/Context/Animal/** (minimal medical alerts model)'
  - 'src/Context/Catalog/Application/Service/PriceResolver.php (bug fix)'
  - 'src/Presentation/Clinic/Controller/Consultation/** (new single-action controllers)'
  - 'templates/clinic/consultation/detail/*.twig (bind real data, JSON hydration)'
  - 'assets/js/pages/consultation.js (restructure + wire to endpoints)'
  - 'fixtures/** (wire Catalog/Pharma stories into datasets + enrich)'
  - 'migrations/Consultation/**, migrations/Animal/** (new tables/columns)'
  - 'config/services.yaml (port wiring, dev + test blocks)'
code_patterns:
  - 'Aggregate child collections as readonly VO arrays with create()/reconstitute() pairs'
  - 'Commands: scalar readonly DTOs + #[AsMessageHandler] handlers, ensureOpen() guard'
  - 'Cross-BC: Application ports + Dbal/Messenger adapters in Infrastructure/Adapter/<BC>/'
  - 'AJAX: fetch + URLSearchParams POST → JSON {success, errors, errorCode}, CSRF token from DOM'
  - 'Server→JS hydration: <script type="application/json" id="..."> parsed at init (agenda pattern)'
test_patterns:
  - 'tests/Unit mirrors src/, handler tests with PHPUnit mocks (exact exception messages)'
  - 'tests/Integration: KernelTestCase + Foundry factories (fixtures/Context/*/Factory) + dama isolation'
  - '100% line coverage per BC excl. Presentation, enforced by review'
---

# Tech-Spec: Consultation Cockpit — Wire Up All Functionality

**Created:** 2026-08-20

## Overview

### Problem Statement

The consultation detail page (`clinic_consultation_details`, "cockpit" layout) has a finished visual layout but every interaction is mocked client-side: `assets/js/pages/consultation.js` (3365 lines, five concatenated IIFEs ported from four separate HTML prototypes) contains zero `fetch` calls, and the Twig templates never read the `ConsultationDetailsDTO` the controller passes — every visible datum is hardcoded ("Luna · Lambert"). The backend Consultation BC only supports chief complaint, weight/temperature vitals, append-only clinical notes, performed acts, and close.

### Solution

Extend the Consultation BC domain model (new VOs/child entities + Doctrine migrations) to cover every cockpit block, wire the page JS to real endpoints (fetch+JSON, single-action controllers, command/query buses), source medications/acts/prices from the Catalog BC through Application ports, and add a minimal medical-alerts model to the Animal BC for the allergy banner. Everything on the page becomes functional EXCEPT AI-related features, which stay mocked.

### Scope

**In Scope (everything on the page must work):**

- **Motif strip**: add/remove multiple consultation motifs (persisted).
- **Constantes strip**: weight, temperature, and additional typed vitals (FC, FR, TRC, score douleur, muqueuses…) — add/edit, persisted.
- **SOAP S (Subjectif)**: free-text persisted (the "Dicter" button = AI → stays mock).
- **SOAP O (Objectif)**: per-system clinical exam grid (RAS / anomalie / non testé per system, species template), structured drill-downs, "Tout RAS" shortcut, detailed observations text — persisted. **Media thumbnails removed from the quad** (dedicated upload project later).
- **SOAP A (Diagnostic)**: differential diagnosis list with certainty (CERTAIN/PROBABLE/POSSIBLE/EXCLUDED), add/edit/remove, primary selection, source tracking (MANUAL vs AI_SUGGESTION) — persisted. "Suggérer (IA)" button/modal = AI → stays mock (accepted suggestions persist like manual ones with AI source).
- **SOAP P (Plan)**: typed plan actions (PERFORMED_ACT, MEDICATION_PRESCRIPTION, FOLLOW_UP_APPOINTMENT, ADVICE, OTHER), add/edit/remove, templates — persisted.
- **Ordonnance panel**: medication search backed by Catalog BC Articles (via port), posology fields, add/remove lines — persisted with price snapshot. Allergy incompatibility warning driven by real Animal medical alerts.
- **Facturation panel**: lines derived from plan acts + ordonnance, persisted with snapshotted Catalog prices, running total (HT/TVA/TTC via real tax data, not hardcoded 20%), draft status. No Invoice entity / Billing BC.
- **Mémo équipe**: persisted note.
- **Patient bar**: real patient/animal/owner data (`GetAnimalById` + `GetClientById`), real allergy/chronic-condition badges (new Animal BC medical alerts), profil animal / profil client modals with real data. The "⋯" menu (SMS, email, tag, archiver, transfert) and owner-modal solde/préférences **stay as visible mocks with an "à venir" toast** (user decision).
- **Topbar**: live consultation timer computed from `startedAtUtc`; save-state indicator reflecting real persistence; Historique modal listing the patient's real past consultations (new query); Imprimer modal → print-ready output (browser print CSS) of ordonnance/compte-rendu.
- **Footer**: "Clôturer la consultation" = existing CloseConsultation flow; "Pause" / "Plus tard" = navigation back to admission queue, consultation stays OPEN.
- **Closed consultations**: the cockpit renders in strict read-only mode (banner "Consultation clôturée", all mutation controls hidden/disabled). No reopening.
- **Animal BC — minimal medical alerts**: allergies + chronic conditions on the animal (simple model + CRUD + fixtures), consumed by the cockpit banner and the ordonnance warning.
- **Demo fixtures**: wire the existing (currently orphaned, never-loaded) Catalog + PharmaceuticalRegistry + Taxation stories into the dev dataset; enrich them (price-list items, drug→authorization links, GTINs, more medications) to exercise search, pricing, and UI thresholds per CLAUDE.md §7.

**Out of Scope:**

- Anything AI: "Dicter" (SOAP S), "Suggérer (IA)" modal content — stay mocked as-is.
- Invoice/Billing bounded context or real invoice generation.
- New PAUSED consultation status.
- Reopening a closed consultation (future pattern would be an addendum note, not reopening).
- Exam media upload (removed from UI for now).
- Real SMS/email sending, client balance, contact preferences (menu items stay mock with toast).
- Per-clinic timezone handling beyond existing project defaults.

## Context for Development

### Codebase Patterns

**Consultation BC (the extension target)**

- Aggregate `src/Context/Consultation/Domain/Consultation.php` (360 lines): private ctor, named constructors `startFromAppointment`/`startFromAdmission`, positional `reconstitute()` (every new field must be threaded there), mutations all call `ensureOpen()` (throws `\DomainException('Cannot modify a closed consultation')`), set `updatedAtUtc`, emit one event, return void.
- Child collections = plain arrays of readonly VOs (`ClinicalNoteRecord`, `PerformedActRecord`) with `create()` (validates, generates `Uuid::v7()` id) / `reconstitute()` (no validation) pairs; foreign user ids stored as raw strings.
- `Vitals` VO only holds `?weightKg` + `?temperatureC` (weight > 0, temp 30–45, both-null rejected) — it stays **as-is**; additional vitals get their own `TypedVitalRecord` collection (Task 6). Do NOT refactor `Vitals`.
- Events: `final readonly` extending `AbstractDomainEvent`, `BOUNDED_CONTEXT='consultation'`, `VERSION=1`, scalar `payload()`. NOTE: Consultation handlers never publish events (`pullDomainEvents()` uncalled) — recorded and dropped; keep consistent.
- Commands: scalar readonly DTOs implementing `CommandInterface`; handlers `#[AsMessageHandler]` with `ConsultationRepositoryInterface` + `ClockInterface`, guard `'Consultation not found'` (exact string asserted in tests), then aggregate call + `save()`.
- Persistence: entities per table (`consultation__consultations`, `__clinical_notes`, `__performed_acts`), loose FKs (no ORM relations), binary(16) UUIDs, decimals as strings, `#[ORM\Version]` optimistic lock on the root. Mappers per entity; `DoctrineConsultationRepository::save()` = upsert root + **delete-all + re-insert children**. For updatable lines (prescription/billing), prefer the Procurement pattern instead: domain child entities + `OneToMany(cascade, orphanRemoval)` + line-by-id sync (`src/Context/Procurement/.../DoctrinePurchaseOrderRepository.php:47-73`).
- Read side: `DoctrineConsultationReadRepository` raw DBAL + `RowAccessor` helpers, scoped `id AND clinic_id`.
- Migrations: `migrations/Consultation/`, generated via `make consultation-migrations` (diff filtered on `^consultation__` — new tables MUST use that prefix).
- Existing ports (all in `Application/Port/`, adapters in `Infrastructure/Adapter/<BC>/`, wired explicitly in `config/services.yaml:341-364` + test block): Dbal context providers (Admission, Scheduling, Clinic eligibility) and Messenger service coordinators. Inbound: `PatientReconciledIntoHandler` bulk-updates `patient_id` — **any new child table carrying patient_id needs the same treatment** (none planned; children key on consultation_id).

**Catalog BC (medication/act/price source)**

- `Article` (kind DRUG/…, `Money $basePrice`, `DrugProperties{authorizationRef→PharmaceuticalRegistry, requiresPrescription, prescriptionClass, isControlledSubstance}`), `Act` (category, base price, duration), `PriceList` with `isDefault` invariant + items + rules.
- Queries: `SearchCatalogItems` (no price/pharma data in results), `GetArticleDetail`/`GetActDetail` (flat DTOs, price as `MinorUnits+Currency`), `ResolvePrice` → domain VO `ResolvedPrice`.
- ⚠️ **Known bug** `Application/Service/PriceResolver.php:39-42`: builds ClinicId from the price-list id and always resolves the default list, ignoring the requested one. Fix in scope (used by our pricing path).
- Port consumption pattern to imitate: Inventory's `ArticleProviderInterface` + `CatalogArticleProviderAdapter` (query-bus adapter mapping to consumer-owned DTOs), wired in `config/services.yaml` dev AND test blocks. Do NOT imitate Procurement's stub adapter.
- Money: `src/Shared/Money/.../Money.php` (int minor units + currency); DTO convention flattens to `...MinorUnits: int` + `...Currency: string`. **No Twig money filter exists** — create one following `PhoneFormatExtension` pattern. Catalog prices are net/HT; TVA requires dispatching Taxation's `GetEffectiveRateForUI`/`ResolveTax` (Catalog stores `TaxCategoryCode` per item).
- PharmaceuticalRegistry (System BC): ANMV data — commercial names, active substances, ATC vet codes, target species, withdrawal periods, controlled classes. No allergy/interaction data anywhere in the codebase.

**Patient/Animal/Client (banner data)**

- `GetAnimalById` → `AnimalView` covers name, species, breed, birth date, reproductiveStatus (sterilized), microchip, color, photo, owner ids. `GetClientById` → `ClientView` (names, contact methods, address). Presentation-layer composition via query bus is the dominant pattern (`ViewAnimalController`, `ViewClientController`).
- **Missing hop**: patientId → animalId. Patient BC has no read query for it (only animal→patient). Add a small Patient read port/query (mirroring `PatientReadRepositoryInterface` style) reading `patient__patients.animal_link_id`.
- **Past consultations**: nothing exists (`ListConsultationsController` is a render-only stub; read repo has only `findById`). Add `ListConsultationsForPatient` (patient_id + clinic_id, `started_at_utc DESC`; `idx_patient` exists). Caveat: reconciled patients — keying via `patient__patients.animal_link_id` is the more complete route (precedent: AdmissionQueueController last-weight query).
- **Allergies/chronic conditions: no model anywhere** — to be created minimally in Animal BC (user decision).

**Frontend (the wiring target)**

- `consultation.js` = 5 IIFEs that don't share state (each redeclares `$`, `state`, `openModal`…): shell (L14–1552), quad O (1559–2011), quad A (2018–2520), quad P (2527–3325), MutationObserver glue (3336–3348). `init()`/`cleanup()` exported; `cleanup()` leaks **five** document-level keydown listeners (~1540, ~1718, ~1994, ~2510, ~3269) and never disconnects the MutationObserver (~3336).
- Dead code to remove: IIFE 1's legacy quad wiring + modals (`openAddDiagnosticModal` 612–670, `openDxConfidenceModal`, legacy `openSuggestDiagnosticModal` 699–754, `openAddPlanActionModal`, `openPlanActionTextModal`, `openPlanTemplatesModal` — selectors absent from current Twig).
- Loading: `data-page` route dispatcher in `assets/app.js` + `importmap.php` (`pages/consultation`). CSS `assets/styles/pages/consultation.css`.
- Mock stores to replace with server data: `state` (patient), `MEDICATIONS`, `DIAGNOSTICS`, `VITAL_TYPES`, `PRESET_MOTIFS`, `PLAN_TEMPLATES`, `PAST_CONSULTATIONS`, quad-local `state`/`diagnoses`/`planItems`. Billing has NO data structure (DOM-scraped, VAT 20% hardcoded in JS `recomputeBillTotal` L276–285).
- No timer exists (started-at never read); save-state "Brouillon · 4 s" hardcoded in Twig + toast.
- **AJAX precedent to follow**: appointment pattern — `fetch` + `URLSearchParams(FormData)` POST, `{success, errors{field}, errorCode}` JSON responses, CSRF via hidden input `csrf_token('<intent>')` read from DOM (`CreateAppointmentController.php:37-43` server side). Hydration precedent: `<script type="application/json" id="agenda-data">` parsed at init (`agenda.js:1227`).
- Selector fragility to fix while wiring: buttons found by `textContent.includes(...)`, counts parsed from label text, brittle `nth-child` selectors — add ids/data-attributes as we go.
- Templates receive `consultation` (ConsultationDetailsDTO) but use none of it; DTO lacks diagnoses/plan/prescription/billing entirely — extend DTO + read repo, bind all partials.

**Fixtures**

- Catalog/Pharma/Taxation stories exist but are **never loaded** (`DevDataset` → `ClinicDataset` omits them). `CompanionClinicCatalogStory` has 4 acts / 3 articles / 2 packages / 2 price lists but zero price-list items, null `drugAuthRef`, no GTINs; Pharma stories create 16 authorizations. Factories exist for everything incl. `TaxonomyBootstrapStory` (tax codes).

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Consultation/Domain/Consultation.php` | Aggregate to extend (pattern: ensureOpen, reconstitute) |
| `src/Context/Consultation/Domain/ValueObject/{Vitals,ClinicalNoteRecord,PerformedActRecord}.php` | VO conventions for new child records |
| `src/Context/Consultation/Application/Command/RecordVitals/*` | Command+handler template |
| `src/Context/Consultation/Infrastructure/Persistence/Doctrine/{Entity,Mapper,Repository}/*` | Persistence pattern (delete+reinsert children) |
| `src/Context/Procurement/.../DoctrinePurchaseOrderRepository.php:47-73` | OneToMany line-sync pattern for updatable lines |
| `src/Context/Consultation/Application/Query/GetConsultationDetails/*` | DTO to extend |
| `src/Context/Inventory/.../Adapter/Catalog/CatalogArticleProviderAdapter.php` | Cross-BC port adapter to imitate |
| `src/Context/Catalog/Application/Service/PriceResolver.php:39-42` | Bug to fix (ignores requested price list) |
| `src/Presentation/Clinic/Controller/Scheduling/Appointment/CreateAppointmentController.php` | JSON+CSRF controller pattern |
| `assets/controllers/appointment_form_controller.js:327-372` | fetch pattern to follow |
| `templates/clinic/scheduling/agenda/index.html.twig:112` + `assets/js/pages/scheduling/agenda.js:1227` | JSON hydration pattern |
| `assets/js/pages/consultation.js` | Full mock map (see investigation) |
| `templates/clinic/consultation/detail/*.twig` | Static templates to bind |
| `src/Shared/Presentation/Twig/PhoneFormatExtension.php` | Pattern for new `money` Twig filter |
| `fixtures/Context/Catalog/Story/CompanionClinicCatalogStory.php` | Orphaned story to wire + enrich |
| `fixtures/DevDataset.php`, `fixtures/Dataset/ClinicDataset.php` | Where to load Catalog/Pharma/Taxation stories |
| `src/Context/Animal/Domain/Animal.php` | Aggregate receiving minimal medical alerts |
| `tests/Unit/Context/Consultation/**` | Test conventions (mocks, exact messages) |

### Technical Decisions

1. **Data model**: extend Consultation BC with dedicated VOs/child entities + migrations for motifs, typed vitals, per-system exam, diagnoses (certainty + primary + source), typed plan actions, prescription lines, billing draft lines, team memo. Use readonly-VO arrays for append-only children; use the Procurement OneToMany line-sync pattern for updatable lines (prescription, billing, diagnoses, plan).
2. **Medication source**: Catalog BC Articles via a Consultation-owned Application port (query-bus adapter, Inventory pattern); prices via Catalog `ResolvePrice` after fixing the PriceResolver bug.
3. **Price snapshot**: prescription and billing lines snapshot the resolved price (minor units + currency) at line-add time; later Catalog changes never alter existing lines. Business rule.
4. **Facturation**: derived lines persisted on the consultation (draft status, snapshotted prices, HT/TVA via Taxation query — no hardcoded 20%). No Invoice concept.
5. **Frontend interaction pattern (decided)**: fetch + `URLSearchParams` POST → JSON `{success, errors, errorCode}` (appointment pattern), ONE shared page-level CSRF intent `consultation_edit` rendered once in Twig (see Phase 2 controller conventions); mutations serialized client-side through a per-consultation FIFO queue with single 409 retry (optimistic-lock safety); initial state hydrated via `<script type="application/json" id="consultation-data">` (agenda pattern). No Turbo Frames for micro-interactions. During wiring, add stable ids/data-attributes and remove textContent-based selectors.
6. **JS restructure**: consolidate the 5 IIFEs' duplicated utilities where cheap, delete IIFE 1 dead quad code, fix `cleanup()` listener leaks, keep the page-module structure (no Stimulus rewrite).
7. **Closed = read-only**: `CLOSED` consultations render banner + disabled controls; server rejects mutations already (`ensureOpen`). No reopen command.
8. **Allergies**: minimal medical-alerts model in Animal BC (allergies + chronic conditions), CRUD + fixtures; consumed by banner + ordonnance warning via port/query composition.
9. **Patient "⋯" menu & owner solde/préférences**: visible mocks with "à venir" toast (user decision).
10. **Exam media**: removed from quad O UI; dedicated project later.
11. **Pause / Plus tard**: navigation only, consultation stays OPEN.
12. **AI features**: untouched mocks ("Dicter", "Suggérer IA"); accepted AI suggestions persist with `source=AI_SUGGESTION`.
13. **Catalog medication fixtures**: dedicated task — wire orphaned stories into datasets, add price-list items, drug→authorization refs, GTINs, enough medications for realistic search (CLAUDE.md §7 thresholds).
14. **Money display**: new `money` Twig filter + shared JS formatter fed minor units; DTOs keep the `MinorUnits+Currency` convention.
15. **Historique**: new `ListConsultationsForPatient` query keyed via `animal_link_id` route (reconciliation-safe).
16. **Delivery in vertical slices, one fresh dev session per chunk**: this spec is epic-sized; do NOT implement it in a single dev pass. Recommended session cuts: Phase 0 (one session), Phase 1 in two sessions (T6–T8 domain+persistence, then T9–T13 app layer+ports), Phase 2 one session per slice or per pair of small slices. Each session starts fresh with this spec as reference, ends `make ci`-green and committable. The spec's task checkboxes track cross-session progress.
17. **New mutations emit no domain events** (see Task 7 rationale — the BC never publishes them; avoid dead classes and their coverage cost).
18. **Billing lines carry `sourceLineId`** and sync by it (stable ids, snapshot preserved — see Task 7 binding rules).

## Implementation Plan

Tasks are grouped in phases; each phase ends `make ci`-green and committable. Within Phase 2 every slice is an independent vertical (backend command → controller → template binding → JS wiring).

### Tasks

**Phase 0 — Foundations**

- [x] Task 1: Fix Catalog PriceResolver bug
  - Files: `src/Context/Catalog/Application/Service/PriceResolver.php`, `src/Context/Catalog/Domain/Pricing/ValueObject/PricingContext.php`, `src/Context/Catalog/Application/Query/Pricing/ResolvePrice/*` (+ tests)
  - Action: This requires small API changes, scoped here: (a) make `priceListId` **nullable** in `ResolvePrice` and `PricingContext` (null = use the clinic's default list); (b) add `ClinicId` to `PricingContext` (or pass it explicitly to `PriceResolver::resolve()`) — today `ResolvePriceHandler` silently drops `ResolvePrice::$clinicId` and `PriceResolver:39-42` fabricates a ClinicId from the price-list id; (c) resolve the *requested* list when given, default list otherwise. Update all existing Catalog tests touched by the signature change. Regression tests: requested non-default list with item override honored, default fallback when null, missing list exception. Catalog coverage stays 100%.

- [x] Task 2: Wire and enrich demo fixtures (Catalog + Pharma + Taxation)
  - Files: `fixtures/Dataset/ClinicDataset.php`, `fixtures/DevDataset.php`, `fixtures/Context/Catalog/Story/CompanionClinicCatalogStory.php`, `fixtures/System/PharmaceuticalRegistry/Story/*`, `fixtures/System/Taxation/Story/TaxonomyBootstrapStory.php`
  - Action: Load TaxonomyBootstrapStory + CompanionClinicCatalogStory + the 3 Pharma stories in the clinic dataset. Enrich: link drug articles to authorizations (`drugAuthRef`), add GTINs to presentations, add PriceListItems to the default price list, grow the catalog to ~12 medications + ~8 acts (mix of Rx/non-Rx, one archived, per CLAUDE.md §7 thresholds).

- [x] Task 3: Patient BC — patientId → animalId read query
  - Files: new `src/Context/Patient/Application/Query/GetPatientAnimalLink/*` (query + handler + small DTO), extend `PatientReadRepositoryInterface` + its Doctrine implementation
  - Action: Query returning `{patientId, animalId|null, displayLabel}` for a patient id + clinic id. Unit + integration tests; Patient BC coverage stays 100%.

- [x] Task 4: Money display plumbing
  - Files: new `src/Shared/Presentation/Twig/MoneyFormatExtension.php` (pattern: `PhoneFormatExtension`), shared JS formatter in `assets/js/` (used by consultation.js)
  - Action: `money` Twig filter + `fmtMoney(minorUnits, currency)` JS helper rendering `14,80 €` style. Unit test for the Twig runtime.

- [x] Task 5: Frontend foundation in the cockpit
  - Files: `assets/js/pages/consultation.js`, `templates/clinic/consultation/detail/index.html.twig`
  - Action: (a) add `<script type="application/json" id="consultation-data">` hydration block; (b) shared `api()` fetch helper (URLSearchParams POST, JSON envelope, CSRF token read from DOM, error toast on failure) with a **per-consultation mutation queue**: mutations are serialized (one in-flight request at a time, FIFO) so debounced auto-saves can never race a line-add against the aggregate's optimistic lock; on a 409 `CONFLICT` response the helper retries once transparently, then surfaces the error state; (c) real save-state indicator (idle/saving/saved/error, fed by api()); (d) live timer from `startedAtUtc`; (e) delete dead IIFE-1 quad code (lines ~612–836 legacy modals + orphan wiring); (f) fix `cleanup()` to remove **all five** document-level keydown listeners (registered at ~1540, ~1718, ~1994, ~2510, ~3269 — store handler refs) and disconnect the MutationObserver (~3336); (g) replace textContent-based button lookups with ids/data-attributes added in the Twig.

**Phase 1 — Consultation BC domain extension**

- [ ] Task 6: New value objects + enums
  - Files: `src/Context/Consultation/Domain/ValueObject/` — `MotifTag`, `VitalType` (enum: FC, FR, TRC, PAIN_SCORE, MUCOUS_MEMBRANES, …), `TypedVitalRecord`, `BodySystem` (enum, 10 systems), `ExamStatus` (enum NORMAL|ANOMALY|UNTESTED), `ExamSystemRecord`, `DiagnosisCertainty` (enum CERTAIN|PROBABLE|POSSIBLE|EXCLUDED), `DiagnosisSource` (enum MANUAL|AI_SUGGESTION), `DiagnosisRecord`, `PlanActionKind` (enum PERFORMED_ACT|MEDICATION_PRESCRIPTION|FOLLOW_UP_APPOINTMENT|ADVICE|OTHER), `PlanActionRecord`, `PrescriptionLineRecord` (with snapshotted `priceMinorUnits`+`currency`, posology fields dose/frequency/duration/route), `BillingLineRecord` (label, code, qty, unit price snapshot, tax category code, source: PLAN_ACT|PRESCRIPTION)
  - Action: follow existing `create()`/`reconstitute()` + exact-message validation conventions. Unit test per VO covering every branch.
  - **Reference data lives on the enums**: `VitalType` carries `unit()`, `min()`, `max()`, `label()` methods (FC 40–250 bpm, FR 8–90 /min, TRC 0–5 s, PAIN_SCORE 0–10, etc. — exact ranges chosen at implementation from the current UI's `VITAL_TYPES` mock, consultation.js:67-77); `TypedVitalRecord::create()` validates against them (feeds AC14). `BodySystem` carries `label()` + `icon()`. These enums are the single server-side source for the hydration payload — no separate config files.

- [ ] Task 7: Aggregate extension
  - File: `src/Context/Consultation/Domain/Consultation.php` (+ new events in `Domain/Event/`)
  - Action: new root fields (`subjectiveText`, `objectiveObservations`, `teamMemo` — nullable strings) and child collections (motifs, typedVitals, examSystems, diagnoses, planActions, prescriptionLines, billingLines). Mutations (all `ensureOpen()` + event): `setMotifs`, `recordTypedVital` (upsert by type), `updateSubjectiveText`, `recordExamSystem` (upsert by system), `markAllSystemsNormal`, `updateObjectiveObservations`, `addDiagnosis`/`updateDiagnosis`/`removeDiagnosis`/`setPrimaryDiagnosis` (single primary invariant), `addPlanAction`/`updatePlanAction`/`removePlanAction`, `addPrescriptionLine`/`removePrescriptionLine`, `syncBillingLines` (see rules below), `updateTeamMemo`. Thread everything through `reconstitute()`. Full unit tests incl. closed-state rejection per mutation.
  - **Billing derivation rules (binding):** `syncBillingLines()` is called internally by the aggregate at the end of EVERY mutation that adds, updates, or removes a plan action of kind PERFORMED_ACT or a prescription line. Each `BillingLineRecord` stores a `sourceLineId` (the id of the plan action or prescription line it derives from). Sync matches existing billing lines by `sourceLineId`: matched lines keep their id AND their snapshotted price (prices are **copied from the source line's snapshot, never re-resolved** — AC4), new sources create new lines, orphaned lines are removed. Quantity/label changes on the source propagate; price does not change unless the source line itself was re-created.
  - **Domain events:** new mutations do NOT introduce event classes. Rationale: the BC records events but never publishes them (`pullDomainEvents()` is uncalled everywhere), so new events would be dead code carrying pure coverage cost. Existing events stay untouched; if event publishing is ever wired up, events can be added then.

- [ ] Task 8: Persistence for new children
  - Files: new entities/mappers under `src/Context/Consultation/Infrastructure/Persistence/Doctrine/`, extend `DoctrineConsultationRepository` (save/findById), migration via `make consultation-migrations`
  - Action: tables `consultation__motifs`, `consultation__typed_vitals`, `consultation__exam_systems` (structured drill-down as JSON column), `consultation__diagnoses`, `consultation__plan_actions`, `consultation__prescription_lines`, `consultation__billing_lines`; 3 new text columns on `consultation__consultations`. Simple value collections (motifs, typed vitals, exam systems) use the existing delete+reinsert pattern; row-identity collections (diagnoses, plan actions, prescription/billing lines) use the Procurement line-sync pattern. Foundry factories for each new entity. Repository round-trip integration tests.

- [ ] Task 9: Catalog + Pharma port for medications/acts/prices (BEFORE the command handlers that consume it)
  - Files: new `src/Context/Consultation/Application/Port/CatalogItemProviderInterface` + DTOs; adapter `src/Context/Consultation/Infrastructure/Adapter/Catalog/QueryBusCatalogItemProvider.php`; **Catalog-side search enrichment**: extend `CatalogSearchResult` + `DoctrineCatalogSearchRepository` (`src/Context/Catalog/...`) to include `isPrescriptionRequired` and `basePriceMinorUnits`/`currency` via a single JOINed query (no N+1 `GetArticleDetail` per result) — Catalog tests updated, coverage stays 100%; wiring in `config/services.yaml` (dev + test blocks)
  - Action: port exposes: `search(term)` → items with kind/code/name/Rx flag/base price; `detail(itemRef)` → full item; `resolvePrice(itemRef)` → snapshot-ready price (via fixed ResolvePrice, default list); **`activeSubstances(articleId)`** → list of substance labels, bridged by the adapter dispatching Catalog `GetArticleDetail` (→ `authorizationRef`) then PharmaceuticalRegistry `GetMarketingAuthorizationDetail` (→ `activeSubstances[]`); returns empty list when the article has no authorization ref. This is the data path for the allergy warning (AC6). Imitate Inventory's adapter. Integration tests against fixtures.

- [ ] Task 10: Commands + handlers (one per mutation of Task 7)
  - Files: `src/Context/Consultation/Application/Command/<Name>/*` ×~14
  - Action: scalar command DTOs + `#[AsMessageHandler]` handlers following `RecordVitals` template ('Consultation not found' guard). `AddPrescriptionLine` and `AddPlanAction(kind=PERFORMED_ACT)` handlers resolve+snapshot the price via the Task 9 port; billing derivation happens inside the aggregate (Task 7 rules). Mock unit tests happy/not-found/closed for each.

- [ ] Task 11: Read side extension + Taxation port
  - Files: `ConsultationDetailsDTO`, `DoctrineConsultationReadRepository`, `GetConsultationDetailsHandler` (+ test); new port `src/Context/Consultation/Application/Port/TaxRateProviderInterface` + adapter `Infrastructure/Adapter/Taxation/QueryBusTaxRateProvider.php` (dispatches Taxation `GetEffectiveRateForUI`); services.yaml dev + test wiring
  - Action: DTO gains motifs, typedVitals, examSystems, diagnoses, planActions, prescriptionLines, billingLines, subjectiveText, objectiveObservations, teamMemo, and computed totals `{totalHtMinorUnits, totalTvaMinorUnits, totalTtcMinorUnits, currency}`. **Where tax is computed**: in `GetConsultationDetailsHandler` (NOT in the DBAL repo) — it collects the distinct `taxCategoryCode`s of the billing lines, asks the Taxation port once per distinct code (memoized per call), and sums. The DBAL repo stays bus-free. Keep flat array shapes with docblocks. Adapter integration test with Taxation fixtures.

- [ ] Task 12: `ListConsultationsForPatient` query
  - Files: new `src/Context/Consultation/Application/Query/ListConsultationsForPatient/*`; new port `src/Context/Consultation/Application/Port/PatientIdsProviderInterface` + adapter `Infrastructure/Adapter/Patient/DbalPatientIdsProvider.php` (reads `patient__patients` ids by `animal_link_id`); extend `ConsultationReadRepositoryInterface` + Doctrine read repo; services.yaml wiring
  - Action: the handler takes `{animalId, clinicId, excludeConsultationId}`; the Patient port resolves all patient ids linked to that animal (reconciliation-safe — NOT a raw cross-BC join inside the read repo, respecting CLAUDE.md §6 structure); the read repo then lists consultations `WHERE patient_id IN (?)` clinic-scoped, `started_at_utc DESC`. History rows: `{consultationId, startedAtUtc, closedAtUtc, status, chiefComplaint, summary, weightKg}` — **weightKg included** (feeds Task 16's weight Δ). Integration tests for port + query.

- [ ] Task 13: Animal BC — minimal medical alerts
  - Files: `src/Context/Animal/Domain/**` (VO `MedicalAlert{id, kind: ALLERGY|CHRONIC_CONDITION, label, note?}` + aggregate methods add/remove), Application commands `AddMedicalAlert`/`RemoveMedicalAlert` + query `ListMedicalAlertsForAnimal`, persistence (`animal__medical_alerts` + migration in `migrations/Animal/`), fixtures (Luna-like animals get a penicillin allergy + arthrose condition)
  - Action: follow Animal BC's existing conventions; full tests, Animal coverage stays 100%.

**Phase 2 — Vertical slices (controller + Twig binding + JS wiring each)**

Every slice: single-`__invoke` POST controllers under `src/Presentation/Clinic/Controller/Consultation/Record/` returning the JSON envelope `{success, errors, errorCode}`; Twig partial bound to the extended DTO; consultation.js block rewired from mock store to hydrated data + `api()` calls; closed-consultation state renders the block read-only.

**Controller conventions (binding for all Phase 2 endpoints):**
- **CSRF**: ONE shared page-level intent `csrf_token('consultation_edit')` rendered once as a hidden input in `index.html.twig`, validated by every cockpit mutation controller (resolves the per-intent vs shared ambiguity: shared-by-page, like the appointment modal's single `appointment` intent). Invalid token → 403 `{success:false, errorCode:'CSRF_INVALID'}`.
- **Error unwrapping**: controllers catch `HandlerFailedException`, unwrap via `$e->getPrevious()`; `\DomainException` → 409 `{success:false, errorCode:'DOMAIN_ERROR', errors:{global:[message]}}`; `\InvalidArgumentException` → 422 `{success:false, errorCode:'VALIDATION', errors:{...}}`; Doctrine `OptimisticLockException` → 409 `{success:false, errorCode:'CONFLICT'}` (client retries once per Task 5b).
- **Reuse means the command, never the old controller**: e.g. weight/temp dispatch the existing `RecordVitals` command from a NEW JSON controller; the legacy flash+redirect controllers (`RecordVitalsController` etc.) are left untouched (or retired if nothing else links to them — check routes before deleting).

- [ ] Task 14: Hydration + patient banner slice
  - Files: `ConsultationDetailsController.php`, `templates/clinic/consultation/detail/{index,_patient_banner}.html.twig`, `consultation.js`
  - Action: controller composes `GetConsultationDetails` + `GetPatientAnimalLink` + `GetAnimalById` + `GetClientById` + `ListMedicalAlertsForAnimal` + `ListConsultationsForPatient`; emits the JSON hydration block; banner shows real animal (name, sex/sterilized, breed, age, chip), owner, real allergy/condition badges; profil animal + profil client modals fed with real data; "⋯" menu entries show "Fonctionnalité à venir" toast (kept mock per decision); breadcrumb + page title real.
  - **Null `animal_link_id` (unreconciled patient)**: the banner falls back to the Patient's `displayLabel` + `observedSpecies`/`observedColor`, shows an "Patient non identifié" hint instead of chip/sterilization, NO allergy badges, profil-animal modal disabled; the ordonnance allergy check is skipped (no alerts = no warning); Historique falls back to `patient_id`-keyed listing for the current patient only. The cockpit must remain fully functional in this state (AC18).

- [ ] Task 15: Motifs slice — `UpdateConsultationMotifsController` (`POST /clinic/consultations/{id}/motifs`), bind `_sidebar_vitals.html.twig` motif strip, wire add/remove/free-text edit.
- [ ] Task 16: Constantes slice — `RecordTypedVitalController` + a new JSON controller dispatching the existing `RecordVitals` command for weight/temp; bind pills incl. weight Δ vs previous consultation (`weightKg` from the Task 12 history rows); wire add/edit modals with VitalType ranges from hydration (enum-sourced, Task 6).
- [ ] Task 17: SOAP S slice — `UpdateSubjectiveTextController`; textarea bound + debounced auto-save via api(); "Dicter" stays mock.
- [ ] Task 18: Quad O slice — `RecordExamSystemController` + `MarkAllSystemsNormalController` + `UpdateObjectiveObservationsController`; remove media-thumbnail UI from the modal; species template affects display only; per-system status/notes/structured data persisted.
- [ ] Task 19: Quad A slice — `AddDiagnosisController`, `UpdateDiagnosisController`, `RemoveDiagnosisController`, `SetPrimaryDiagnosisController`; diagnosis nomenclature = a static PHP list in a dedicated Presentation view-model class `src/Presentation/Clinic/ViewModel/Consultation/CockpitReferenceData.php` (ported verbatim from the current `NOMENCLATURE` mock, consultation.js:2044-2060), served via hydration; "Suggérer (IA)" modal stays mock but accepted suggestions call the real add endpoint with `source=AI_SUGGESTION`.
- [ ] Task 20: Quad P slice — `AddPlanActionController`, `UpdatePlanActionController`, `RemovePlanActionController`; act suggestions come from the Catalog port (real acts + prices); PERFORMED_ACT additions derive billing lines in the aggregate; preset motifs + plan templates also live in `CockpitReferenceData.php` (ported from `PRESET_MOTIFS` consultation.js:79-85 and the quad-P `PLAN_TEMPLATES` consultation.js:2615-2645), served via hydration; applying a template fires the real add endpoints item by item through the Task 5 queue.
- [ ] Task 21: Ordonnance slice — `AddPrescriptionLineController`, `RemovePrescriptionLineController`; medication search modal backed by a `GET` search endpoint using the Catalog port (`src/Presentation/Clinic/Controller/Consultation/Record/SearchCatalogItemsController` or Api namespace, HTML-fragment or JSON per global-search precedent); posology form; price snapshot; allergy warning cross-checks the animal's real medical alerts against the article's active substances (label match, simple contains — no interaction engine); billing lines derived.
- [ ] Task 22: Facturation slice — bind `.bill-*` markup to DTO billing lines + totals (HT/TVA/TTC from Taxation-resolved rates — delete the hardcoded ÷1.2); rows re-render from server responses after any plan/ordonnance change; draft badge static (no status workflow).
- [ ] Task 23: Mémo équipe slice — `UpdateTeamMemoController`; debounced auto-save.
- [ ] Task 24: Topbar slice — Historique modal renders the real history list (link rows to their consultation pages); timer + save-state already live from Task 5; Imprimer: print stylesheet (`@media print`) rendering ordonnance/compte-rendu from real data via a dedicated print view or print-scoped CSS on the page.
- [ ] Task 25: Footer + read-only slice — wire "Clôturer" modal to existing `CloseConsultationController` (keep POST+redirect flow); Pause/Plus tard navigate to `clinic_admission_queue`; when `status=CLOSED` render banner "Consultation clôturée", disable all mutation UI, hide add buttons (server already rejects via ensureOpen).
- [ ] Task 26: Consultation demo fixtures — extend fixtures so demo DB contains consultations in varied states (open empty, open rich with all blocks filled, closed with summary) per CLAUDE.md §7.
- [ ] Task 27: Finalization — update `src/Context/Consultation/README.md` (stale NoteType list + new model); `make ci` + coverage verification on Consultation/Animal/Catalog/Patient; full browser pass (login → cockpit → exercise every block → reload → everything persisted → close → read-only).

### Acceptance Criteria

- [ ] AC 1: Given an open consultation, when I add a motif, a typed vital, a subjective text, an exam-system status, a diagnosis, a plan action, a prescription line, and a team memo, then each is persisted and still present after a full page reload.
- [ ] AC 2: Given the cockpit, when the page loads, then patient banner, motifs, constantes, SOAP content, ordonnance, facturation, and mémo all render from server data with zero hardcoded "Luna/Lambert" content remaining.
- [ ] AC 3: Given the medication search modal, when I search, then results come from Catalog fixtures (name/code, Rx flag), and when I add one with posology, then the line appears in the ordonnance with its price snapshotted at add time.
- [ ] AC 4: Given a prescription line exists, when the Catalog base price of that article later changes, then the existing line's price is unchanged.
- [ ] AC 5: Given plan acts and prescription lines, when any is added/removed, then billing lines re-derive server-side, and totals show HT/TVA/TTC computed from real Taxation rates (no hardcoded 20%).
- [ ] AC 6: Given the animal has a penicillin allergy (Animal BC medical alert), when the cockpit renders, then the banner shows the real allergy badge, and when I add a medication whose active substance matches the allergy label, then the ordonnance warning appears.
- [ ] AC 7: Given the patient has past consultations, when I open Historique, then the real list appears (date, motif/summary) with links; given none exist, an empty state shows.
- [ ] AC 8: Given the consultation is OPEN, when time passes, then the topbar timer ticks from startedAtUtc, and when any save completes/fails, then the save-state indicator reflects it.
- [ ] AC 9: Given a CLOSED consultation, when I open the cockpit, then a closure banner shows and every mutation control is disabled/hidden; when a mutation endpoint is called anyway, then the server returns the domain error.
- [ ] AC 10: Given the cockpit, when I click "Dicter" or "Suggérer (IA)", then the existing mock behavior is preserved unchanged; when I accept an AI suggestion, then it persists via the real endpoint with source AI_SUGGESTION and shows the IA chip after reload.
- [ ] AC 11: Given "Pause"/"Plus tard", when clicked and confirmed, then I land on the admission queue and the consultation stays OPEN (still reachable from the Pris en charge panel); given "Clôturer", when confirmed, then the existing close flow runs and redirects appropriately.
- [ ] AC 12: Given the "⋯" patient menu, when I click SMS/email/tag/archiver/transfert, then a "Fonctionnalité à venir" toast shows (no dead silence, nothing else happens).
- [ ] AC 13: Given the Imprimer modal, when I choose ordonnance/compte-rendu, then a print-ready layout with real data opens via browser print.
- [ ] AC 14: Given an invalid mutation (empty diagnosis label, vital out of range, unknown consultation id), when submitted, then the JSON envelope returns success=false with field errors and the UI shows the error state without corrupting local state.
- [ ] AC 15: Given `make ci`, when run after each phase, then it is green, and the Consultation, Animal, Catalog, and Patient BCs each report 100% line coverage.
- [x] AC 16: Given `ResolvePrice` with an explicit non-default price list containing an item override, when resolved, then the override price is returned (PriceResolver bug fixed) — regression-tested.
- [ ] AC 17: Given two mutations issued in quick succession (e.g. memo auto-save while adding a diagnosis), when both complete, then neither is lost and no error surfaces to the user (client FIFO queue + single 409/CONFLICT retry); given a genuine unresolved conflict, then the save-state indicator shows the error state.
- [ ] AC 18: Given a consultation whose patient has no linked animal (unreconciled), when the cockpit loads, then it renders fully functional with the patient's provisional label, no allergy badges, disabled profil-animal modal, and patient-scoped history — no errors.

## Review Notes — Phase 0 (2026-08-20)

Adversarial review run on the full Phase 0 diff (isolated subagent, information asymmetry). 16 findings: 1 Critical, 4 Medium, 11 Low.

**Fixed (10):** F1 invalid `drugPrescriptionClass` enum values in catalog fixtures (Critical — would crash every domain article read; now `RX_NARCOTIC`/`RX`/`NONE`); F2 `MoneyFormatRuntime` now injects `CurrencyRegistry` (real symbols + per-currency decimals, no hardcoded 2-decimal assumption); F4 added `ResolvePriceHandlerTest` (both priceListId branches); F6 timer now compensates client clock skew via `serverNowUtc` in the hydration payload; F7 `parseUtcDate` regex anchored; F8 toast container removed by `cleanup()` (Turbo cache leak); F9 hydration `json_encode(JSON_HEX_TAG)` pins the script-breakout invariant; F10 fixture substance dedup uses `ActiveSubstance::normalizeLabel()`; F11 catalog story throws `\LogicException` instead of `\assert()`; F14 dead/mismatched CSS token fallbacks dropped.

**Skipped with reason (6):** F3 blind 409 retry semantics — decided when Phase 2 defines the server payloads (retry may need a version field); F5 PriceResolver ignores price-list status (pre-existing for the default list too; domain decision, not Phase 0); F12 exception factory method suggestion conflicts with the project rule "no static factory methods on exceptions"; F13 GTIN literals duplicated between pharma and catalog fixture files (intentional demo correspondence); F15 unused api()/money plumbing is deliberate Phase 0 scaffolding for Phase 2; F16 `ResolvePrice` positional-arg reorder — verified zero positional callers.

Post-fix: `make ci` green (2540 tests), fixtures reloaded and enum values verified in DB, Playwright smoke re-run green (timer skew-corrected, no JS errors).

## Additional Context

### Dependencies

- Catalog + PharmaceuticalRegistry + Taxation fixture stories wired into `ClinicDataset`/`DevDataset` (currently orphaned).
- PriceResolver bug fix in Catalog BC (touching Catalog = its coverage must stay 100%).
- New Patient BC read query (patientId → animalId hop).
- New Animal BC medical-alerts model (touching Animal = coverage stays 100%).

### Testing Strategy

- Unit: every new VO (validation branches, exact messages), aggregate mutations (happy + closed-state rejection each), handlers (mock repos, happy/not-found/closed).
- Integration: repository round-trips for each new child table; new read queries; port adapters (Dbal + query-bus).
- New Foundry factories for each new entity (fixtures/Context/Consultation/Factory/…).
- Frontend verified in browser (Playwright flow: login → open consultation → exercise each block → reload → data persisted).
- 100% line coverage maintained on Consultation, Animal, Catalog, Patient BCs.

### Notes

- User instruction: "TOUT ce qu'il y a sur cette page doit être fonctionnel SAUF ce qui peut être lié à de l'IA" — AI buttons/content stay mocked, everything else real.
- Consultation BC README.md is stale (NoteType list) — update while touching the BC (Task 27).
- `cleanup()` keydown listener leak fix is part of the JS restructure (Task 5).

**High-risk items (pre-mortem):**

- **Aggregate size**: `save()` will rewrite up to 9 child tables per mutation. The optimistic `#[ORM\Version]` lock protects concurrent edits; collisions are handled by the client FIFO queue + 409/CONFLICT retry (Task 5b + controller conventions). Watch flush cost; the Procurement line-sync pattern limits rewrites for row-identity collections.
- **Test volume**: Phase 1 is the bulk of the effort — ~14 handlers × 3 paths + ~13 VOs + aggregate mutations + round-trips. Budget accordingly; do not batch-skip sad paths.
- **Taxation integration**: first-ever consumer of `GetEffectiveRateForUI` from Presentation/Consultation read side — verify the tax fixtures (TaxonomyBootstrapStory) provide rates for both act and drug categories, else totals silently degrade.
- **Allergy matching is label-based** (substance name contains allergy label) — deliberately naive; a false sense of safety is possible. The warning copy should say "vérifiez" not "incompatible garanti".
- **Exam structured drill-downs stored as JSON column** — the one pragmatic JSON concession (UI-shaped data, per-system schemas vary); document the shape in the VO docblock.

**Future considerations (out of scope, noted for later):**

- Exam media upload (dedicated storage project).
- Real invoice generation from billing draft lines (future Billing BC).
- Addendum notes on closed consultations.
- Real SMS/email actions from the patient menu; client balance.
- AI dictation + AI diagnosis suggestions (replace the two mocks).
