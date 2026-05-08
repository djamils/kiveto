---
title: 'Identify Unidentified Emergency Admission'
slug: 'identify-unidentified-admission'
created: '2026-05-08'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4, 5]
tech_stack: ['Symfony 7', 'Doctrine ORM', 'Doctrine DBAL', 'Twig', 'Stimulus JS', 'PHPUnit']
files_to_modify:
  - src/Context/Admission/Application/Port/PatientCreationPort.php
  - src/Context/Admission/Infrastructure/Adapter/Patient/CommandBusPatientCreationAdapter.php
  - src/Context/Admission/Domain/Admission.php
  - src/Context/Patient/Application/Service/PatientIdentityResolutionService.php
  - templates/clinic/admission/queue.html.twig
  - assets/controllers/waiting_room_controller.js
code_patterns:
  - 'Command/Handler pair in Application/Command/<Name>/'
  - 'Port interface + CommandBus adapter for cross-BC calls'
  - 'Integration events for cross-BC side effects'
  - 'Vanilla JS for elements outside Stimulus controller scope'
test_patterns:
  - 'PHPUnit unit tests in tests/Unit/Context/<BC>/'
  - 'Domain tests: Admission::open() helper + pullDomainEvents() assertions'
  - 'Handler tests: mock all deps, assert dispatch/save called'
---

# Tech-Spec: Identify Unidentified Emergency Admission

**Created:** 2026-05-08

## Overview

### Problem Statement

When an emergency animal arrives unidentified (no owner, no microchip scan), staff admit it as anonymous. Once the animal is stabilised or an owner is found, the clinical team needs to link the admission to a real client/animal record mid-flow — without losing any triage data already captured. Currently the modal UI exists (`modal-identifier` with 3 tabs) but has no backend wiring at all.

### Solution

Wire two of the three identification tabs:

1. **"Propriétaire" tab** — staff search for an existing animal using `global-search` (picker-id `identifier-animal`, type `animal`). On pick, the backend runs `ReconcileAdmissionPatient`: reads the admission's `patientId` (the unidentified patient), then calls `PatientCreationPort.reconcilePatientToAnimal()` which dispatches Patient BC command `ReconcilePatientToAnimal`. That handler calls `PatientIdentityResolutionService.linkOrReconcileToAnimal()` which branches: if the animal has no active patient → `linkToExistingAnimal()` (patient linked to animal, admission `patient_id` unchanged, `animal_link_id` set); if the animal already has an active patient → `reconcileInto()` (unidentified patient archived, admission `patient_id` updated via existing `PatientReconciledIntoHandler`).

2. **"Anonyme" tab** — staff optionally update `physicalDescription` and `triageNotes` on the Admission via `UpdateAnonymousAdmission` command. Empty submission is valid.

After either action the page reloads. The "Identifier" button and "NON ID" badge disappear after successful owner identification because the condition `isUnknown = (not isPatientIdentifiedAtOpening) AND (knownAnimalId is null)` evaluates to false once `knownAnimalId` becomes non-null.

### Scope

**In Scope:**
- Tab "Propriétaire": `global-search` (picker-id `identifier-animal`, type `animal`) → pick → POST `tab=owner` + `animalId` + `animalName` → `ReconcileAdmissionPatient`
- Tab "Anonyme": optional `physicalDescription` + `triageNotes` update → POST `tab=anon` (empty = valid) → `UpdateAnonymousAdmission`
- Remove "Puce / Tatouage" tab from rendered modal
- New route `POST /admission/identify` → `IdentifyAdmissionController`
- New Admission BC commands: `ReconcileAdmissionPatient`, `UpdateAnonymousAdmission`
- New Patient BC command: `ReconcilePatientToAnimal` (+ new `linkOrReconcileToAnimal()` method on `PatientIdentityResolutionService`)
- Extend `PatientCreationPort` with `reconcilePatientToAnimal()`; implement in `CommandBusPatientCreationAdapter`
- Add `Admission::updatePhysicalDescription(?string, \DateTimeImmutable): void` domain method
- `isUnknown` condition updated: `(not entry.isPatientIdentifiedAtOpening) and (entry.knownAnimalId is null)`
- Vanilla JS `openIdentifierModal(admissionId, label, physicalDescription, triageNotes)` to pre-fill modal
- `identifier-animal` picker handler in `waiting_room_controller.js`

