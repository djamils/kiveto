---
title: 'Waiting Room — Real Data Wire-up & Stimulus Refactor'
slug: 'waiting-room-wire-up'
created: '2026-04-19'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4, 5]
tech_stack: ['PHP 8.5', 'Symfony 7.4', 'Twig', 'Turbo 7.3', 'Stimulus', 'Tailwind CSS v4']
files_to_modify:
  - 'src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/WaitingRoomController.php'
  - 'templates/clinic/scheduling/waiting-room/index.html.twig'
  - 'templates/clinic/scheduling/waiting-room/_topbar.html.twig'
  - 'templates/clinic/scheduling/waiting-room/_queue_panel.html.twig'
  - 'templates/clinic/scheduling/waiting-room/_control_panel.html.twig'
  - 'templates/clinic/scheduling/waiting-room/_modals.html.twig'
  - 'fixtures/Dataset/ClinicDataset.php'
  - 'fixtures/Context/Scheduling/Story/SchedulingStory.php'
files_to_create:
  - 'src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/WaitingRoomQueueController.php'
  - 'src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/WaitingRoomEntryDetailsController.php'
  - 'src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/CreateWalkInController.php'
  - 'src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/UpdateTriageController.php'
  - 'src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/StartServiceFromWaitingRoomController.php'
  - 'src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/CloseEntryFromWaitingRoomController.php'
  - 'templates/clinic/scheduling/waiting-room/_queue_frame.html.twig'
  - 'templates/clinic/scheduling/waiting-room/_entry_details.html.twig'
  - 'assets/controllers/auto_refresh_controller.js'
  - 'assets/controllers/waiting_room_controller.js'
  - 'fixtures/Context/Scheduling/Story/WaitingRoomStory.php'
files_to_delete:
  - 'assets/js/pages/scheduling/waiting-room.js'
code_patterns:
  - 'Single-action controllers (__invoke only), one route per class'
  - 'QueryBus::ask() for reads, CommandBus::dispatch() for writes'
  - 'Turbo Frame SSR: dedicated frame-only route, main page embeds <turbo-frame src="..." refresh="morph" data-turbo-action="replace">'
  - 'Status bar stats inside <turbo-frame id="waiting-queue"> — refresh automatically with queue, zero drift'
  - 'Detail panel: <a href data-turbo-frame="patient-detail"> + <div id="patient-detail-panel" class="is-hidden"> wrapper'
  - 'auto-refresh Stimulus controller placed directly on <turbo-frame> — this.element.reload() is TurboFrameElement API'
  - 'Filter tabs: <a href="?filter=" data-turbo-frame="waiting-queue"> — server-side filtering, zero client-side filter JS'
  - 'Modal pattern: class="modal-overlay hidden" + Stimulus waiting-room controller open/close'
  - 'Post/Redirect/Get for all form actions — redirect to /scheduling/waiting-room, frame rehydrates from src'
  - 'CSRF tokens on all POST forms — isCsrfTokenValid() check in every action controller'
  - 'CurrentClinicContextInterface for clinicId — never from form input'
  - 'HandlerFailedException unwrap via getWrappedExceptions() for query bus calls that may throw'
  - 'WaitingRoomEntryEntityFactory (Foundry) for fixtures — bypasses domain invariants for realistic temporal states'
  - 'SchedulingStory::add() keys for appointment refs — naming: appointment:paris:YYYY-MM-DD:HH:MM:slug'
test_patterns:
  - 'Presentation layer excluded from 100% coverage requirement (CLAUDE.md)'
  - 'All command/query handlers already tested — no new unit tests required'
---

# Tech-Spec: Waiting Room — Real Data Wire-up & Stimulus Refactor

**Created:** 2026-04-19

## Overview

### Problem Statement

The waiting room page is architecturally complete server-side (5 commands, 2 queries, full CQRS + domain events) but the client renders 7 hardcoded mock patients from a JS array (`PATIENTS`). Zero Turbo Frame integration exists. 14 functions are exposed on `window.*` and 20 `onclick=` inline handlers span 5 template partials. Stats (4 en attente, 2 en consultation) and the date ("Samedi 22 mars 2026") are hardcoded. The double `#page-body-desktop` / `#page-body-tabs` layout duplicates every list element twice. The page is non-functional in production.

### Solution

Replace JS mock rendering with a Turbo Frame SSR pattern: a dedicated queue route renders Twig queue cards from `ListWaitingRoom` (including status bar stats) and auto-refreshes every 20s via a Stimulus `auto-refresh` controller with `refresh="morph"`. A per-entry detail route serves the detail panel frame — navigation driven by a plain `<a data-turbo-frame>` link wrapped in a responsive panel (`is-hidden` / visible via Stimulus). Filter tabs are server-side links reloading the queue frame with a `?filter=` param. All `onclick=` inline handlers migrate to a `waiting-room` Stimulus controller. Four commands are wired to real CSRF-protected POST forms using Post/Redirect/Get. Modals migrate to the global `modal-overlay hidden` pattern. The double layout and legacy bottom-sheet / detail-slide are consolidated into a single responsive panel. `waiting-room.js` is deleted entirely.

### Scope

