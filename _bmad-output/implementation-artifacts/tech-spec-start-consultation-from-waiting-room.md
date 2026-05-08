---
title: 'Start Consultation from Waiting Room'
slug: 'start-consultation-from-waiting-room'
created: '2026-05-08'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Symfony 7', 'Doctrine ORM', 'Doctrine DBAL', 'Twig', 'PHPUnit']
files_to_modify:
  - src/Context/Consultation/Application/Command/StartConsultationFromAdmission/StartConsultationFromAdmissionHandler.php
  - src/Context/Consultation/Infrastructure/Adapter/Admission/MessengerAdmissionServiceCoordinator.php
  - src/Context/Consultation/Application/Query/GetConsultationDetails/GetConsultationDetails.php
  - src/Context/Consultation/Application/Query/GetConsultationDetails/GetConsultationDetailsHandler.php
  - src/Context/Consultation/Application/Port/ConsultationReadRepositoryInterface.php
  - src/Context/Consultation/Infrastructure/Persistence/Doctrine/Repository/DoctrineConsultationReadRepository.php
  - src/Presentation/Clinic/Controller/Consultation/View/ConsultationDetailsController.php
  - templates/clinic/admission/queue.html.twig
  - tests/Unit/Context/Consultation/Application/Command/StartConsultationFromAdmission/StartConsultationFromAdmissionHandlerTest.php
  - tests/Integration/Context/Consultation/Application/Query/GetConsultationDetails/GetConsultationDetailsHandlerTest.php
code_patterns:
  - 'Command/Handler pair in Application/Command/<Name>/'
  - 'Port interface + Messenger adapter for cross-BC coordination'
  - 'CurrentClinicContextInterface for clinicId in controllers'
  - 'Single-action controllers with __invoke()'
test_patterns:
  - 'PHPUnit unit tests in tests/Unit/Context/<BC>/'
  - 'Integration tests in tests/Integration/Context/<BC>/'
  - 'Handler tests: mock all deps, assert method calls'
---

# Tech-Spec: Start Consultation from Waiting Room

**Created:** 2026-05-08

## Overview

### Problem Statement

The "→ Prendre en charge" button in the flux du jour (queue) is an inert `<button type="button">` with no action. Clicking it does nothing. Additionally, the Consultation BC has two PoC-era issues that must be fixed before wiring: (1) the `AdmissionServiceCoordinatorInterface` is a no-op stub so admission location status is never updated when a consultation starts, causing cards to stay in the "En attente" column instead of moving to "Pris en charge"; (2) `GetConsultationDetails` fetches consultations by UUID only with no `clinic_id` filter, allowing any authenticated user to read consultations from any clinic.

### Solution

Wire the button to a new `StartConsultationFromAdmissionController` (POST) that dispatches the existing `StartConsultationFromAdmission` command and redirects to the consultation details page. Simultaneously: (a) implement the stub `MessengerAdmissionServiceCoordinator` to dispatch a new `UpdateAdmissionLocationStatus` command, inject it into the handler so the admission moves to `in_consultation_room` synchronously; (b) add `clinicId` to `GetConsultationDetails` query and enforce it in the read repository SQL.

### Scope

**In Scope:**
- New `UpdateAdmissionLocationStatus` command + handler (Admission BC)
- Implement `MessengerAdmissionServiceCoordinator.updateLocationStatus()` to dispatch this command
- Inject `AdmissionServiceCoordinatorInterface` into `StartConsultationFromAdmissionHandler`, call after save
- New `StartConsultationFromAdmissionController` — POST `/admission/{admissionId}/start-consultation`
- Wire "Prendre en charge" button in `waitExpanded` macro (inline form + CSRF)
- Tenancy fix: add `clinicId` to `GetConsultationDetails`, handler, `ConsultationReadRepositoryInterface`, DBAL repo SQL, and `ConsultationDetailsController`

**Out of Scope:**
- Fix delete+reinsert PoC in `DoctrineConsultationRepository.save()` (separate story)
- CSRF on existing `StartConsultationFromAppointmentController`
- "Consultation already open" guard (duplicate start)
- List of consultations view
- Real-time card movement without page reload

## Context for Development

### Codebase Patterns