**Out of Scope:**
- ICAD microchip lookup / "Puce / Tatouage" tab
- Real-time DOM update without page reload
- Card migration to a different UI column after identification
- Creating a new client record from this modal

## Context for Development

### Codebase Patterns

- **Command/Handler**: every application operation is a `final readonly class MyCommand implements CommandInterface` + `#[AsMessageHandler] final readonly class MyCommandHandler`. Handler lives in `Application/Command/<CommandName>/` with exactly two files.
- **Port/Adapter BC boundary**: cross-BC calls go via a port interface in `Application/Port/` (Admission BC) implemented by an adapter in `Infrastructure/Adapter/<OtherBC>/`. Adapters dispatch the other BC's commands via `$this->commandBus->dispatch(new OtherBcCommand(...))`.
- **Integration events**: cross-BC state updates use integration events on `messenger.bus.integration_event`. Admission BC's `PatientReconciledIntoHandler` already listens to `PatientReconciledIntoIntegrationEvent` and updates `admission.patient_id` automatically — no changes needed there.
- **Vanilla JS for modal**: the `modal-identifier` div is rendered outside `data-controller="waiting-room"` (the controller div closes before the modals section). Open/close and form wiring use `onclick` vanilla JS functions (same pattern as `modal-urgence` / `urgClose()`).
- **global-search picker in modal**: works independently of Stimulus scope — `global-search` is its own controller registered on the search wrapper div. It dispatches `vetsaas:search:pick` that bubbles to `document`. `waiting_room_controller.js` catches it in `_handleSearchPick()`.
- **Tests**: `tests/Unit/Context/<BC>/`. Domain tests: arrange via `Admission::open()` helper, act, assert via `pullDomainEvents()`. Handler tests: mock all dependencies, assert method calls.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Admission/Application/Port/PatientCreationPort.php` | Extend with `reconcilePatientToAnimal()` |
| `src/Context/Admission/Infrastructure/Adapter/Patient/CommandBusPatientCreationAdapter.php` | Implement new port method — dispatches Patient BC command |
| `src/Context/Patient/Application/Service/PatientIdentityResolutionService.php` | Add `linkOrReconcileToAnimal()`; existing `linkToExistingAnimal()` + `reconcileInto()` |
| `src/Context/Patient/Application/Port/PatientReadRepositoryPort.php` (or equivalent) | Used internally by `PatientIdentityResolutionService` for `existsActiveForAnimal()` |
| `src/Context/Admission/Application/EventSubscriber/PatientReconciledIntoHandler.php` | Already handles reconcile event → no changes |
| `src/Context/Admission/Domain/Admission.php` | Add `updatePhysicalDescription()`; existing `updateTriageNotes()` |
| `src/Context/Admission/Application/Command/UpdateAdmissionTriage/UpdateAdmissionTriageHandler.php` | Pattern for handler: get → domain method → save |
| `src/Context/Patient/Application/Command/CreateIdentifiedPatient/` | Patient BC command/handler pattern |
| `assets/controllers/waiting_room_controller.js` | `_handleSearchPick()` — add `identifier-animal` case |
| `templates/clinic/admission/queue.html.twig` | `waitExpanded` macro, `modal-identifier` HTML, `isUnknown` condition, JS block |
| `tests/Unit/Context/Admission/AdmissionTest.php` | Test patterns |

### Technical Decisions

- **One command per tab** (`ReconcileAdmissionPatient` vs `UpdateAnonymousAdmission`) — branched in controller on `tab` hidden field.
- **Extend `PatientCreationPort`** (not a new port) — it is already injected in admission command handlers via `EmergencyAdmissionService`.
- **New `linkOrReconcileToAnimal()` method on `PatientIdentityResolutionService`** encapsulates the branch logic (check-then-link-or-reconcile). The Patient BC command handler calls this single method.
- **`isPatientIdentifiedAtOpening` is never updated** — historical flag. Button/badge visibility is driven by `knownAnimalId is null`.
- **Empty Anonyme submission is valid**: controller allows null values; handler only calls domain methods when value is non-null; saves only if at least one field changed.
- **Pre-fill Anonyme fields**: `openIdentifierModal()` receives `physicalDescription` and `triageNotes` from the button's data attributes so the staff sees current values.

## Implementation Plan

### Tasks

- [x] **Task 1 — Domain: `Admission::updatePhysicalDescription()`**
  - File: `src/Context/Admission/Domain/Admission.php`
  - Action: Add method `public function updatePhysicalDescription(?string $description, \DateTimeImmutable $now): void` that sets `$this->physicalDescription = $description` and `$this->updatedAt = $now`. No domain event needed (description update is non-significant).
  - Notes: Mirror the exact pattern of existing `updateTriageNotes()`.

- [x] **Task 2 — Patient BC service: `linkOrReconcileToAnimal()`**
  - File: `src/Context/Patient/Application/Service/PatientIdentityResolutionService.php`
  - Action: Add `public function linkOrReconcileToAnimal(PatientId $sourcePatientId, string $clinicId, string $animalId, string $animalName): void`. Body: if `$this->patientReadRepository->existsActiveForAnimal($clinicId, $animalId)` → get the existing patient id → call `$this->reconcileInto($sourcePatientId, PatientId::fromString($existingId), $clinicId)`. Else → call `$this->linkToExistingAnimal($sourcePatientId, $clinicId, $animalId, $animalName)`.
  - Notes: `patientReadRepository` is already injected in the service. The existing `getActivePatientIdForAnimal()` on that repository gives the existing patient id.

- [x] **Task 3 — Patient BC: `ReconcilePatientToAnimal` command + handler**
  - Files: `src/Context/Patient/Application/Command/ReconcilePatientToAnimal/ReconcilePatientToAnimal.php` (new), `src/Context/Patient/Application/Command/ReconcilePatientToAnimal/ReconcilePatientToAnimalHandler.php` (new)
  - Action — Command: `final readonly class ReconcilePatientToAnimal implements CommandInterface` with constructor `(public string $sourcePatientId, public string $animalId, public string $animalName, public string $clinicId)`.
  - Action — Handler: `#[AsMessageHandler]`, inject `PatientIdentityResolutionService`. `__invoke` calls `$this->service->linkOrReconcileToAnimal(PatientId::fromString($command->sourcePatientId), $command->clinicId, $command->animalId, $command->animalName)`.
  - Notes: Handler has one dependency only. No return value.

