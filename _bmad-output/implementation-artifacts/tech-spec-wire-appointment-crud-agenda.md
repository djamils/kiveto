---
title: 'Wire Appointment CRUD on Agenda Page'
slug: 'wire-appointment-crud-agenda'
created: '2026-04-13'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - PHP 8.4
  - Symfony 7.4 (Controllers, CSRF, Messenger CommandBus)
  - Stimulus 3.2.2 (autocomplete controllers, appointment_form_controller)
  - Twig 3 (modal template, form theme)
  - Tailwind CSS v4 + Kiveto tokens
  - AssetMapper importmap
  - PHPUnit 12, Foundry 2.x
files_to_modify:
  - src/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointment.php
  - src/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandler.php
  - src/Context/Scheduling/Domain/Appointment.php
  - src/Context/Scheduling/Domain/Event/AppointmentScheduled.php
  - src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/AppointmentItem.php
  - src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/GetAgendaForClinicDateRangeHandler.php
  - src/Context/Scheduling/Application/Query/GetAppointmentDetails/AppointmentDetails.php
  - src/Context/Scheduling/Application/Query/GetAppointmentDetails/GetAppointmentDetailsHandler.php
  - src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Entity/AppointmentEntity.php
  - src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Mapper/AppointmentMapper.php
  - src/Presentation/Clinic/Controller/Scheduling/Appointment/CreateAppointmentController.php
  - templates/clinic/scheduling/agenda/index.html.twig
  - templates/clinic/scheduling/_modal_new_appointment.html.twig
  - templates/clinic/scheduling/_agenda.html.twig (dashboard widget — NOT the agenda grid)
  - assets/js/pages/scheduling/agenda.js
  - fixtures/Context/Scheduling/AppointmentFactory.php
  - fixtures/Context/Scheduling/Factory/AppointmentEntityFactory.php
files_to_create:
  - src/Context/Scheduling/Application/Command/RescheduleAppointment/RescheduleAppointment.php
  - src/Context/Scheduling/Application/Command/RescheduleAppointment/RescheduleAppointmentHandler.php
  - src/Presentation/Clinic/Controller/Scheduling/Appointment/RescheduleAppointmentController.php
  - src/Presentation/Clinic/Controller/Scheduling/Appointment/CancelAppointmentController.php
  - src/Presentation/Clinic/Controller/Api/Clinic/Animals/SearchAnimalsApiController.php
  - assets/controllers/appointment_form_controller.js
  - assets/controllers/animal_search_autocomplete_controller.js
  - migrations/Scheduling/VersionXXXX_practitioner_not_null.php
  - src/Context/Scheduling/Application/Query/GetAppointmentClinicId/GetAppointmentClinicId.php
  - src/Context/Scheduling/Application/Query/GetAppointmentClinicId/GetAppointmentClinicIdHandler.php
  - tests/Unit/Context/Scheduling/Application/Command/RescheduleAppointment/RescheduleAppointmentHandlerTest.php
code_patterns:
  - Single-action controllers (__invoke, no business logic)
  - JSON responses for agenda endpoints (not Turbo Stream — grid is JS-rendered)
  - Request body sent as form-data (application/x-www-form-urlencoded), response as JSON
  - Stimulus controller with CustomEvent dispatch for cross-controller communication
  - Stimulus autocomplete controller (debounce, fetch, keyboard nav, hidden input)
  - Command/Handler via Messenger (CommandBusInterface::dispatch), events auto-published via repository save
  - CSRF validation pattern (CsrfTokenManagerInterface, token id 'appointment')
test_patterns:
  - Unit: Handler tested via mocked repository + checker interfaces
  - Unit: API controller tested via WebTestCase or functional test
  - Integration: appointment fixtures via SchedulingStory
  - self::assertSame() everywhere, never assertEquals()
---

# Tech-Spec: Wire Appointment CRUD on Agenda Page

**Created:** 2026-04-13

## Overview

### Problem Statement

Le flow RDV sur la page agenda n'est pas câblé : les fonctions `openNewRdv()` et `openNewRdvGlobal()` sont des stubs (toast "bientôt disponible"), le modal existe mais n'est pas inclus dans le template, les champs owner/animal sont des UUID bruts sans autocomplete, pas de CSRF, pas de retour d'erreur inline, pas de command reschedule exposée, et pas d'API de recherche animaux. Le `practitionerUserId` est nullable partout dans le domain alors qu'un RDV sans praticien n'a pas de sens métier en v1. L'agenda est la feature à plus haute fréquence d'utilisation quotidienne — chaque friction dans le flow RDV se multiplie par le nombre de consultations/jour.

### Solution