- **Command/Handler**: `final readonly class MyCommand implements CommandInterface` + `#[AsMessageHandler] final readonly class MyCommandHandler`. Two files per pair in `Application/Command/<Name>/`.
- **Single-action controllers**: one `__invoke()` per controller, one route. Inject `CurrentClinicContextInterface` for `clinicId`. Use `CsrfTokenManagerInterface` for CSRF.
- **Cross-BC coordination port**: `AdmissionServiceCoordinatorInterface` in Consultation BC's `Application/Port/`. Implemented by `MessengerAdmissionServiceCoordinator` in `Infrastructure/Adapter/Admission/`. The adapter dispatches commands on the Admission BC via `CommandBusInterface`.
- **`StartConsultationFromAppointmentController`** — direct pattern reference: POST form → dispatch command → redirect to `clinic_consultation_details`. No CSRF in existing controller (PoC), but the new one must have it per codebase standard.
- **Template macro access**: `waitExpanded` macro (defined in `queue.html.twig` with `{%- macro ... -%}`) has access to Twig extensions including `path()` and `csrf_token()`. Use `style="display:contents;"` on the form to preserve the `wait-split` flex layout.
- **Tenancy pattern**: `GetConsultationDetails(consultationId, clinicId)` — handler converts string to `ClinicId` VO (local BC VO from `src/Context/Consultation/Domain/ValueObject/ClinicId.php`). Read repo adds `AND clinic_id = :clinicId`.
- **Integration event bus**: integration events are async. Location status update is a command dispatch (sync), not an integration event.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Admission/Application/Command/UpdateAdmissionTriage/UpdateAdmissionTriage.php` | Pattern for new `UpdateAdmissionLocationStatus` command |
| `src/Context/Admission/Application/Command/UpdateAdmissionTriage/UpdateAdmissionTriageHandler.php` | Pattern for handler: get → domain method → save |
| `src/Context/Admission/Domain/Admission.php` | `updateLocationStatus(LocationStatus, \DateTimeImmutable)` domain method |
| `src/Context/Admission/Domain/ValueObject/LocationStatusValue.php` | `InConsultationRoom = 'in_consultation_room'` |
| `src/Context/Admission/Domain/ValueObject/LocationStatus.php` | VO wrapping `LocationStatusValue` + timestamp |
| `src/Context/Consultation/Application/Command/StartConsultationFromAdmission/StartConsultationFromAdmissionHandler.php` | Handler to extend with coordinator call |
| `src/Context/Consultation/Infrastructure/Adapter/Admission/MessengerAdmissionServiceCoordinator.php` | Stub to implement |
| `src/Context/Consultation/Application/Query/GetConsultationDetails/GetConsultationDetails.php` | Query to extend with `clinicId` |
| `src/Context/Consultation/Infrastructure/Persistence/Doctrine/Repository/DoctrineConsultationReadRepository.php` | Add `AND clinic_id = :clinicId` to SQL |
| `src/Presentation/Clinic/Controller/Consultation/Start/StartConsultationFromAppointmentController.php` | Controller pattern reference |
| `templates/clinic/admission/queue.html.twig` | `waitExpanded` macro, "Prendre en charge" button (line ~339) |
| `tests/Unit/Context/Consultation/Application/Command/StartConsultationFromAdmission/StartConsultationFromAdmissionHandlerTest.php` | Handler test to update |
| `tests/Integration/Context/Consultation/Application/Query/GetConsultationDetails/GetConsultationDetailsHandlerTest.php` | Integration test to update |

### Technical Decisions

- **`UpdateAdmissionLocationStatus` command carries `clinicId`** — mirrors `UpdateAdmissionTriage` pattern. The handler fetches the admission by `(clinicId, admissionId)` via `AdmissionRepositoryInterface.get()`.
- **`MessengerAdmissionServiceCoordinator` dispatches to command bus** — inject `CommandBusInterface` (already bound in Symfony DI). Dispatch `new UpdateAdmissionLocationStatus(admissionId, 'in_consultation_room', clinicId)`. The `clinicId` is passed through from `updateLocationStatus(admissionId, newLocationStatus, triggeredByUserId)` — but the port signature doesn't include `clinicId`. Solution: the adapter queries the admission's clinicId via DBAL or resolves it from `admissionId` via a new parameter. **Simpler**: extend `AdmissionServiceCoordinatorInterface.updateLocationStatus()` to also accept `clinicId` — since it's an internal port, no BC boundary issue.
- **CSRF token**: use `consultation_start` (non per-admission, sufficient for session scoping).
- **`ConsultationDetailsController` injects `CurrentClinicContextInterface`** — passes `currentClinicId->toString()` to `GetConsultationDetails`. Query passes it to handler, handler to read repo.
- **`ConsultationReadRepositoryInterface.findById()` signature change**: add `ClinicId $clinicId` parameter. This is a BC-internal interface. Integration test `GetConsultationDetailsHandlerTest` must be updated.

## Implementation Plan

### Tasks

- [x] **Task 1 — Admission BC: `UpdateAdmissionLocationStatus` command + handler**
  - Files: `src/Context/Admission/Application/Command/UpdateAdmissionLocationStatus/UpdateAdmissionLocationStatus.php` (new), `UpdateAdmissionLocationStatusHandler.php` (new)
  - Command: `final readonly class UpdateAdmissionLocationStatus implements CommandInterface` with `(public string $clinicId, public string $admissionId, public string $newLocationStatus)`.
  - Handler: `#[AsMessageHandler]`, inject `AdmissionRepositoryInterface` + `ClockInterface`. `__invoke`: `$admission = $this->admissionRepository->get(ClinicId::fromString($command->clinicId), AdmissionId::fromString($command->admissionId))`, `$now = $this->clock->now()`, `$admission->updateLocationStatus(new LocationStatus(LocationStatusValue::from($command->newLocationStatus), $now), $now)`, `$this->admissionRepository->save($admission)`.