- [x] **Task 4 — Admission BC port: extend `PatientCreationPort`**
  - File: `src/Context/Admission/Application/Port/PatientCreationPort.php`
  - Action: Add method signature `public function reconcilePatientToAnimal(string $sourcePatientId, string $animalId, string $animalName, string $clinicId): void;`

- [x] **Task 5 — Admission BC adapter: implement `reconcilePatientToAnimal()`**
  - File: `src/Context/Admission/Infrastructure/Adapter/Patient/CommandBusPatientCreationAdapter.php`
  - Action: Implement the new port method. Body: `$this->commandBus->dispatch(new ReconcilePatientToAnimal(sourcePatientId: $sourcePatientId, animalId: $animalId, animalName: $animalName, clinicId: $clinicId));` — mirrors the pattern of `createIdentifiedPatient()`.
  - Notes: Add `use App\Context\Patient\Application\Command\ReconcilePatientToAnimal\ReconcilePatientToAnimal;`.

- [x] **Task 6 — Admission BC: `ReconcileAdmissionPatient` command + handler**
  - Files: `src/Context/Admission/Application/Command/ReconcileAdmissionPatient/ReconcileAdmissionPatient.php` (new), `src/Context/Admission/Application/Command/ReconcileAdmissionPatient/ReconcileAdmissionPatientHandler.php` (new)
  - Action — Command: `final readonly class ReconcileAdmissionPatient implements CommandInterface` with `(public string $clinicId, public string $admissionId, public string $animalId, public string $animalName)`.
  - Action — Handler: inject `AdmissionRepositoryInterface` + `PatientCreationPort`. `__invoke`: get admission via `$this->admissionRepository->get(ClinicId::fromString($command->clinicId), AdmissionId::fromString($command->admissionId))`, then call `$this->patientCreationPort->reconcilePatientToAnimal(sourcePatientId: $admission->patientId(), animalId: $command->animalId, animalName: $command->animalName, clinicId: $command->clinicId)`. No `admissionRepository->save()` — the patient-side integration event handles the admission update automatically.