**In Scope:**
- `WaitingRoomQueueController` — GET `/scheduling/waiting-room/queue[?filter=]`, calls `ListWaitingRoom`, applies sub-filter, renders `_queue_frame.html.twig` (queue cards + status bar stats + empty state)
- `WaitingRoomEntryDetailsController` — GET `/scheduling/waiting-room/{entryId}/details` where `{entryId}` = UUID of `WaitingRoomEntry` aggregate, calls `GetWaitingRoomEntryDetails`, renders `_entry_details.html.twig`; handles missing entry via `HandlerFailedException` unwrap → 404
- `CreateWalkInController` — POST with CSRF, dispatches `CreateWaitingRoomWalkInEntry`, redirects to `clinic_scheduling_waiting_room`
- `UpdateTriageController` — POST with CSRF, dispatches `UpdateWaitingRoomTriage`, redirects to `clinic_scheduling_waiting_room`
- `StartServiceFromWaitingRoomController` — POST with CSRF, dispatches `StartServiceForWaitingRoomEntry`, redirects to `clinic_scheduling_waiting_room`
- `CloseEntryFromWaitingRoomController` — POST with CSRF, dispatches `CloseWaitingRoomEntry`, redirects to `clinic_scheduling_waiting_room`
- Stimulus `auto-refresh` controller — placed directly on the `<turbo-frame>` element; `this.element.reload()` (TurboFrameElement API)
- Stimulus `waiting-room` controller — real-time clock, modal open/close, detail panel show/hide (`#patient-detail-panel` wrapper), Escape key
- `modal-checkin` rewired: remove hardcoded RDV rows (Milo/Nala), walk-in form only, CSRF token, migrate to `modal-overlay hidden`, real POST to `CreateWalkInController`
- `modal-urgence` rewired: fast-path walk-in with `arrivalMode=EMERGENCY, priority=2`, CSRF token, migrate to `modal-overlay hidden`, real POST to `CreateWalkInController`
- Remove `modal-appel` entirely — no stub, no trace in rendered HTML
- Layout consolidation: delete `#page-body-desktop` / `#page-body-tabs`, `#detail-slide`, `#bs`, `#bso` — single responsive layout
- Status bar stats moved inside `<turbo-frame id="waiting-queue">` — computed from `entries` in Twig, refresh with queue, zero drift
- Topbar retains: title, clock (Stimulus targets), "Urgence" button, "Check-in" button
- Remove `data-turbo-temporary` from all queue list divs (redundant)
- Empty state CTA (`_queue_panel.html.twig`) — outside the frame, hidden/shown via CSS `.is-empty` class on wrapper
- `WaitingRoomStory` fixture: 7 entries using `WaitingRoomEntryEntityFactory` (Foundry), varied temporal states
- `SchedulingStory` modified to expose appointment IDs via `$this->add()` keys
- `ClinicDataset`: add `WaitingRoomStory::load()` after `SchedulingStory::load()`
- Delete `assets/js/pages/scheduling/waiting-room.js` + remove entry from `assets/app.js`

**Out of Scope:**
- `CreateWaitingRoomEntryFromAppointment` (event listener, stays as-is)
- Mercure/SSE real-time push (exit door: when concurrent multi-user is measured)
- Feature "appeler en salle de consultation" — requires `CallWaitingRoomEntry` (domain command) + CallingDisplay (patient-facing screen). To be specified separately.
- Enriched patient detail (last consultation, vaccins, allergies) — cross-BC queries; v1 shows `WaitingRoomEntryDetailsDTO` fields only
- Owner/animal search autocomplete in check-in modal
- `DONE` entry count in stats bar (DONE filtered out by `ListWaitingRoom`; shows "—")
- Turbo Streams responses (Post/Redirect/Get accepted for v1; exit door: migrate if reload latency measured)

---

## Context for Development

### Codebase Patterns

**Single-action controllers — pattern from existing code:**
```php
#[Route('/scheduling/waiting-room/{entryId}/start-service', name: 'clinic_scheduling_waitingroom_start', methods: ['POST'])]
final class StartServiceController extends AbstractController
{
    public function __invoke(string $entryId): Response
    {
        $user = $this->getUser(); \assert($user instanceof SecurityUser);
        try {
            $this->commandBus->dispatch(new StartServiceForWaitingRoomEntry(
                waitingRoomEntryId: $entryId,
                serviceStartedByUserId: $user->id(),
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('clinic_scheduling_agenda');
    }
}
```
New waiting-room-context controllers follow the same pattern but redirect to `clinic_scheduling_waiting_room` and validate CSRF.

**CSRF on all POST forms:**
```twig
{# In every POST form: #}
<input type="hidden" name="_token" value="{{ csrf_token('waiting_room_action') }}">
```
```php
// In every action controller __invoke():
if (!$this->isCsrfTokenValid('waiting_room_action', $request->request->getString('_token'))) {
    throw $this->createAccessDeniedException('Invalid CSRF token.');
}
```

**HandlerFailedException unwrap for query bus calls:**
```php
use Symfony\Component\Messenger\Exception\HandlerFailedException;

try {
    $entry = $this->queryBus->ask(new GetWaitingRoomEntryDetails($entryId));
    \assert($entry instanceof WaitingRoomEntryDetailsDTO);
} catch (HandlerFailedException $e) {
    foreach ($e->getWrappedExceptions() as $nested) {
        if ($nested instanceof \DomainException) {
            throw $this->createNotFoundException();
        }
    }
    throw $e;
}
```

**Turbo Frame SSR — queue frame with stats inside:**
```twig
{{-- index.html.twig --}}
<turbo-frame id="waiting-queue"
             src="{{ path('clinic_scheduling_waitingroom_queue') }}"
             refresh="morph"
             data-turbo-action="replace"
             data-controller="auto-refresh"
             data-auto-refresh-interval-value="20000">
  <div class="wr-frame-loading">Chargement…</div>
</turbo-frame>

{{-- _queue_frame.html.twig — stats AND queue cards in the same frame --}}
<turbo-frame id="waiting-queue">
  <div class="status-bar">
    <div class="stat-block">... {{ entries|filter(e => e.status == 'WAITING')|length }} ...</div>
    <div class="stat-block">... {{ entries|filter(e => e.status == 'IN_SERVICE')|length }} ...</div>
    <div class="stat-block">... {{ entries|filter(e => e.arrivalMode == 'EMERGENCY')|length }} ...</div>
    <div class="stat-block">... — ...</div>
  </div>
  {% if entries is empty %}...{% else %}...cards...{% endif %}
</turbo-frame>
```
Stats live inside the frame → refresh every 20s automatically → zero drift. No double `ListWaitingRoom` call: `WaitingRoomQueueController` is the only caller; `WaitingRoomController` no longer calls `ListWaitingRoom` (not needed since stats moved into frame).