- [x] **Task 2 — Consultation BC port: extend `AdmissionServiceCoordinatorInterface`**
  - File: `src/Context/Consultation/Application/Port/AdmissionServiceCoordinatorInterface.php`
  - Action: Add `clinicId` parameter to `updateLocationStatus()`: `public function updateLocationStatus(string $admissionId, string $newLocationStatus, string $clinicId): void;` (replaces the `triggeredByUserId` parameter which is unused — remove it to keep the interface clean).
  - Notes: This is a BC-internal port, no external callers. Update all implementations and call sites.

- [x] **Task 3 — Implement `MessengerAdmissionServiceCoordinator.updateLocationStatus()`**
  - File: `src/Context/Consultation/Infrastructure/Adapter/Admission/MessengerAdmissionServiceCoordinator.php`
  - Action: Inject `CommandBusInterface $commandBus`. Implement: `$this->commandBus->dispatch(new UpdateAdmissionLocationStatus(clinicId: $clinicId, admissionId: $admissionId, newLocationStatus: $newLocationStatus));`
  - Add `use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatus;` and `use App\Shared\Application\Bus\CommandBusInterface;`.

- [x] **Task 4 — `StartConsultationFromAdmissionHandler`: inject coordinator and call it**
  - File: `src/Context/Consultation/Application/Command/StartConsultationFromAdmission/StartConsultationFromAdmissionHandler.php`
  - Action: Add `private AdmissionServiceCoordinatorInterface $admissionServiceCoordinator` to constructor. After `$this->consultations->save($consultation)`, add: `$this->admissionServiceCoordinator->updateLocationStatus($command->admissionId, 'in_consultation_room', $admissionContext->clinicId);`
  - Notes: `$admissionContext->clinicId` is already resolved earlier in the handler.

- [x] **Task 5 — Tenancy fix (implement in this sub-order: 5a → 5b → 5c → 5d → 5e)**
  - **5a — `GetConsultationDetails.php`**: add `public string $clinicId` to constructor.
  - **5b — `ConsultationReadRepositoryInterface.php`**: change signature to `findById(ConsultationId $consultationId, ClinicId $clinicId): ConsultationDetailsDTO;`. Update docstring: `@throws \DomainException if consultation not found or does not belong to $clinicId`.
  - **5c — `DoctrineConsultationReadRepository.php`**: add `$clinicIdBinary = Uuid::fromString($clinicId->toString())->toBinary()` and `AND clinic_id = :clinicId` to the main SQL (`WHERE id = :id AND clinic_id = :clinicId`), bind `'clinicId' => $clinicIdBinary`.
  - **5d — `GetConsultationDetailsHandler.php`**: pass `ClinicId::fromString($query->clinicId)` as second arg to `findById()`.
  - **5e — `ConsultationDetailsController.php`**: inject `CurrentClinicContextInterface $currentClinicContext`. In `__invoke`: `$currentClinicId = $this->currentClinicContext->getCurrentClinicId(); \assert(null !== $currentClinicId);` then `new GetConsultationDetails($id, $currentClinicId->toString())`.