- [x] **Task 7 — Admission BC: `UpdateAnonymousAdmission` command + handler**
  - Files: `src/Context/Admission/Application/Command/UpdateAnonymousAdmission/UpdateAnonymousAdmission.php` (new), `src/Context/Admission/Application/Command/UpdateAnonymousAdmission/UpdateAnonymousAdmissionHandler.php` (new)
  - Action — Command: `final readonly class UpdateAnonymousAdmission implements CommandInterface` with `(public string $clinicId, public string $admissionId, public ?string $physicalDescription, public ?string $triageNotes)`.
  - Action — Handler: inject `AdmissionRepositoryInterface` + `ClockInterface`. `__invoke`: get admission, call `$admission->updatePhysicalDescription($command->physicalDescription, $now)` only if `$command->physicalDescription !== null`, call `$admission->updateTriageNotes($command->triageNotes, $now)` only if `$command->triageNotes !== null`. Call `$this->admissionRepository->save($admission)` only if at least one field was non-null; otherwise return early.

- [x] **Task 8 — Presentation: `IdentifyAdmissionController`**
  - File: `src/Presentation/Clinic/Controller/Admission/IdentifyAdmissionController.php` (new)
  - Action: `#[Route('/admission/identify', name: 'clinic_admission_identify', methods: ['POST'])]`, single `__invoke`. Validate CSRF token `admission_identify`. Read `tab`, `admissionId`. If `tab === 'owner'`: dispatch `ReconcileAdmissionPatient(clinicId, admissionId, animalId, animalName)`. If `tab === 'anon'`: dispatch `UpdateAnonymousAdmission(clinicId, admissionId, physicalDescription ?: null, triageNotes ?: null)`. Flash success. Redirect to `clinic_admission_queue`. Catch `\InvalidArgumentException` and `\DomainException` → flash error + redirect.