**Detail panel — zero JS navigation, single responsive component:**
```twig
<div id="patient-detail-panel" class="patient-detail-panel is-hidden"
     data-waiting-room-target="detailPanel">
  <turbo-frame id="patient-detail" src="">
    <!-- empty initial state -->
  </turbo-frame>
</div>
```
Queue card link:
```twig
<a href="{{ path('clinic_scheduling_waitingroom_entry_details', {entryId: entry.id}) }}"
   class="queue-card-link"
   data-turbo-frame="patient-detail">
```
Stimulus `waiting-room` controller listens for `turbo:frame-load` on `#patient-detail` → removes `is-hidden` from panel. Escape → adds `is-hidden` back. No `#detail-slide`, no `#bs`, no `#bso`.

CSS `.patient-detail-panel`:
- Desktop/landscape: side panel (`width: 320px`, flex shrink-0)
- Portrait/mobile: bottom sheet (`position: fixed; bottom: 0; left: 0; right: 0; height: 60vh`)
- `.is-hidden`: `display: none`

**`auto-refresh` controller — must be on the `<turbo-frame>` itself:**
```js
// assets/controllers/auto_refresh_controller.js
// IMPORTANT: Must be placed on a <turbo-frame> element.
// this.element.reload() is a TurboFrameElement method — throws on HTMLElement.
import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
  static values = { interval: { type: Number, default: 20000 } };
  connect() { this._timer = setInterval(() => this.element.reload(), this.intervalValue); }
  disconnect() { clearInterval(this._timer); }
}
```

**Filter tabs — server-side links:**
```twig
<a href="{{ path('clinic_scheduling_waitingroom_queue', {filter: 'urgence'}) }}"
   data-turbo-frame="waiting-queue"
   class="qf-tab {{ currentFilter == 'urgence' ? 'active' : '' }}">Urgences</a>
```
`data-turbo-action="replace"` on the frame → URL updates without browser history entry per tab click.

**Modal pattern — global `modal-overlay hidden`:**
```html
<div class="modal-overlay hidden" id="modal-checkin"
     data-action="click->waiting-room#closeOnOverlayClick">
  <div class="modal">...</div>
</div>
```
All `wr-modal-overlay` CSS removed with `waiting-room.js`. `wr-modal-*` layout overrides (body padding, footer sticky if any) become targeted rules in `assets/styles/pages/scheduling/waiting-room.css`.

**Post/Redirect/Get — full-page reload accepted for v1:**
All form actions redirect 303 to `clinic_scheduling_waiting_room`. On the redirected page, `<turbo-frame id="waiting-queue">` rehydrates from `src`. Modal closed (default `hidden` after reload).

**`CreateWalkInEntry` command parameters:**
```php
public string $clinicId,              // from CurrentClinicContextInterface — NOT from form
public ?string $ownerId = null,
public ?string $animalId = null,
public ?string $foundAnimalDescription = null,
public string $arrivalMode = 'STANDARD',  // 'STANDARD' | 'EMERGENCY'
public int $priority = 0,                 // 0=normal, 1=prioritaire, 2=urgence
public ?string $triageNotes = null,
```

**`UpdateWaitingRoomTriage` command parameters:**
```php
public string $waitingRoomEntryId,
public int $priority,
public ?string $triageNotes,
public string $arrivalMode,  // 'STANDARD' | 'EMERGENCY'
```

**`WaitingRoomEntryItem` fields** (returned by `ListWaitingRoom`, ORDER: EMERGENCY first → priority DESC → arrivedAtUtc ASC):
`id`, `clinicId`, `origin` (WALK_IN|FROM_APPOINTMENT), `arrivalMode` (STANDARD|EMERGENCY), `linkedAppointmentId?`, `ownerId?`, `animalId?`, `foundAnimalDescription?` *(confirmed on DTO)*, `priority` (int), `triageNotes?`, `status` (WAITING|CALLED|IN_SERVICE), `arrivedAtUtc`, `calledAtUtc?`, `serviceStartedAtUtc?`, `closedAtUtc?`

**`WaitingRoomEntryDetailsDTO` fields** (returned by `GetWaitingRoomEntryDetails`):
`waitingRoomEntryId`, `clinicId`, `status`, `origin`, `arrivalMode`, `linkedAppointmentId?`, `ownerId?`, `animalId?`, `triageNotes?`, `arrivedAtUtc`, `calledAtUtc?`, `serviceStartedAtUtc?`, `closedAtUtc?`

**Existing action controllers — do NOT modify:**
- `StartServiceController` → `clinic_scheduling_waitingroom_start` → redirects to agenda (agenda widget)
- `CloseWaitingRoomEntryController` → `clinic_scheduling_waitingroom_close` → redirects to agenda (agenda widget)

**`WaitingRoomEntryEntityFactory` (Foundry — used for fixtures):**
Fluent builder: `withStatus(WaitingRoomEntryStatus::IN_SERVICE)`, `withArrivalMode(WaitingRoomArrivalMode::EMERGENCY)`, `withOwnerId(...)`, `withAnimalId(...)`, etc. Use `afterInstantiate()` callback to set arbitrary timestamps. Do NOT use domain factory `WaitingRoomEntryFactory` for fixtures — it enforces `arrivedAtUtc = now()` and `status = WAITING`.

**`SchedulingStory` appointment refs — pattern to add:**
```php
// In SchedulingStory::build(), after each appointment creation:
$this->add('appointment:paris:2026-03-22:10:15:luna-martin', $appointmentId);
// In WaitingRoomStory::build():
$appointmentId = SchedulingStory::get('appointment:paris:2026-03-22:10:15:luna-martin');
```
Naming convention: `appointment:{clinic-slug}:{date}:{time}:{animal-slug}`.

**`assets/app.js` page dispatcher** (correct path — NOT `assets/js/app.js`): Remove the `clinic_scheduling_waiting_room` route-name key entry when deleting `waiting-room.js`.

### Files to Reference