- [x] **Task 6 — New `StartConsultationFromAdmissionController`**
  - File: `src/Presentation/Clinic/Controller/Consultation/Start/StartConsultationFromAdmissionController.php` (new)
  - Route: `#[Route('/admission/{admissionId}/start-consultation', name: 'clinic_consultation_start_from_admission', methods: ['POST'])]`
  - Body (exact sequence):
    1. CSRF: `$token = $request->request->getString('_token'); if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('consultation_start', $token))) { $this->addFlash('error', 'Jeton de sécurité invalide.'); return $this->redirectToRoute('clinic_admission_queue'); }`
    2. User: `$user = $this->getUser(); \assert($user instanceof SecurityUser);`
    3. Dispatch + redirect: wrap in try/catch `\DomainException` → `$this->addFlash('error', $e->getMessage()); return $this->redirectToRoute('clinic_admission_queue');`. On success: `return $this->redirectToRoute('clinic_consultation_details', ['id' => $consultationId]);`
  - Constructor deps: `CommandBusInterface`, `CsrfTokenManagerInterface`.
  - Notes: The `StartConsultationFromAdmissionHandler` validates that the admission belongs to the current user's clinic implicitly — it checks practitioner eligibility against the admission's `clinicId`. If a user submits an `admissionId` from another clinic, the eligibility check will fail (they are not a vet at that clinic) → `DomainException` → flash error + redirect. This provides implicit tenancy protection; no explicit clinicId check is required in the controller.

- [x] **Task 7 — Template: wire "Prendre en charge" button**
  - File: `templates/clinic/admission/queue.html.twig`
  - In the `waitExpanded` macro, replace the inert `<button class="wait-split-main" type="button">→ Prendre en charge</button>` with:
    ```twig
    <form method="post"
          action="{{ path('clinic_consultation_start_from_admission', {admissionId: entry.admissionId}) }}"
          style="display:contents;">
      <input type="hidden" name="_token" value="{{ csrf_token('consultation_start') }}">
      <button class="wait-split-main" type="submit">→ Prendre en charge</button>
    </form>
    ```

- [x] **Task 8 — Tests** *(implement last, after Tasks 1–7)*
  - **`UpdateAdmissionLocationStatusHandlerTest`** (new, `tests/Unit/Context/Admission/`):
    - Mock `AdmissionRepositoryInterface` + `ClockInterface` (stub).
    - `testUpdatesLocationStatusToInConsultationRoom()`: open an `Admission` via `Admission::open()`, configure `admissionRepository->get()` to return it, call handler, assert `admissionRepository->save()` called once, assert `$admission->locationStatus()->value() === LocationStatusValue::InConsultationRoom`.
    - `testThrowsWhenAdmissionNotFound()`: configure `admissionRepository->get()` to throw `AdmissionNotFoundException`, assert `\DomainException` propagates.
  - **`StartConsultationFromAdmissionHandlerTest`** (update):
    - Add `AdmissionServiceCoordinatorInterface&MockObject $coordinator` to `setUp()`. Rebuild handler with new dep.
    - `testCallsUpdateLocationStatusAfterSave()`: assert `$coordinator->updateLocationStatus('in_consultation_room', ...)` called once. Assert it is called AFTER `$consultations->save()` (use `InOrder` mock assertion or verify both called).
    - Ensure all existing tests (happy path, eligibility failure) still pass with the new dep stubbed.
  - **`GetConsultationDetailsHandlerTest`** (integration update):
    - Update all existing calls: `new GetConsultationDetails($consultationId)` → `new GetConsultationDetails($consultationId, $clinicId)`.
    - `testThrowsWhenConsultationBelongsToDifferentClinic()`: use `ConsultationEntityFactory` to create a consultation with `clinicId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'`. Call handler with same `consultationId` but `clinicId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'`. Assert `\DomainException` thrown with message containing "not found".

### Acceptance Criteria