- [x] **Task 9 — Template: wire `modal-identifier`**
  - File: `templates/clinic/admission/queue.html.twig`
  - Actions (in order):
    1. **Update `isUnknown` condition** in `waitExpanded` macro (line ~309): change `{%- set isUnknown = not entry.isPatientIdentifiedAtOpening -%}` to `{%- set isUnknown = (not entry.isPatientIdentifiedAtOpening) and (entry.knownAnimalId is null) -%}`.
    2. **Update "Identifier" button** (line ~333): remove `data-action` + `data-waiting-room-modal-id-param` attributes; add `onclick="openIdentifierModal('{{ entry.admissionId }}', '{{ entry.displayLabel|e('js') }}', '{{ entry.triageNotes|default('')|e('js') }}')"` and `data-physical-description="{{ entry.triageNotes|default('') }}"`.
    3. **Wrap modal body in a `<form>`**: add `<form method="post" action="{{ path('clinic_admission_identify') }}" id="identify-form">` just inside `<div class="modal-body">` and close `</form>` just before `<div class="modal-foot">`. Add hidden inputs: `<input type="hidden" name="_token" value="{{ csrf_token('admission_identify') }}">`, `<input type="hidden" name="admissionId" id="identify-admission-id">`, `<input type="hidden" name="tab" id="identify-tab" value="owner">`, `<input type="hidden" name="animalId" id="identify-animal-id">`, `<input type="hidden" name="animalName" id="identify-animal-name">`.
    4. **Remove "Puce / Tatouage" tab**: remove the `<button class="id-tab active">Puce / Tatouage</button>` and the entire `<div class="id-panel active" id="id-panel-chip">` block. Change the "Propriétaire" tab button to have class `id-tab active` (it becomes the default tab).
    5. **Wire `global-search` in owner tab**: wrap the `#id-owner-search` input with `<div data-controller="global-search" data-global-search-url-value="{{ path('clinic_search', {format: 'dropdown'}) }}" data-global-search-mode-value="pick" data-global-search-picker-id-value="identifier-animal" data-global-search-type-value="animal">`. Add `data-global-search-target="input"` on the input, `data-global-search-target="results"` on `#id-owner-results`, and `data-global-search-target="liveRegion"` on a hidden `<div class="sr-only" aria-live="polite">`.
    6. **Add `name` attributes to anon tab fields**: `<textarea name="physicalDescription" id="id-anon-desc">`, `<input name="triageNotes" id="id-anon-place">`.
    7. **Add selected animal chip zone** in owner tab: add `<div id="id-animal-selected" style="display:none;"></div>` below the results div.
    8. **Update submit button** to `type="submit" form="identify-form"` (remove disabled attr — controlled by JS).
    9. **Footer modal**: move the submit button outside the form (it references `form="identify-form"`) but keep it in `modal-foot`. Update `modal-foot` structure accordingly.
    10. **JS block**: add vanilla JS functions `openIdentifierModal(admissionId, label, triageNotes)`, `identifierSetAnimal(resourceId, name, subtitle, context)`, and update `idSetTab()` to: (a) set `identify-tab` value, (b) update submit button label + state, (c) remove chip tab logic. Update `idHints` to remove `chip` entry and make `owner` the default. Initial `idSetTab('owner')` on open.

- [x] **Task 10 — JS: `identifier-animal` picker handler**
  - File: `assets/controllers/waiting_room_controller.js`
  - Action: In `_handleSearchPick()`, add a new `else if ('identifier-animal' === pickerId)` branch. Body: clear search results in `#modal-identifier`, populate `#identify-animal-id` with `hit.resourceId`, populate `#identify-animal-name` with `hit.title`, call `window.identifierSetAnimal?.(hit.resourceId, hit.title, hit.subtitle || '', hit.context || '')` to show the chip and enable the submit button.

- [x] **Task 11 — Tests**
  - Files: `tests/Unit/Context/Admission/AdmissionTest.php` (extend), `tests/Unit/Context/Admission/ReconcileAdmissionPatientHandlerTest.php` (new), `tests/Unit/Context/Admission/UpdateAnonymousAdmissionHandlerTest.php` (new), `tests/Unit/Context/Patient/ReconcilePatientToAnimalHandlerTest.php` (new)
  - Domain (`AdmissionTest`): add `testUpdatePhysicalDescriptionSetsField()` — open admission, call `updatePhysicalDescription('fat grey cat', $now)`, assert `physicalDescription() === 'fat grey cat'` and no domain event.
  - `ReconcileAdmissionPatientHandler` test: mock `AdmissionRepositoryInterface` + `PatientCreationPort`. Assert `patientCreationPort->reconcilePatientToAnimal()` called with correct `sourcePatientId` from the mocked admission. Assert `admissionRepository->save()` NOT called.
  - `UpdateAnonymousAdmissionHandler` test — three cases: (1) both fields null → `save()` not called; (2) only `physicalDescription` set → `updatePhysicalDescription()` called, `updateTriageNotes()` not called, `save()` called; (3) both fields set → both domain methods called + `save()`.
  - `ReconcilePatientToAnimalHandler` test: mock `PatientIdentityResolutionService`. Assert `linkOrReconcileToAnimal()` called with correct args.
  - Coverage must reach 100% for all new files in Admission BC and Patient BC.

### Acceptance Criteria

- [x] **AC 1 — Happy path: owner tab, animal has no existing patient**
  Given an active admission with `isPatientIdentifiedAtOpening=false` and `knownAnimalId=null`,
  when staff opens the modal, searches for animal "Max" (resourceId = `animal-uuid`, no existing patient for that animal), picks it, and clicks "Lier au dossier",
  then `ReconcileAdmissionPatient` is dispatched → `PatientIdentityResolutionService.linkOrReconcileToAnimal()` is called → patient's `animal_link_id` is set → page reloads → card's `knownAnimalId` is non-null → "Identifier" button and "NON ID" badge are absent.