| File | Purpose |
|------|---------|
| `src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/WaitingRoomController.php` | Existing main controller — constructor injection pattern |
| `src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/StartServiceController.php` | Existing action controller pattern — do NOT modify |
| `src/Context/Scheduling/Application/Command/CreateWaitingRoomWalkInEntry/CreateWalkInEntry.php` | Walk-in command params |
| `src/Context/Scheduling/Application/Command/UpdateWaitingRoomTriage/UpdateWaitingRoomTriage.php` | Triage command params |
| `src/Context/Scheduling/Application/Query/ListWaitingRoom/WaitingRoomEntryItem.php` | DTO fields for queue cards (foundAnimalDescription confirmed present) |
| `src/Context/Scheduling/Application/Query/GetWaitingRoomEntryDetails/WaitingRoomEntryDetailsDTO.php` | DTO fields for detail panel |
| `templates/clinic/scheduling/waiting-room/_modals.html.twig` | Full modal HTML to refactor (modal-appel to delete) |
| `templates/clinic/scheduling/waiting-room/index.html.twig` | Main template — double layout to consolidate |
| `templates/clinic/clients/view/_modals.html.twig` | Reference: `modal-overlay hidden` pattern |
| `assets/controllers/appointment_form_controller.js` | Reference Stimulus pattern: targets, values, connect/disconnect |
| `fixtures/Context/Scheduling/Factory/WaitingRoomEntryEntityFactory.php` | Foundry factory for WaitingRoomStory — use this, not domain factory |
| `fixtures/Context/Scheduling/Story/SchedulingStory.php` | Add `$this->add()` keys for appointment IDs |
| `fixtures/Dataset/ClinicDataset.php` | Add WaitingRoomStory::load() at position 8 |
| `assets/app.js` | Remove waiting-room.js dispatcher entry (correct path) |
| `assets/styles/pages/scheduling/waiting-room.css` | Correct CSS path — not `scheduling-waiting-room.css` |

### Technical Decisions

1. **Turbo Frame SSR over JSON payload** — Queue is a list of cards; JSON (agenda) is the exception for complex grid rendering.

2. **Status bar inside `<turbo-frame id="waiting-queue">`** — Stats computed from `entries` in Twig. Auto-refresh every 20s keeps them in sync with queue content. Zero drift, zero extra query, zero extra code.

3. **`data-turbo-action="replace"` on queue frame** — Filter tabs update the URL without polluting browser history. `advance` would stack a back-history entry per filter click (mobile UX failure). `replace` keeps the URL shareable.

4. **Detail panel: single responsive component** — One `<div id="patient-detail-panel">` wraps `<turbo-frame id="patient-detail">`. CSS positions it as side panel (desktop) or bottom sheet (mobile/portrait). Stimulus controller shows/hides via `is-hidden`. Replaces `#detail-slide` + `#bs` + `#bso`. Escape closes it.

5. **Detail panel navigation: `<a data-turbo-frame="patient-detail">`** — Zero JS for navigation. `turbo:frame-load` on the frame triggers the Stimulus show. No `querySelector`, no DOM manipulation.

6. **`auto-refresh` controller directly on `<turbo-frame>`** — `this.element.reload()` is `TurboFrameElement` API. Documented in controller header to prevent placement on wrapper.

7. **Global `modal-overlay hidden` for WR modals** — Migrates `wr-modal-overlay` to the global pattern. `wr-modal-*` layout CSS becomes targeted overrides in `waiting-room.css`. Dead `wr-modal-*` CSS rules removed.

8. **CSRF on ALL POST forms** — Non-negotiable. Token name `'waiting_room_action'`. `isCsrfTokenValid()` in every action controller.

9. **`HandlerFailedException` unwrap** — Symfony Messenger wraps handler exceptions. `catch (\DomainException)` alone will miss. Use `getWrappedExceptions()` loop. Flag as candidate for middleware/helper if pattern repeats on consultation/hospitalisations.

10. **`WaitingRoomEntryEntityFactory` for fixtures** — Domain factory enforces `arrivedAtUtc = now()` and `status = WAITING`. Foundry factory bypasses these via `afterInstantiate()`, enabling realistic temporal states (arrived 2h ago, IN_SERVICE since 5min).

11. **`SchedulingStory::add()` for appointment refs** — Consistent with `ClinicDataStory` and `AnimalDataStory`. `WaitingRoomStory` depends on named keys, not fragile repository queries.

12. **Post/Redirect/Get for all form actions** — Standard Symfony + Turbo. Redirect 303 → page reloads → frame rehydrates → modal closed by default. No Turbo Streams in v1.

13. **Delete `modal-appel` entirely** — No `CallWaitingRoomEntry` domain command. No stub, no placeholder.

---

## Implementation Plan

### Tasks

- [x] **Task 1 — Stimulus `auto-refresh` controller**
  - File: `assets/controllers/auto_refresh_controller.js` (create)
  - Header comment: *"MUST be placed on a `<turbo-frame>` element. `this.element.reload()` is TurboFrameElement API — throws on non-frame elements."*
  - `static values = { interval: { type: Number, default: 20000 } }`
  - `connect()`: `this._timer = setInterval(() => this.element.reload(), this.intervalValue)`
  - `disconnect()`: `clearInterval(this._timer)`

- [x] **Task 2 — Stimulus `waiting-room` controller**
  - File: `assets/controllers/waiting_room_controller.js` (create)
  - `static targets = ['clockH', 'clockM', 'clockS', 'detailPanel']`
  - `connect()`:
    - Start clock: `setInterval` every 1000ms, update `clockHTarget`/`clockMTarget`/`clockSTarget` with zero-padded current time
    - Attach `keydown` listener → Escape calls `closeDetailPanel()` + `closeAllModals()`
    - Attach `turbo:frame-load` listener on `document.getElementById('patient-detail')` → calls `showDetailPanel()`
  - `disconnect()`: `clearInterval(this._clockTimer)`, remove keydown + frame-load listeners
  - `openModal(event)`: `document.getElementById(event.params.modalId).classList.remove('hidden')`
  - `closeModal(event)`: `document.getElementById(event.params.modalId).classList.add('hidden')`
  - `closeOnOverlayClick(event)`: `if (event.target === event.currentTarget) this.closeModal(event)`
  - `closeAllModals()`: `document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(m => m.classList.add('hidden'))`
  - `showDetailPanel()`: `this.detailPanelTarget.classList.remove('is-hidden')`
  - `closeDetailPanel()`: `this.detailPanelTarget.classList.add('is-hidden')`