Câbler le cycle de vie complet du RDV (créer, modifier=reschedule, annuler) dans la page agenda :
- Réutiliser le modal existant en mode create/edit piloté par un appointment_id optionnel
- Autocompletes chaînés owner → animal (réutilise le pattern `client_search_autocomplete_controller.js`)
- Select praticien alimenté depuis le payload agenda (pas d'aller-retour serveur)
- Pré-remplissage intelligent (slot libre → date+heure+praticien, bouton global → date seule)
- Réponses JSON + injection erreurs via Stimulus (la grille est rendue en JS, pas en Twig — Turbo Stream ne s'applique pas)
- Backend : `RescheduleAppointment` command + handler, `CancelAppointmentController`, API search animaux, CSRF sur les 3 endpoints
- Breaking change : `practitionerUserId` rendu non-nullable dans tout le domain Scheduling
- Annulation inline depuis le popup détail (confirmation dans le popup, pas un second modal)

### Scope

**In Scope:**

- Créer RDV via modal (clic slot libre ou bouton global), CSRF, erreurs JSON inline
- Modifier RDV = reschedule uniquement (date, heure, durée, praticien) — même modal en mode edit, POST
- Annuler RDV via popup détail → confirmation inline → JSON response → JS re-render
- Autocomplete chaîné : owner (nom, téléphone, email) → animal (filtré par owner, désactivé si owner vide)
- Select praticien depuis la liste vétérinaires du payload agenda
- Pré-remplissage : slot libre → date+heure+praticien colonne ; bouton global → date du jour affiché
- `RescheduleAppointment` command + handler avec validation "RDV passé non modifiable" côté serveur
- API `/api/clinic/animals/search?ownerId=...&q=...` pour l'autocomplete animal
- CSRF sur create, reschedule, cancel (token id `'appointment'`)
- Multi-tenant safety : vérification clinic ownership sur les 3 endpoints
- Breaking change : `practitionerUserId` non-nullable dans tout le BC Scheduling (17 fichiers code + tests)
- `appointment_form_controller.js` Stimulus dédié (séparé de agenda.js)
- Enrichir payload agenda avec `ownerLabel`, `animalLabel`, `practitionerLabel`

**Out of Scope:**

- Modifier owner/animal/motif d'un RDV existant (workflow = annuler + recréer)
- Drag & drop pour déplacer les RDV dans la grille
- Gestion des récurrences
- Notifications client (email/SMS)
- Intégration facturation
- Liste exhaustive de motifs data-driven (les options restent hardcodées dans le select pour v1)
- Fallback HTML pour navigateurs sans JS (JS actif est un prérequis fonctionnel)
- Double-clic sur RDV pour éditer directement (power-user shortcut, v2)
- Migrer `window.__agendaVets` vers un Stimulus `data-vets` attribute (follow-up)

## Context for Development

### Codebase Patterns (verified)

- **Appointment aggregate** (`src/Context/Scheduling/Domain/Appointment.php`): `schedule()` factory, `reschedule(TimeSlot)` method, `changePractitionerAssignee(PractitionerAssignee)` method, `cancel()` method. Status enum: PLANNED, CANCELLED, NO_SHOW, COMPLETED. `isTerminal()` guards on cancel/reschedule. `reschedule()` does early return if same TimeSlot (no-op). **`cancel()` is idempotent for CANCELLED status** (early return, not throw) — but throws `DomainException` for other terminal statuses (NO_SHOW, COMPLETED).

- **Event publishing pattern** : domain events are recorded via `$this->recordDomainEvent()` inside aggregate methods. Events are flushed automatically by the repository's `save()` method. **No `DomainEventPublisher` injection in handlers** — the repository handles publication.

- **ScheduleAppointmentHandler**: validates owner existence, animal existence, practitioner membership eligibility, appointment conflicts (via checker interfaces). Returns appointment ID string. **Command DTO uses `\DateTimeImmutable $startsAtUtc`** (not string).

- **CancelAppointmentHandler**: loads appointment by ID only — **no clinicId verification**. Calls `cancel()`, saves, closes linked waiting room entry. **The command `CancelAppointment` has only `string $appointmentId`** — no clinicId field.

- **CreateAppointmentController**: reads form fields via `$request->request->getString(...)` (**form-data**, not JSON body). Controller does `new \DateTimeImmutable($startsAtRaw)` before dispatching.

- **Request/Response convention** : requests sent as `application/x-www-form-urlencoded` (standard form POST), responses returned as `JsonResponse`. Controllers read `$request->request` bag.

- **Agenda JS** (`assets/js/pages/scheduling/agenda.js`): ~1000 lignes. `openNewRdv(dateStr, time)` has 2 params, no practitioner context. Free slot `renderFreeSlotBlock` has `vet.userId` in closure scope but does not pass it to `openNewRdv`. `parseUtcDateTime()` expects `Y-m-d H:i:s` format.

### Files Inventory — Disambiguation

| File | What it is | NOT to be confused with |
| ---- | ---------- | ----------------------- |
| `templates/clinic/scheduling/agenda/index.html.twig` | Main agenda page template (turbo-frame, JSON payload, nav) | — |
| `templates/clinic/scheduling/_agenda.html.twig` | **Dashboard widget** — simple card list of today's appointments | NOT the agenda grid |
| `templates/clinic/scheduling/_modal_new_appointment.html.twig` | New appointment modal — to be rewritten | — |
| `assets/js/pages/scheduling/agenda.js` | JS module — renders the grid, handles nav, stubs | — |

### Existing — Do Not Recreate

| Component | Path | Notes |
| ---- | ---- | ----- |
| `CancelAppointmentHandler` | `src/Context/Scheduling/Application/Command/CancelAppointment/CancelAppointmentHandler.php` | Handler complet, soft delete + policy salle d'attente |
| `ScheduleAppointmentHandler` | `src/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandler.php` | To modify (non-nullable), not recreate |
| `Appointment::reschedule(TimeSlot)` | `src/Context/Scheduling/Domain/Appointment.php` | Domain method, emits `AppointmentRescheduled` |
| `Appointment::changePractitionerAssignee()` | `src/Context/Scheduling/Domain/Appointment.php` | Domain method, emits `AppointmentPractitionerAssigneeChanged` |
| `Appointment::cancel()` | `src/Context/Scheduling/Domain/Appointment.php` | Idempotent for CANCELLED, throws for other terminal |
| All domain events | `src/Context/Scheduling/Domain/Event/` | `AppointmentRescheduled`, `AppointmentCancelled`, `AppointmentPractitionerAssigneeChanged` |
| `AppointmentConflictCheckerInterface` | `src/Context/Scheduling/Application/Port/` | Already has `?AppointmentId $excludeAppointmentId = null` |
| `DbalAppointmentConflictChecker` | `src/Context/Scheduling/Infrastructure/Adapter/` | Already implements `AND id != :excludeId` |
| `SearchAnimalsCriteria` | `src/Context/Animal/Application/Query/SearchAnimals/` | Already has `?string $ownerClientId` |
| `AnimalReadRepositoryInterface::search()` | `src/Context/Animal/Application/Port/` | Already supports ownerClientId filter |
| `modal.js` | `assets/js/ui/modal.js` | `modal.open(id)` / `modal.close(id)` / `modal.confirm()` |

### Technical Decisions

- **D1 — Modifier = reschedule (date/heure/durée) + changePractitionerAssignee seulement.** Owner/animal/motif non modifiables — annuler + recréer. Le handler reschedule orchestre `reschedule(TimeSlot)` + `changePractitionerAssignee()`, les deux no-op si inchangés. **Le reschedule ne valide PAS owner/animal existence** — ces champs ne changent pas.

- **D2 — Validation "RDV passé non modifiable" côté serveur.** Le handler vérifie `appointment.timeSlot.startsAtUtc < clock.now()` et throw. L'UI désactive le bouton mais le serveur est la source de vérité.

- **D3 — Autocompletes chaînés owner → animal.** `animal_search_autocomplete_controller` a un `ownerIdValue`. Sur `owner:changed` CustomEvent, reset animal + update `ownerIdValue`. Owner vide → animal désactivé.

- **D4 — Select praticien côté client.** Liste depuis payload agenda. Guard minimum: `const vets = window.__agendaVets ?? [];` — si vide, select désactivé avec message. Follow-up: migrer vers Stimulus data attribute.

- **D5 — Même modal create/edit.** URL templates: `data-create-url` et `data-reschedule-url-template="/scheduling/appointments/:id/reschedule"`. Le Stimulus controller switche l'action au moment de l'ouverture. En mode edit, owner + animal en lecture seule.

- **D6 — Form-data request, JSON response.** Les controllers lisent `$request->request` (form-data POST). Les réponses sont `JsonResponse`. Le fetch JS envoie `Content-Type: application/x-www-form-urlencoded` avec `new URLSearchParams(formData)`. **`startsAtUtc` en réponse JSON : format ISO 8601 (`Y-m-d\TH:i:s\Z`).** Le parser `parseUtcDateTime()` dans agenda.js doit être audité/adapté pour accepter ISO 8601 en plus de `Y-m-d H:i:s`.

  Structure JSON :
  ```json
  // Success create/reschedule
  { "success": true, "appointment": { "id": "uuid", "startsAtUtc": "2026-04-14T09:00:00Z", "durationMinutes": 30, "practitionerUserId": "uuid", "ownerId": "uuid", "animalId": "uuid", "ownerLabel": "Sophie Dupont", "animalLabel": "Rex", "practitionerLabel": "Dr. Martin", "status": "PLANNED", "reason": "Consultation", "notes": "" } }

  // Success cancel
  { "success": true, "appointmentId": "uuid", "alreadyCancelled": false }

  // Error
  { "success": false, "errors": { "global": ["Ce créneau est déjà occupé."], "startsAtUtc": ["La date est obligatoire."] }, "errorCode": "APPOINTMENT_CONFLICT" }
  ```

  Error codes: `APPOINTMENT_CONFLICT`, `APPOINTMENT_TERMINAL_STATUS`, `VALIDATION_FAILED`. Cancel of already-cancelled returns `{ "success": true, "appointmentId": "...", "alreadyCancelled": true }` (idempotent, matches domain behavior).

- **D7 — Annulation inline dans popup détail.** Bouton "Annuler" → confirmation inline + loading state (disabled + spinner) pendant POST. Success → popup ferme, grille re-render. Error → réactive + message. **Submit button on create/reschedule modal also gets disabled/spinner during fetch** (prevents double-click = double RDV).

- **D8 — CSRF token id `'appointment'`.** Token dans hidden field du modal. Envoyé comme `_token` dans le form-data POST body.

- **D9 — API search animaux.** Réutilise `SearchAnimals` existante avec `SearchAnimalsCriteria(ownerClientId: ...)`. Controller retourne un **`AnimalSearchResult` DTO explicite** avec seulement `{id, name, species, breedName}` — pas de sérialisation brute de `AnimalListItemView` (qui a 12 champs).

- **D10 — Self-conflict exclusion.** Déjà implémenté. `RescheduleAppointmentHandler` passe l'ID courant au conflict checker.

- **D11 — POST partout, pas PATCH.**

- **D12 — `appointment_form_controller.js` Stimulus dédié.** Communication via CustomEvents :
  ```
  agenda.js → dispatch 'appointment:open-modal' { detail: { mode, prefill, appointment } }
  appointment_form_controller.js → écoute, ouvre modal, gère form
  appointment_form_controller.js → dispatch 'appointment:saved' { detail: { appointment, action } }
  agenda.js → écoute, met à jour state, re-render grille
  ```

- **D13 — Slots libres : data attributes.** Refactored `openNewRdv` signature:
  ```js
  // Old (stub): openNewRdv(dateStr, time)
  // New: dispatch CustomEvent with full context
  document.dispatchEvent(new CustomEvent('appointment:open-modal', {
    detail: { mode: 'create', prefill: { date, time, practitionerUserId } }
  }));
  ```
  Free slot click: read `data-start-time` + `data-practitioner-id` from the slot element (set during `renderFreeSlotBlock`, vet.userId is in closure scope). Hour-slot click (non-free-slot): pass `practitionerUserId: null` — practitioner select left empty.

- **D14 — Enrichir payload agenda.** `GetAgendaForClinicDateRangeHandler` joins owner name, animal name, and practitioner display name into `AppointmentItem`. New fields: `ownerLabel` (nullable string), `animalLabel` (nullable string), `practitionerLabel` (string). Requires LEFT JOIN on `client__clients`, `animal__animals`, and `identity_access__users` tables. Labels are `null` in the handler when the joined entity is missing (soft-deleted) — presentation layer shows fallback string (e.g., `ownerLabel ?? '[Client supprimé]'`).

- **D15 — Cross-BC LEFT JOINs: deliberate debt.** The `GetAgendaForClinicDateRangeHandler` performs cross-BC JOINs (client, animal, identity_access tables) at the DBAL infra level. This is acceptable in CQRS read-side — the bounded context purity rule applies to the write model, not to denormalized read queries. Perf justification: 1 query vs N+1 for 100-200 appointments per week view. Migration path (not v1): materialized read model `scheduling__agenda_view` projected via domain events. Trigger: DB split by BC or query perf > 200ms.

- **D16 — Tenant guard query.** `GetAppointmentClinicId(string $appointmentId): ?string` — lightweight DBAL query returning only the `clinic_id` for a given appointment. Used by `CancelAppointmentController` and `RescheduleAppointmentController` to verify clinic ownership before dispatching commands. Pattern:
  ```php
  $clinicId = $this->queryBus->ask(new GetAppointmentClinicId($appointmentId));
  if (null === $clinicId || $clinicId !== $currentClinicId->toString()) {
      throw $this->createNotFoundException();
  }
  ```
  Reusable primitive for any future endpoint that touches an appointment. Handler is a one-liner DBAL SELECT.

- **D17 — Single CSRF intention `'appointment'` for all 3 flows.** Token rendered in the modal hidden field `<input type="hidden" name="_token" value="{{ csrf_token('appointment') }}">`. The cancel flow (from the popup detail, not the modal) reads the token from the modal DOM — the modal is always present in the page even when hidden. Pattern in JS:
  ```js
  const csrfToken = document.querySelector('#modalAppointment input[name="_token"]')?.value;
  ```
  No separate token per action — same risk profile, simpler code.

- **D18 — Grid animations on create/reschedule/cancel.** Visual feedback after CRUD operations:
    - **Create success**: new appointment block fades in (opacity 0→1, 300ms) + background flashes `var(--brand-100)` for 1s then fades to normal
    - **Reschedule success**: old position block fades out (300ms), new position fades in + brand flash (same as create)
    - **Cancel success**: block fades out over 400ms, then removed from DOM
    - **Error**: no animation, block stays untouched
    - Implementation: CSS `@keyframes ki-appointment-flash` + agenda.js adds `is-new` or `is-removing` class on the appointment block, class auto-removed after animation via `animationend` listener. ~20 lines CSS + ~15 lines JS.

### Multi-Tenant Safety

Every endpoint that touches an appointment must verify that the appointment belongs to the current clinic. Pattern:

```php
$appointment = $this->repository->findById(AppointmentId::fromString($appointmentId));
if (null === $appointment || !$appointment->clinicId()->equals($currentClinicId)) {
    throw $this->createNotFoundException();
}
```

| Endpoint | Current state | Required change |
| ---- | ---- | ---- |
| `CreateAppointmentController` | Passes `clinicId` to command — handler validates | OK, no change |
| `RescheduleAppointmentController` | New | clinicId passed to command, handler verifies. Controller also uses `GetAppointmentClinicId` tenant guard as defense-in-depth. |
| `CancelAppointmentController` | New controller, existing handler has **no clinic check** | Controller uses `GetAppointmentClinicId` tenant guard query before dispatching. The handler's `CancelAppointment` command has no `clinicId` field — guard is in the controller. |

**Test**: add a security test case for each controller — attempt to cancel/reschedule an appointment from another clinic → 404.

### Breaking Change: practitionerUserId Non-Nullable

**17 code files + associated tests.** Un RDV sans praticien n'a pas de sens métier en v1.

| Layer | File | Change |
| ----- | ---- | ------ |
| Command DTO | `ScheduleAppointment.php` | `?string` → `string` |
| Handler | `ScheduleAppointmentHandler.php` | **De-indent** the validation block: remove outer `if (null !== $command->practitionerUserId)` and its `else` branch, keep inner logic (membership check, conflict check, PractitionerAssignee creation) as unconditional code |
| Domain | `Appointment.php` | `?PractitionerAssignee` → `PractitionerAssignee` in property, `schedule()`, `reconstitute()`. Remove null checks in `changePractitionerAssignee()`. **Pre-task: grep `unassignPractitioner` across codebase, remove all call sites**, then remove the method. |
| Event | `AppointmentScheduled.php` | `?string` → `string` |
| Query DTO | `AppointmentItem.php` | `?string` → `string` + add `?string $ownerLabel`, `?string $animalLabel`, `string $practitionerLabel` |
| Query Handler | `GetAgendaForClinicDateRangeHandler.php` | `nullableString` → `string` + LEFT JOIN for labels |
| Query DTO | `AppointmentDetails.php` | `?string` → `string` |
| Query Handler | `GetAppointmentDetailsHandler.php` | `nullableString` → `string` |
| Entity | `AppointmentEntity.php` | `nullable: true` → `nullable: false`, property + getter/setter |
| Mapper | `AppointmentMapper.php` | Remove null checks in `toDomain()`, `toEntity()`, `updateEntity()` |
| Controller | `CreateAppointmentController.php` | Validate required, remove `?: null` |
| Factory | `AppointmentFactory.php` | `?string` → `string`, remove null check |
| Factory | `AppointmentEntityFactory.php` | `?string` → `string`, default non-null |
| Migration | New `VersionXXXX.php` | See migration strategy below |
| Template | `_modal_new_appointment.html.twig` | "Praticien (optionnel)" → "Praticien" |
| Template | `_agenda.html.twig` (dashboard widget) | Remove `{% if appointment.practitionerUserId %}` |
| Tests | Handler/domain/events tests | Remove null-practitioner test cases |

**Notes**: `GetAgendaForClinicDateRange.practitionerUserId` (query filter param) stays nullable. `AppointmentPractitionerAssigneeChanged.oldPractitionerUserId` stays `?string` (audit trail).

**Migration strategy**:
1. Migration `up()` starts with: `SELECT COUNT(*) FROM scheduling__appointments WHERE practitioner_user_id IS NULL`
2. If count > 0: `throw new \RuntimeException('Found N appointments with NULL practitioner_user_id. Run data:fix-null-practitioners before migrating.')`
3. If count = 0: `ALTER TABLE scheduling__appointments MODIFY practitioner_user_id BINARY(16) NOT NULL`
4. Migration `down()`: `ALTER TABLE scheduling__appointments MODIFY practitioner_user_id BINARY(16) DEFAULT NULL`

## Implementation Plan

### Tasks

Ordered as **vertical slices** — each phase delivers a testable feature end-to-end.

#### Phase 0 — Breaking Change: practitionerUserId non-nullable (~1h)

- [ ] **Task 0.1: Grep `unassignPractitioner` across entire codebase**
    - Action: Verify all call sites. Expected: only `Appointment.php` (definition) + `AppointmentTest.php` (test). If more found, update each call site.

- [ ] **Task 0.2: Tighten domain Appointment aggregate**
    - File: `src/Context/Scheduling/Domain/Appointment.php`
    - Action: `?PractitionerAssignee` → `PractitionerAssignee` in property, `schedule()`, `reconstitute()`, getter. Remove null checks in `changePractitionerAssignee()` (line 139 null-safe operator). Remove `unassignPractitioner()` method entirely.

- [ ] **Task 0.3: Tighten ScheduleAppointment command + handler**
    - Files: `ScheduleAppointment.php`, `ScheduleAppointmentHandler.php`
    - Action: Command: `?string $practitionerUserId` → `string`. Handler: **de-indent** — remove outer `if (null !== $command->practitionerUserId) {` and the `} else {` branch. Keep the inner logic (UserId creation, membership eligibility check, conflict check, PractitionerAssignee creation) as unconditional. Pseudo-diff:
      ```diff
      -$practitionerAssignee = null;
      -if (null !== $command->practitionerUserId) {
           $practitionerUserId = UserId::fromString($command->practitionerUserId);
           // ... eligibility check, conflict check ...
           $practitionerAssignee = new PractitionerAssignee($practitionerUserId);
      -} else {
      -    $practitionerAssignee = null;
      -}
      ```

- [ ] **Task 0.4: Tighten event + query DTOs + handlers + entity + mapper**
    - Action: Apply `?string` → `string` / `nullableString` → `string` / `nullable: false` across all files listed in the Breaking Change table. Add `ownerLabel`, `animalLabel`, `practitionerLabel` to `AppointmentItem` + LEFT JOINs in `GetAgendaForClinicDateRangeHandler`.

- [ ] **Task 0.5: Doctrine migration**
    - File: new `migrations/Scheduling/VersionXXXX.php`
    - Action: Pre-check for NULL rows (abort if found), then `ALTER ... NOT NULL`.

- [ ] **Task 0.6: Update fixtures, factories, templates, tests**
    - Action: All files from Breaking Change table. Remove null-practitioner test cases. Remove template guards.

- [ ] **Task 0.7: Run `make ci` — validate green**

#### Phase 1 — Create Appointment full stack (~2h)

Backend + modal + Stimulus + wiring — the "Create" feature works end-to-end.

- [ ] **Task 1.1: Refactor CreateAppointmentController → JSON + CSRF + multi-tenant**
    - File: `src/Presentation/Clinic/Controller/Scheduling/Appointment/CreateAppointmentController.php`
    - Action: Add `CsrfTokenManagerInterface`. Validate `_token` from `$request->request` with token id `'appointment'`. On success: build appointment JSON (including labels) + return `JsonResponse` 200. On error: `JsonResponse` 422 with `{success: false, errors, errorCode}`. Map `\InvalidArgumentException` → `VALIDATION_FAILED`, domain conflict exception → `APPOINTMENT_CONFLICT`. Remove redirect + flash. ClinicId already passed to command — no multi-tenant change needed.

- [ ] **Task 1.2: Create SearchAnimalsApiController**
    - File: `src/Presentation/Clinic/Controller/Api/Clinic/Animals/SearchAnimalsApiController.php`
    - Route: `GET /animals/search` (prefix `/api/clinic`)
    - Action: Clone `SearchClientsApiController`. Require `ownerId` (400 if missing). Use `SearchAnimals` + `SearchAnimalsCriteria(ownerClientId: $ownerId, searchTerm: $q)`. Map each `AnimalListItemView` to explicit `{id, name, species, breedName}` — do NOT serialize the full DTO. Rate-limited via `api_clinic_search`.

- [ ] **Task 1.3: Rewrite _modal_new_appointment.html.twig**
    - File: `templates/clinic/scheduling/_modal_new_appointment.html.twig`
    - Action: Complete rewrite. CSRF hidden `_token`. URL templates as `data-*` attributes on the form. Owner autocomplete + hidden `ownerId`. Animal autocomplete + hidden `animalId`. Practitioner `<select>` (populated by JS). DateTime, duration, reason, notes fields. Error targets. Title/button targets for create/edit mode.

- [ ] **Task 1.4: Include modal in agenda template**
    - File: `templates/clinic/scheduling/agenda/index.html.twig`
    - Action: Add `{{ include('clinic/scheduling/_modal_new_appointment.html.twig') }}`.

- [ ] **Task 1.5: Create animal_search_autocomplete_controller.js**
    - File: `assets/controllers/animal_search_autocomplete_controller.js`
    - Action: Clone + adapt `client_search_autocomplete_controller.js`. `ownerIdValue` Stimulus value. Fetch conditional on non-empty `ownerIdValue`. Listen to `owner:changed` CustomEvent. Display: `name — species (breedName)`.

- [ ] **Task 1.6: Create appointment_form_controller.js**
    - File: `assets/controllers/appointment_form_controller.js`
    - Action: Stimulus controller. Listen `appointment:open-modal`. Populate practitioner select from `window.__agendaVets ?? []` (guard if empty). Manage create/edit mode. Submit via `fetch` with `URLSearchParams` (form-data). Parse JSON response. On success: `modal.close()` + dispatch `appointment:saved`. On error: inject errors. **Disable submit button during fetch** (prevent double-click). Owner change dispatches `owner:changed`.

- [ ] **Task 1.7: Wire agenda.js — create flow**
    - File: `assets/js/pages/scheduling/agenda.js`
    - Action: Replace `openNewRdvGlobal()` stub → dispatch `appointment:open-modal` with `{mode: 'create', prefill: {date: selectedDate}}`. Replace `openNewRdv(dateStr, time)` → dispatch with `{mode: 'create', prefill: {date, time, practitionerUserId: null}}`. Add `data-start-time` + `data-practitioner-id` on free slot blocks in `renderFreeSlotBlock` (vet.userId from closure). Free slot click: read dataset, dispatch with practitionerUserId. Export `window.__agendaVets = _vetIndex`. Listen `appointment:saved` where `action === 'created'` → push to `_appointments`, re-render. **Audit `parseUtcDateTime()`** — add ISO 8601 support (`T` separator, `Z` suffix) alongside existing `Y-m-d H:i:s`.

- [ ] **Task 1.8: Run `make ci` + manual smoke test create**

#### Phase 2 — Reschedule Appointment full stack (~1.5h)

- [ ] **Task 2.1: Create RescheduleAppointment command DTO**
    - File: `src/Context/Scheduling/Application/Command/RescheduleAppointment/RescheduleAppointment.php`
    - Action: `final readonly class` — fields: `string $clinicId`, `string $appointmentId`, `\DateTimeImmutable $startsAtUtc`, `int $durationMinutes`, `string $practitionerUserId`.

- [ ] **Task 2.2: Create RescheduleAppointmentHandler**
    - File: `src/Context/Scheduling/Application/Command/RescheduleAppointment/RescheduleAppointmentHandler.php`
    - Action: `#[AsMessageHandler]`. Inject `AppointmentRepositoryInterface`, `AppointmentConflictCheckerInterface`, `MembershipEligibilityCheckerInterface`, `ClockInterface`. **No `DomainEventPublisher`** — events auto-published via `repository->save()`. Logic:
        1. Load appointment by ID, verify `$appointment->clinicId()` matches command clinicId
        2. **Guard: `$appointment->timeSlot()->startsAtUtc() < $this->clock->now()`** → throw `\DomainException('Impossible de modifier un rendez-vous passé.')`
        3. Build new `TimeSlot($command->startsAtUtc, $command->durationMinutes)`
        4. Build `PractitionerAssignee(UserId::fromString($command->practitionerUserId))`, validate membership
        5. `$this->conflictChecker->hasOverlap($clinicId, $practitionerUserId, $newTimeSlot, excludeAppointmentId: $appointmentId)`
        6. `$appointment->reschedule($newTimeSlot)` (no-op if same)
        7. `$appointment->changePractitionerAssignee($newAssignee)` (no-op if same)
        8. `$this->repository->save($appointment)` — events auto-published

- [ ] **Task 2.3: Create RescheduleAppointmentHandlerTest**
    - Action: 8 test cases (see Testing Strategy).

- [ ] **Task 2.4: Create RescheduleAppointmentController**
    - Route: `POST /scheduling/appointments/{appointmentId}/reschedule`
    - Action: CSRF validation. Read form-data fields. `new \DateTimeImmutable($startsAtRaw)`. Dispatch `RescheduleAppointment`. Return JSON. Multi-tenant: clinicId passed to command, handler verifies.

- [ ] **Task 2.5: Wire agenda.js — edit flow**
    - Action: Add "Modifier" button in popup detail JS. On click: dispatch `appointment:open-modal` with `{mode: 'edit', appointment: currentAppointment}`. Listen `appointment:saved` where `action === 'rescheduled'` → update appointment in `_appointments` array, re-render. **"Modifier" button disabled if `startsAtUtc < now`** (past appointments).

- [ ] **Task 2.6: Run `make ci` + manual smoke test reschedule**

#### Phase 3 — Cancel Appointment full stack (~45min)

- [ ] **Task 3.0: Create GetAppointmentClinicId query + handler**
    - Files: `src/Context/Scheduling/Application/Query/GetAppointmentClinicId/GetAppointmentClinicId.php`, `GetAppointmentClinicIdHandler.php`
    - Action: Lightweight DBAL query: `SELECT BIN_TO_UUID(clinic_id) FROM scheduling__appointments WHERE id = UUID_TO_BIN(:id)`. Returns `?string` (null if not found). Reusable tenant guard primitive.

- [ ] **Task 3.1: Create CancelAppointmentController**
    - Route: `POST /scheduling/appointments/{appointmentId}/cancel`
    - Action: CSRF validation. **Multi-tenant guard via `GetAppointmentClinicId`**: query bus ask, compare with current clinic, 404 if mismatch. Then dispatch `CancelAppointment(appointmentId: $appointmentId)` (command has no clinicId field — don't modify it). Return `{success: true, appointmentId, alreadyCancelled: false}`. Catch `DomainException` (terminal status other than CANCELLED) → `{success: false, errorCode: "APPOINTMENT_TERMINAL_STATUS"}`. **Note**: `cancel()` is idempotent for CANCELLED — if pre-check detects already cancelled, return `{success: true, alreadyCancelled: true}`. **CSRF token read from modal DOM** by the JS cancel flow (D17).

- [ ] **Task 3.2: Wire agenda.js — cancel flow**
    - Action: Add "Annuler le RDV" button in popup detail JS. On click: show inline confirmation ("Confirmer l'annulation ?" + danger button). On confirm: read CSRF token from modal DOM (`#modalAppointment input[name="_token"]`), fetch POST to cancel URL (from `data-cancel-url-template`), disabled + spinner during fetch. On success: dispatch `appointment:saved` with `{action: 'cancelled', appointmentId}`. On error: re-enable button, show message. Listener `appointment:saved` where `action === 'cancelled'` → add `is-removing` class on appointment block (400ms fade out via CSS), on `animationend` remove from `_appointments` + re-render.

- [ ] **Task 3.3: Add grid animation CSS + JS**
    - Files: `assets/styles/pages/scheduling/agenda.css`, `assets/js/pages/scheduling/agenda.js`
    - Action: Add `@keyframes ki-appointment-flash` (brand-100 bg → transparent, 1s). CSS classes: `.is-new` (fade in 300ms + flash), `.is-removing` (fade out 400ms). In agenda.js `appointment:saved` listener: on `created` → find new block, add `.is-new`, remove on `animationend`. On `rescheduled` → `.is-removing` on old, `.is-new` on new after re-render. On `cancelled` → `.is-removing`, remove from state on `animationend`.

- [ ] **Task 3.4: Run `make ci` + manual smoke test cancel + animations**

#### Phase 4 — CI + final validation (~15min)

- [ ] **Task 4.1: Run full `make ci`**
- [ ] **Task 4.2: Run `make assets`**
- [ ] **Task 4.3: Full manual smoke test (all 3 flows + edge cases)**

### Acceptance Criteria

- **AC-Create-Modal** — Given the agenda page, when the user clicks "Nouveau RDV", then the modal opens in create mode with the selected date pre-filled and practitioner select populated.

- **AC-Create-SlotPrefill** — Given a free slot, when clicked, then the modal opens with date, start time, and practitioner pre-filled from the slot's data attributes.

- **AC-Create-Submit** — Given valid form data, when submitted, then the appointment is created, the modal closes, and the RDV appears in the grid without page reload.

- **AC-Create-Conflict** — Given an overlapping time slot, when submitted, then `errorCode: "APPOINTMENT_CONFLICT"` is displayed in the modal banner.

- **AC-Create-CSRF** — Given no valid CSRF token, then 403 Forbidden.

- **AC-Create-DoubleClick** — Given a submit in progress, the submit button is disabled with spinner, preventing duplicate creation.

- **AC-Autocomplete-Owner** — Given the owner field, when typing 2+ chars, then debounced search shows matching clients.

- **AC-Autocomplete-Animal** — Given an owner selected, the animal field searches by owner. Given no owner, the animal field is disabled.

- **AC-Autocomplete-Chain** — Given owner changed, then animal selection is cleared and reset.

- **AC-Reschedule-Modal** — Given a PLANNED appointment, when "Modifier" clicked, then modal opens in edit mode with fields pre-filled, owner/animal read-only, labels displayed (not UUIDs).

- **AC-Reschedule-Submit** — Given valid data, when submitted, then appointment rescheduled, modal closes, grid updated.

- **AC-Reschedule-Past** — Given past appointment, "Modifier" button is disabled in UI, and server rejects with `APPOINTMENT_TERMINAL_STATUS`.

- **AC-Reschedule-Terminal** — Given CANCELLED/NO_SHOW appointment, server rejects reschedule.

- **AC-Reschedule-NoOp** — Given same timeslot + practitioner, submit succeeds silently (domain no-op).

- **AC-Cancel-Inline** — Given PLANNED appointment, "Annuler" shows inline confirmation with loading state on confirm.

- **AC-Cancel-Submit** — Given confirmation, appointment cancelled, popup closes, RDV removed from grid.

- **AC-Cancel-Idempotent** — Given already CANCELLED appointment, cancel returns `{success: true, alreadyCancelled: true}`.

- **AC-MultiTenant-Cancel** — Given appointment from another clinic, cancel returns 404.

- **AC-MultiTenant-Reschedule** — Given appointment from another clinic, reschedule returns 404.

- **AC-Practitioner-Required** — Given create/reschedule without practitioner, server rejects with `VALIDATION_FAILED`.

- **AC-AnimalSearch-API** — Given valid `ownerId` + `q`, returns `{data: [{id, name, species, breedName}], meta: {count}}`.

- **AC-AnimalSearch-NoOwner** — Given no `ownerId`, returns 400.

- **AC-Anim-Create** — Given a successful create, the new appointment block fades in and flashes brand color for 1s.

- **AC-Anim-Reschedule** — Given a successful reschedule, the old position fades out and the new position fades in with brand flash.

- **AC-Anim-Cancel** — Given a successful cancel, the appointment block fades out over 400ms before removal.

- **AC-Labels-Agenda** — Given the agenda payload, each appointment includes `ownerLabel`, `animalLabel`, `practitionerLabel` as human-readable strings (not UUIDs).

- **AC-Labels-Fallback** — Given a soft-deleted owner/animal, the label is null in the payload and the UI shows a fallback like "[Client supprimé]".

- **AC-CI-Green** — `make ci` passes all stages.

## Additional Context

### Dependencies

- **AppointmentConflictCheckerInterface** : already has `excludeAppointmentId`, no changes
- **MembershipEligibilityCheckerInterface** : already implemented
- **OwnerExistenceCheckerInterface / AnimalExistenceCheckerInterface** : already implemented (used by create, NOT by reschedule)
- **SearchAnimals query + SearchAnimalsCriteria** : already supports `ownerClientId` filter
- **modal.js** : existing open/close/confirm
- **client_search_autocomplete_controller.js** : pattern cloned for animal search

### Testing Strategy

- **Unit tests — RescheduleAppointmentHandler** (8 cases): happy path, past rejected, conflict, practitioner ineligible, NO_SHOW terminal, CANCELLED terminal, same-timeslot no-op, adjacent slot accepted
- **Unit tests — CancelAppointmentController** (4 cases): happy path, CSRF 403, already cancelled (idempotent), other-clinic 404
- **Non-régression — CreateAppointmentController**: CSRF + JSON (not redirect)
- **Unit tests — SearchAnimalsApiController** (3 cases): valid, missing ownerId 400, rate limited 429
- **Tests practitionerUserId non-nullable**: update handler/domain/events tests
- **Integration tests — GetAgendaForClinicDateRangeHandler labels** (update existing test): RDV with all labels present, RDV with deleted owner (label null), RDV with deleted animal (label null), RDV with disabled practitioner (label still present via LEFT JOIN), tenant isolation (clinic A RDV not in clinic B query)
- **Security tests**: cancel/reschedule with wrong clinicId → 404
- **Manual testing**: full CRUD flow in browser + verify grid animations (create flash, reschedule move, cancel fade)

### Notes

- Events are auto-published via `repository->save()`. Do NOT inject `DomainEventPublisher` in handlers.
- `CancelAppointment` command has only `appointmentId` — no `clinicId`. Multi-tenant check is in the controller.
- `cancel()` is idempotent for CANCELLED status (early return). The API reflects this with `alreadyCancelled: true`.
- `parseUtcDateTime()` in agenda.js must be updated to accept ISO 8601 alongside `Y-m-d H:i:s`.
- Requests: `application/x-www-form-urlencoded`. Responses: `application/json`.
- Submit buttons disabled during fetch on all forms (create, reschedule, cancel confirm).
- `Appointment::unassignPractitioner()` removed. Grep first to verify no other call sites.
- `_agenda.html.twig` is the **dashboard widget**, not the agenda grid.
- `window.__agendaVets` guarded with `?? []`. Follow-up: migrate to Stimulus data attribute.
- Cross-BC LEFT JOINs in `GetAgendaForClinicDateRangeHandler` are deliberate CQRS read-side debt. See D15.
- Single CSRF intention `'appointment'` shared by create/reschedule/cancel. Cancel reads token from modal DOM. See D17.
- `GetAppointmentClinicId` is a reusable tenant guard query primitive. See D16.
- Labels are null in query handler when source entity is missing. Fallback strings are presentation-layer responsibility.