- [x] **AC 2 — Happy path: owner tab, animal already has an existing patient (reconcile path)**
  Given an active unidentified admission and animal "Cleo" that already has an active patient `existing-patient-uuid`,
  when staff picks "Cleo" and submits,
  then `reconcileInto()` is called → `PatientReconciledIntoIntegrationEvent` is published → `PatientReconciledIntoHandler` updates `admission.patient_id` to `existing-patient-uuid` → page reloads → card reflects identified patient data.

- [x] **AC 3 — Happy path: anon tab with content**
  Given an active unidentified admission,
  when staff switches to "Anonyme" tab, types "Chat tigré, ~3kg" in description and "Trouvé rue St-Denis" in location, and clicks "Confirmer",
  then `UpdateAnonymousAdmission` is dispatched → admission's `physicalDescription` and `triageNotes` are updated → page reloads → card-meta shows updated triage notes.

- [x] **AC 4 — Happy path: anon tab, empty submission**
  Given an active unidentified admission,
  when staff switches to "Anonyme" tab and clicks "Confirmer" without filling any field,
  then the request completes without error → page reloads → admission data unchanged → "NON ID" badge and "Identifier" button still visible.

- [x] **AC 5 — CSRF validation failure**
  Given any admission,
  when a POST to `/admission/identify` is made with an invalid `_token`,
  then the controller returns a redirect to `clinic_admission_queue` with an error flash; no command is dispatched.

- [x] **AC 6 — Owner tab disabled until animal selected**
  Given the modal is open on the "Propriétaire" tab,
  when no animal has been picked from the search,
  then the "Lier au dossier" submit button is disabled.

- [x] **AC 7 — "Puce / Tatouage" tab absent**
  Given the flux page is loaded,
  when an unidentified card's "Identifier" button is clicked,
  then the modal opens with only two tabs: "Propriétaire" (active by default) and "Anonyme".

- [x] **AC 8 — Tab hint updates on switch**
  Given the modal is open,
  when staff clicks "Anonyme" tab,
  then the footer hint reads "Vous pourrez compléter l'identification plus tard depuis la fiche patient." and the submit button label reads "Confirmer" and is enabled.

## Additional Context

### Dependencies

- `PatientCreationPort` — extended, already injected in `EmergencyAdmissionService` and `CommandBusPatientCreationAdapter`
- `PatientReadRepositoryPort` / Patient BC internal read service — used inside `PatientIdentityResolutionService` for `existsActiveForAnimal()` and `getActivePatientIdForAnimal()`
- `PatientReconciledIntoHandler` (Admission BC) — no changes; already handles the reconcile event
- `global-search` Stimulus controller — supports `mode=pick` + `type=animal` in any DOM context

### Testing Strategy

- Unit tests only — all new handlers have clearly mockable dependencies
- Domain test extension in `AdmissionTest.php` for `updatePhysicalDescription()`
- No new integration tests — existing `PatientReconciledIntoHandler` integration is already covered
- Manual smoke test: open flux page → click "Identifier" on NON ID card → pick animal → submit → verify badge disappears after reload

### Notes

- `is_patient_identified_at_opening` is intentionally NOT updated. Do not add update logic.
- The `linkToExistingAnimal` path publishes `PatientLinkedToAnimalIntegrationEvent` which has NO subscriber in Admission BC. This is correct — `admission.patient_id` is unchanged, `knownAnimalId` becomes non-null via the patient's `animal_link_id`, which is queried in `findAllActiveForClinic()`.
- Ensure the `global-search` controller initializes correctly inside the modal even though the modal starts `hidden`. The `connect()` lifecycle will fire when the element enters the DOM (it's always in DOM, just CSS-hidden), so no special lazy-init needed.
- Submit button for anon tab: always enabled (no selection required). For owner tab: disabled until `identifierSetAnimal()` is called.