- [x] **Task 3 — `WaitingRoomQueueController`**
  - File: `src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/WaitingRoomQueueController.php` (create)
  - Route: `GET /scheduling/waiting-room/queue`, name `clinic_scheduling_waitingroom_queue`
  - Constructor: `QueryBusInterface $queryBus`, `CurrentClinicContextInterface $currentClinicContext`
  - Read `$filter = $request->query->getString('filter', 'all')`; validate against `['all', 'urgence', 'attente', 'consultation']`
  - Call `ListWaitingRoom($currentClinicId->toString())`; assert is array
  - Sub-filter in PHP: `urgence` → `arrivalMode === 'EMERGENCY'`; `attente` → `status === 'WAITING'`; `consultation` → `status === 'IN_SERVICE'`; `all` → no filter
  - Render `clinic/scheduling/waiting-room/_queue_frame.html.twig` with `['entries' => $filtered, 'currentFilter' => $filter]`

- [x] **Task 4 — `WaitingRoomEntryDetailsController`**
  - File: `src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/WaitingRoomEntryDetailsController.php` (create)
  - Route: `GET /scheduling/waiting-room/{entryId}/details`, name `clinic_scheduling_waitingroom_entry_details`
  - `{entryId}` = UUID of `WaitingRoomEntry` aggregate (not patient, not animal, not appointment)
  - Constructor: `QueryBusInterface $queryBus`
  - Use `HandlerFailedException` unwrap pattern (see Codebase Patterns above); throw 404 on `\DomainException`
  - Render `clinic/scheduling/waiting-room/_entry_details.html.twig` with `['entry' => $entry]`

- [x] **Task 5 — `CreateWalkInController`**
  - File: `src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/CreateWalkInController.php` (create)
  - Route: `POST /scheduling/waiting-room/walk-in`, name `clinic_scheduling_waitingroom_walkin`
  - Validate CSRF token `'waiting_room_action'`; throw `AccessDeniedException` on failure
  - Read from request: `foundAnimalDescription` (string|null), `arrivalMode` (validate in `['STANDARD','EMERGENCY']`, default `'STANDARD'`), `priority` (int, clamp 0–2, default `0`), `triageNotes` (string|null)
  - `clinicId` from `CurrentClinicContextInterface` — never from request
  - Dispatch `CreateWaitingRoomWalkInEntry(...)` in try/catch; `addFlash('error', ...)` on failure
  - Redirect to `clinic_scheduling_waiting_room`

- [x] **Task 6 — `UpdateTriageController`**
  - File: `src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/UpdateTriageController.php` (create)
  - Route: `POST /scheduling/waiting-room/{entryId}/triage`, name `clinic_scheduling_waitingroom_triage`
  - Validate CSRF token `'waiting_room_action'`
  - Read: `priority` (int), `triageNotes` (string|null), `arrivalMode` (validate `['STANDARD','EMERGENCY']`)
  - Dispatch `UpdateWaitingRoomTriage(...)` in try/catch
  - Redirect to `clinic_scheduling_waiting_room`

- [x] **Task 7 — `StartServiceFromWaitingRoomController`**
  - File: `src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/StartServiceFromWaitingRoomController.php` (create)
  - Route: `POST /scheduling/waiting-room/{entryId}/start-service-from-queue`, name `clinic_scheduling_waitingroom_start_from_queue`
  - Validate CSRF token `'waiting_room_action'`
  - Dispatch `StartServiceForWaitingRoomEntry(waitingRoomEntryId: $entryId, serviceStartedByUserId: $user->id())`
  - Redirect to `clinic_scheduling_waiting_room`

- [x] **Task 8 — `CloseEntryFromWaitingRoomController`**
  - File: `src/Presentation/Clinic/Controller/Scheduling/WaitingRoom/CloseEntryFromWaitingRoomController.php` (create)
  - Route: `POST /scheduling/waiting-room/{entryId}/close-from-queue`, name `clinic_scheduling_waitingroom_close_from_queue`
  - Validate CSRF token `'waiting_room_action'`
  - Dispatch `CloseWaitingRoomEntry(waitingRoomEntryId: $entryId, closedByUserId: $user->id())`
  - Redirect to `clinic_scheduling_waiting_room`

- [x] **Task 9 — `_queue_frame.html.twig`**
  - File: `templates/clinic/scheduling/waiting-room/_queue_frame.html.twig` (create)
  - Root: `<turbo-frame id="waiting-queue">`
  - **Status bar** (always rendered, stats from `entries`):
    ```twig
    {% set countWaiting = entries|filter(e => e.status == 'WAITING')|length %}
    {% set countService = entries|filter(e => e.status == 'IN_SERVICE')|length %}
    {% set countEmergency = entries|filter(e => e.arrivalMode == 'EMERGENCY')|length %}
    ```
  - **Filter tabs** (active state via `currentFilter`):
    ```twig
    <a href="{{ path('clinic_scheduling_waitingroom_queue', {filter: 'all'}) }}"
       data-turbo-frame="waiting-queue"
       class="qf-tab {{ currentFilter == 'all' ? 'active' : '' }}">Tous</a>
    {# repeat for urgence/attente/consultation #}
    ```
  - **Empty state** (when `entries is empty`):
    ```twig
    <div class="wr-empty-state">
      <i data-lucide="inbox" class="wr-empty-icon"></i>
      <p class="wr-empty-title">Aucun patient en salle d'attente</p>
    </div>
    ```
    The "Check-in patient" CTA is in `_queue_panel.html.twig` outside the frame (see Task 13).
  - **Queue cards** — wrapper structure (avoids invalid `<button>` inside `<a>`):
    ```twig
    <div class="queue-card-wrap {{ entry.arrivalMode == 'EMERGENCY' ? 'queue-card-wrap--emergency' : '' }}">
      <a href="{{ path('clinic_scheduling_waitingroom_entry_details', {entryId: entry.id}) }}"
         class="queue-card-link"
         data-turbo-frame="patient-detail">
        {# card content: foundAnimalDescription or owner/animal info, status badge, origin badge,
           arrivedAtUtc formatted, triageNotes truncated, EMERGENCY indicator #}
      </a>
      <div class="queue-card-actions">
        {% if entry.status in ['WAITING', 'CALLED'] %}
          <form method="post" action="{{ path('clinic_scheduling_waitingroom_start_from_queue', {entryId: entry.id}) }}">
            <input type="hidden" name="_token" value="{{ csrf_token('waiting_room_action') }}">
            <button type="submit" class="btn btn-green btn-xs">Démarrer</button>
          </form>
        {% endif %}
        {% if entry.status == 'IN_SERVICE' %}
          <form method="post" action="{{ path('clinic_scheduling_waitingroom_close_from_queue', {entryId: entry.id}) }}">
            <input type="hidden" name="_token" value="{{ csrf_token('waiting_room_action') }}">
            <button type="submit" class="btn btn-secondary btn-xs">Terminer</button>
          </form>
        {% endif %}
      </div>
    </div>
    ```