- [x] **AC 1 — Happy path: vet clicks "Prendre en charge"**
  Given an active admission in "En attente" and the connected user holds the VETERINARY role,
  when they click "→ Prendre en charge",
  then a POST is sent to `clinic_consultation_start_from_admission`, a `Consultation` is created, the admission's `locationStatus` becomes `in_consultation_room`, the user is redirected to the consultation details page, and on the next queue load the card appears in the "Pris en charge" column.

- [x] **AC 2 — Non-vet user**
  Given the connected user does NOT hold the VETERINARY role,
  when they click "→ Prendre en charge" and the form is submitted,
  then the controller catches the `DomainException`, adds an error flash, and redirects to `clinic_admission_queue`. No consultation is created.

- [x] **AC 3 — CSRF invalid**
  Given any admission,
  when a POST to `clinic_consultation_start_from_admission` is made with an invalid `_token`,
  then the controller returns a redirect to `clinic_admission_queue` with an error flash. No consultation created.

- [x] **AC 4 — Card moves to "Pris en charge" column**
  Given AC 1 succeeded and the queue page is reloaded,
  when the page renders,
  then the admission card is in `consultEntries` (filtered by `locationStatus == 'in_consultation_room'`) and appears under the "Pris en charge" section. The "En attente" column no longer contains this card.

- [x] **AC 5 — Tenancy: cross-clinic read blocked**
  Given a consultation belonging to clinic A,
  when a user of clinic B hits `/clinic/consultations/{id}`,
  then `GetConsultationDetails` is called with clinic B's `clinicId`, the SQL finds no row matching both `id` and `clinic_id`, and a `DomainException` is thrown (404 or error page).

## Additional Context

### Dependencies

- `AdmissionRepositoryInterface` — already wired in Admission BC command handlers
- `AdmissionServiceCoordinatorInterface` — already registered in DI, just needs implementation
- `CommandBusInterface` — already used throughout Infrastructure adapters
- `CurrentClinicContextInterface` — already used in Presentation controllers
- `CsrfTokenManagerInterface` — already used in `CloseAdmissionController` (pattern reference)
- `LocationStatus` + `LocationStatusValue` — already in Admission BC domain

### Testing Strategy

- Unit tests for `UpdateAdmissionLocationStatusHandler` and updated `StartConsultationFromAdmissionHandler` (no DB)
- Integration test update for `GetConsultationDetailsHandler` to assert clinic isolation
- Manual smoke test: open flux, click "Prendre en charge" as a vet → verify redirect to consultation page and card moves to "Pris en charge" column on reload

### Notes

- The `AdmissionServiceCoordinatorInterface` port signature currently has `triggeredByUserId` which is unused. Task 2 replaces it with `clinicId` which is actually needed. This is a clean fix, not a breaking change (internal port, no external callers).
- `StartConsultationFromAppointmentController` has no CSRF — not fixed in this spec (out of scope) but worth noting as a future cleanup.
- The delete+reinsert in `DoctrineConsultationRepository.save()` is a known PoC debt — not fixed here but it means saving a consultation after adding notes re-inserts all notes. Not a problem for the start flow (no notes on creation) but must be addressed before the note-recording features are wired.
- `ConsultationDetailsController` currently has no 404 handling for the `DomainException` from `findById` — it would propagate as a 500. Out of scope, but the tenancy fix makes the 500 scenario more reachable.
- **Duplicate consultation guard** is out of scope. Clicking "Prendre en charge" twice will create two `Consultation` rows for the same admission. This is a known limitation to be addressed in a follow-up story (add unique constraint on `(admission_id, status='active')` + guard in handler).
- **Coordinator error policy**: if `updateLocationStatus()` throws (e.g., admission not found after save), the exception propagates out of `StartConsultationFromAdmissionHandler`, rolls back the Doctrine transaction (command bus has `doctrine_transaction` middleware), and the controller catches `\DomainException` → flash error. This is acceptable — no state mismatch since the whole operation is atomic.
- **ClinicId VOs are BC-local**: each BC defines its own `ClinicId` value object. The coordinator receives and passes `clinicId` as a `string`; the Admission BC handler converts it to `App\Context\Admission\Domain\ValueObject\ClinicId` locally. No cross-BC VO comparison ever occurs.