- [x] **Task 10 — `_entry_details.html.twig`**
  - File: `templates/clinic/scheduling/waiting-room/_entry_details.html.twig` (create)
  - Root: `<turbo-frame id="patient-detail">`
  - Displays `WaitingRoomEntryDetailsDTO` fields: status, origin, arrivalMode (EMERGENCY highlighted), triageNotes (if present), arrivedAtUtc, calledAtUtc (if set), serviceStartedAtUtc (if set)
  - Triage update form:
    ```twig
    <form method="post" action="{{ path('clinic_scheduling_waitingroom_triage', {entryId: entry.waitingRoomEntryId}) }}">
      <input type="hidden" name="_token" value="{{ csrf_token('waiting_room_action') }}">
      {# priority select, triageNotes textarea, arrivalMode select #}
      <button type="submit" class="btn btn-primary btn-sm">Mettre à jour le triage</button>
    </form>
    ```

- [x] **Task 11 — Refactor `index.html.twig`**
  - Delete `#page-body-desktop`, `#page-body-tabs`, `#detail-slide`, `#bs`, `#bso`, FAB group duplicate
  - Single `<div class="page-body" data-controller="waiting-room">`:
    - Queue panel (left): `_queue_panel.html.twig` partial (no `data-turbo-temporary` list div)
    - Control panel (right): `_control_panel.html.twig` partial
  - Queue panel area contains the Turbo Frame for the queue:
    ```twig
    <turbo-frame id="waiting-queue"
                 src="{{ path('clinic_scheduling_waitingroom_queue') }}"
                 refresh="morph"
                 data-turbo-action="replace"
                 data-controller="auto-refresh"
                 data-auto-refresh-interval-value="20000">
      <div class="wr-frame-loading">Chargement…</div>
    </turbo-frame>
    ```
  - Control panel area contains the detail panel wrapper:
    ```twig
    <div id="patient-detail-panel"
         class="patient-detail-panel is-hidden"
         data-waiting-room-target="detailPanel">
      <turbo-frame id="patient-detail" src=""></turbo-frame>
    </div>
    ```
  - Keep one FAB group (CSS controls mobile-only visibility)
  - Remove `{% block javascripts %}{% endblock %}`
  - Remove `#vets-list`, `#timeline-list` and their content

- [x] **Task 12 — Refactor `_topbar.html.twig`**
  - Dynamic date: `{{ currentDate }}` (string from `WaitingRoomController`)
  - Clock spans: `data-waiting-room-target="clockH"`, `data-waiting-room-target="clockM"`, `data-waiting-room-target="clockS"`
  - Remove `<div class="status-bar">` entirely (moved into `_queue_frame.html.twig`)
  - "Urgence" button: `data-action="waiting-room#openModal" data-waiting-room-modal-id-param="modal-urgence"`
  - "Check-in" button: `data-action="waiting-room#openModal" data-waiting-room-modal-id-param="modal-checkin"`

- [x] **Task 13 — Refactor `_queue_panel.html.twig`**
  - Remove `<div class="queue-list" id="queue-list" data-turbo-temporary>` entirely (Turbo Frame is in `index.html.twig`)
  - Remove `<span class="wr-queue-count">` (moved into frame)
  - Remove all `onclick=` from filter buttons — now rendered inside the frame in `_queue_frame.html.twig`
  - The panel now contains: queue title + "Check-in" button + the Turbo Frame slot (see Task 11) + empty-state CTA outside frame
  - Empty-state CTA (outside frame, shown only via CSS when queue frame signals empty):
    ```twig
    <div class="wr-queue-empty-cta" id="wr-queue-empty-cta">
      <button class="btn btn-primary btn-sm"
              data-action="waiting-room#openModal"
              data-waiting-room-modal-id-param="modal-checkin">
        Check-in patient
      </button>
    </div>
    ```
    Note: toggling visibility of this CTA based on frame content requires either a JS approach (Stimulus observer on frame) or CSS that reads `turbo-frame:empty`. Simplest v1 approach: always show the CTA below the frame (it's always relevant). Remove the `wr-empty-state` empty text from `_queue_frame.html.twig` Task 9, keep only the frame's `{% if entries is empty %}` block for the icon+message inside the frame.
  - "Check-in" button in queue head: `data-action="waiting-room#openModal" data-waiting-room-modal-id-param="modal-checkin"`

- [x] **Task 14 — Refactor `_control_panel.html.twig`**
  - Replace `<div id="patient-detail-wrap">` → the detail panel wrapper is in `index.html.twig` (Task 11)
  - Remove `#vets-list`, `#timeline-list`, urgence banner (hardcoded Gaia content removed)
  - Remove all `onclick=` — `closeDetailSlide()` removed with the component
  - Control panel now primarily hosts the `#patient-detail-panel` wrapper

- [x] **Task 15 — Refactor `_modals.html.twig`**
  - **Delete `modal-appel` entirely** — no stub
  - **`modal-checkin`** — rewrite:
    ```twig
    <div class="modal-overlay hidden" id="modal-checkin"
         data-action="click->waiting-room#closeOnOverlayClick">
      <div class="modal">
        <div class="modal-head">
          <p class="modal-title">Check-in patient</p>
          <button class="modal-close-btn"
                  data-action="waiting-room#closeModal"
                  data-waiting-room-modal-id-param="modal-checkin">×</button>
        </div>
        <form method="post" action="{{ path('clinic_scheduling_waitingroom_walkin') }}">
          <input type="hidden" name="_token" value="{{ csrf_token('waiting_room_action') }}">
          <input type="hidden" name="arrivalMode" value="STANDARD">
          <div class="modal-body">
            <div class="form-row">
              <label class="form-label">Animal / Propriétaire</label>
              <input class="form-input" name="foundAnimalDescription" placeholder="Ex : Milo (Canin) — Mme Dupont">
            </div>
            <div class="form-row">
              <label class="form-label">Motif de visite</label>
              <textarea class="form-input" name="triageNotes" rows="2" placeholder="Symptômes, motif…"></textarea>
            </div>
            <div class="form-row">
              <label class="form-label">Priorité</label>
              {# select: 0=Normal, 1=Prioritaire — name="priority" #}
            </div>
          </div>
          <div class="modal-foot">
            <button type="button" class="btn btn-secondary"
                    data-action="waiting-room#closeModal"
                    data-waiting-room-modal-id-param="modal-checkin">Annuler</button>
            <button type="submit" class="btn btn-primary">Valider l'arrivée</button>
          </div>
        </form>
      </div>
    </div>
    ```
  - **`modal-urgence`** — rewrite with same modal structure, different fields:
    - Hidden: `arrivalMode=EMERGENCY`, `priority=2`
    - Fields: `foundAnimalDescription` (animal + owner + species description), `triageNotes` (motif urgence)
    - Remove hardcoded vet dropdown entirely
    - Submit btn class: `btn btn-danger`

- [x] **Task 16 — Update `WaitingRoomController`**
  - Add `ClockInterface $clock` to constructor (inject via DI — follows pattern of other controllers using the clock)
  - Add `IntlDateFormatter` for `currentDate`:
    ```php
    $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, $clinicTz);
    $currentDate = ucfirst($formatter->format($this->clock->now()));
    ```
  - Remove `ListWaitingRoom` call (stats now live in queue frame — no longer needed here)
  - Remove `waitingRoomEntries` from render vars
  - Pass: `'currentDate' => $currentDate`, `'currentClinicId' => $currentClinicId->toString()`, `'currentClinicName' => $clinic->name`

- [x] **Task 17 — Modify `SchedulingStory` to expose appointment refs**
  - File: `fixtures/Context/Scheduling/Story/SchedulingStory.php` (modify)
  - After each appointment creation, add: `$this->add('appointment:paris:{date}:{time}:{slug}', $appointmentId->toString())`
  - Add at least 3 refs for appointments that `WaitingRoomStory` will reference
  - Naming convention: `appointment:paris:2026-03-22:10:15:luna-martin`

- [x] **Task 18 — `WaitingRoomStory` fixture**
  - File: `fixtures/Context/Scheduling/Story/WaitingRoomStory.php` (create)
  - **Use `WaitingRoomEntryEntityFactory` (Foundry), NOT domain factory `WaitingRoomEntryFactory`**
  - Header comment: *"Depends on SchedulingStory (appointment refs must exist). Uses Foundry EntityFactory to bypass domain temporal invariants (arrivedAtUtc, status)."*
  - Use `ClinicDataStory::INDEPENDENT_CLINIC_ID` for `clinicId`
  - Use `WaitingRoomArrivalMode::STANDARD` / `WaitingRoomArrivalMode::EMERGENCY` enums (not strings)
  - Use `WaitingRoomEntryStatus::WAITING` / `WaitingRoomEntryStatus::IN_SERVICE` enums
  - Create 7 entries using `afterInstantiate()` or fluent builder to set `arrivedAtUtc`, `serviceStartedAtUtc`:
    1. Walk-in, STANDARD, WAITING, `foundAnimalDescription="Gaia (Canin Labrador) — Mme Fauchard"`, triageNotes="Convulsions", arrived `-2 hours`
    2. Walk-in, EMERGENCY, WAITING, `foundAnimalDescription="Chat inconnu — M. Arnault"`, triageNotes="Trauma choc voiture", priority=2, arrived `-30 min`
    3. Walk-in, STANDARD, WAITING, `foundAnimalDescription="Nala (Félin) — Mme Moreau"`, arrived `-45 min`
    4. From-appointment (`SchedulingStory::get('appointment:paris:...')`), STANDARD, WAITING, real `ownerId`/`animalId`, arrived `-1 hour`
    5. From-appointment, STANDARD, IN_SERVICE, real `ownerId`/`animalId`, arrived `-90 min`, `serviceStartedAtUtc = -20 min`
    6. From-appointment, STANDARD, IN_SERVICE, real `ownerId`/`animalId`, arrived `-60 min`, `serviceStartedAtUtc = -5 min`
    7. Walk-in, STANDARD, WAITING, `foundAnimalDescription="Milo (Canin British)"`, triageNotes="Vaccin annuel", arrived `-75 min`

- [x] **Task 19 — Update `ClinicDataset`**
  - Add `use App\Fixtures\Context\Scheduling\Story\WaitingRoomStory;`
  - Add `WaitingRoomStory::load();` after `SchedulingStory::load()` and before `SchedulingPlanningBlockStory::load()`

- [x] **Task 20 — Delete `waiting-room.js` + clean `assets/app.js`**
  - Delete `assets/js/pages/scheduling/waiting-room.js`
  - In `assets/app.js`: remove the `clinic_scheduling_waiting_room` route-name entry from the page dispatcher

- [x] **Task 21 — `make assets` + `make ci`**
  - Run `make assets`
  - Run `make ci` — all checks must pass

### Acceptance Criteria

- [x] **AC1**: Given fixtures are loaded, when navigating to `/scheduling/waiting-room`, then 7 real queue cards are visible — no mock "Gaia Labrador" hardcoded names from JS, no `PATIENTS` array, correct status badges and origin badges.

- [x] **AC2**: Given the queue frame is loaded, when 20 seconds pass without user interaction, then the frame silently reloads via morph — no full-page flash, no scroll reset, no visible flicker. Status bar counts update with queue content.

- [x] **AC3**: Given there are no active entries (DONE/empty table), when the queue frame loads, then the empty state is shown: inbox icon + "Aucun patient en salle d'attente" inside the frame; the "Check-in patient" CTA button below the frame is visible and opens `modal-checkin`.

- [x] **AC4**: Given the "Urgences" filter tab is clicked, when the queue frame reloads, then only entries with `arrivalMode=EMERGENCY` are shown; the URL is updated via `replace` (not `advance`) — browser Back navigates to the previous page, not the previous filter state.

- [x] **AC5**: Given an entry is in WAITING status, when the "Démarrer" form is submitted with a valid CSRF token, then: 303 redirect → page reloads → entry is IN_SERVICE in DB → queue card shows updated badge. Submitting without a valid CSRF token returns `AccessDeniedException`.

- [x] **AC6**: Given an entry is in IN_SERVICE status, when the "Terminer" form is submitted with a valid CSRF token, then: 303 redirect → entry is DONE → card disappears from queue (DONE excluded by `ListWaitingRoom`) → user remains on waiting room.

- [x] **AC7**: Given the user fills `foundAnimalDescription` and `triageNotes` in `modal-checkin` and submits, then: 303 redirect → new WAITING entry in DB with `arrivalMode=STANDARD` → new card visible in queue → `modal-checkin` closed (default `hidden` after page reload).

- [x] **AC8**: Given the user fills `modal-urgence` and submits, then: new entry with `arrivalMode=EMERGENCY, priority=2` → card at top of queue (EMERGENCY sort order) → `modal-urgence` closed.

- [x] **AC9**: Given a queue card link is clicked, when the detail frame loads via `<a data-turbo-frame="patient-detail">`, then: `#patient-detail-panel` becomes visible (Stimulus removes `is-hidden`), `WaitingRoomEntryDetailsDTO` fields are shown. No JS click handler wired — navigation is Turbo-native.

- [x] **AC10**: Given the detail panel is open, when Escape is pressed, then: `#patient-detail-panel` becomes hidden (Stimulus adds `is-hidden`), any open modal also closes.

- [x] **AC11**: Given the page loads, then: topbar date is today's date in French (e.g. "Samedi 19 avril 2026"), clock ticks in real time via Stimulus. Status bar counts are inside the queue frame and refresh with it.

- [x] **AC12**: Given the rendered HTML of the page, then: `modal-appel` is absent, no `wr-modal-overlay` class exists anywhere, no `data-turbo-temporary` attribute exists, no mock patient names from JS appear, no `PATIENTS` array reference exists.

- [x] **AC13**: Given `make ci` runs after all changes, then php-cs-fixer, phpcs, phpstan, tailwind-build, and tests all pass.

---

## Additional Context

### Dependencies

- `ListWaitingRoom` — existing, integration-tested; called only by `WaitingRoomQueueController` post-refactor
- `GetWaitingRoomEntryDetails` — existing handler (no HTTP route yet; Task 4 creates it); `HandlerFailedException` handling required
- `CreateWaitingRoomWalkInEntry` — existing, unit-tested
- `UpdateWaitingRoomTriage` — existing, unit-tested
- `StartServiceForWaitingRoomEntry` — existing, unit-tested
- `CloseWaitingRoomEntry` — existing, unit-tested
- `WaitingRoomEntryEntityFactory` (Foundry) — existing; used in Task 18 for temporal fixture states
- `SchedulingStory` — modified in Task 17 to expose appointment refs via `$this->add()`
- `ClinicDataStory::INDEPENDENT_CLINIC_ID` — needed in `WaitingRoomStory`
- PHP `intl` extension — required for `IntlDateFormatter`; guaranteed present in Symfony 7
- No new migrations, no new domain objects, no new Symfony services

### Testing Strategy

- Presentation layer excluded from 100% coverage (CLAUDE.md)
- All command/query handlers already have unit/integration tests
- No new unit tests required
- Manual testing checklist:
  1. `make fixtures` → 7 entries visible, correct statuses ✓
  2. Filter tabs: Tous / Urgences / En attente / Consultation → correct subsets ✓
  3. Browser Back after filter click → returns to previous page (not previous filter) ✓
  4. Wait 20s → frame reloads silently, stats update ✓
  5. Walk-in check-in: submit → new card in queue ✓
  6. Urgence: submit → card at top with EMERGENCY badge ✓
  7. Démarrer: status IN_SERVICE, stays on waiting room ✓
  8. Terminer: card disappears, stays on waiting room ✓
  9. Card click → detail panel opens with real DTO data, no JS click handler ✓
  10. Escape → detail panel closes ✓
  11. Empty state: delete all entries → reload → empty state + CTA visible ✓
  12. Rendered HTML: no `modal-appel`, no `wr-modal-overlay`, no `PATIENTS`, no `data-turbo-temporary` ✓
  13. CSRF: submit form without token → 403 ✓
  14. `make ci` green ✓

### Notes

- **`HandlerFailedException` pattern reuse**: This will recur on consultation and hospitalisations controllers. Consider a protected helper method on `AbstractController` or a Messenger middleware that unwraps and rethrows once the pattern appears a third time.
- **Mercure exit door**: Replace `auto-refresh` Stimulus controller with Mercure subscription on `WaitingRoomEntryCreated`, `WaitingRoomEntryServiceStarted`, `WaitingRoomEntryClosed` when concurrent multi-user becomes a measured use case. Domain events already exist.
- **Empty-state CTA placement (Task 13 note)**: The CTA button is outside the frame and always visible below an empty queue. If always-visible is undesirable, a Stimulus observer on the frame or a CSS `:has()` selector (`turbo-frame:not(:has(*)) + .wr-queue-empty-cta`) can toggle it — but `:has()` browser support should be verified first.
- **`SchedulingStory` `$this->add()` naming**: Confirm the exact appointment IDs and naming keys during Task 17 implementation before writing Task 18, to avoid a lookup mismatch at fixture load time.
