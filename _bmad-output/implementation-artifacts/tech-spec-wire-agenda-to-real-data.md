---
title: 'Wire Agenda Page to Real Backend Data'
slug: 'wire-agenda-to-real-data'
created: '2026-04-11'
status: 'completed'
stepsCompleted: [1, 2, 3, 4, 5, 6]
tech_stack:
  - PHP 8.5
  - Symfony 7.4
  - Doctrine DBAL (raw SQL for read queries)
  - Twig 3
  - Tailwind CSS v4 + custom tokens (kiveto.css)
  - Vanilla JS (no framework), Turbo Drive, Stimulus
  - PHPUnit 12, Foundry 2.x
files_to_modify:
  - src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php
  - src/Presentation/Clinic/Controller/Scheduling/Appointment/CheckInAppointmentController.php
  - src/Context/Scheduling/Application/Query/GetAgendaForClinicDay/* (rename to GetAgendaForClinicDateRange)
  - tests/Integration/Context/Scheduling/Application/Query/GetAgendaForClinicDay/* (rename)
  - src/Context/Clinic/Application/Port/ClinicMembershipReadRepositoryInterface.php (extend)
  - src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepository.php (extend)
  - src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/* (new)
  - templates/clinic/scheduling/agenda/index.html.twig
  - templates/clinic/scheduling/_scheduling_aside.html.twig
  - assets/js/pages/scheduling/agenda.js (full rewrite, ~1067 → ~400 lines)
  - fixtures/Context/Scheduling/Story/SchedulingStory.php (populate from empty)
  - fixtures/Dataset/ClinicDataset.php (register SchedulingStory)
  - tests/Integration (new tests for new queries + fixture smoke)
code_patterns:
  - Single-action controllers (__invoke, no business logic)
  - Raw DBAL for read queries (no QueryBuilder)
  - Read repo interfaces in Application/Port, adapters in Infrastructure/Persistence/Doctrine/Repository
  - QueryBus dispatch with \assert() for PHPStan type narrowing
  - Foundry PersistentProxyObjectFactory for Doctrine entity seeding
  - Story class with DI constructor (ClockInterface, CommandBusInterface)
  - Twig JS data injection via <script type="application/json"> + JSON.parse
  - Turbo Drive navigation via <a href="?query=params">
  - CSRF tokens via csrf_token('id') Twig function
test_patterns:
  - Unit: named constructors + DTOs (direct instantiation, no mocks)
  - Unit: handlers with createMock/createStub for ports
  - Integration: Doctrine repositories via KernelTestCase + Foundry factories + DAMA auto-rollback
  - Integration: raw DBAL query handlers against seeded DB
  - Integration: fixture smoke test loading ClinicDataset and asserting row counts
  - self::assertSame() everywhere, never assertEquals()
---

# Tech-Spec: Wire Agenda Page to Real Backend Data

**Created:** 2026-04-11

## Overview

### Problem Statement

The Agenda page (`clinic_scheduling_agenda` route) is visually complete but
entirely disconnected from the backend. `AgendaController` correctly dispatches
`GetAgendaForClinicDay` and `ListWaitingRoom` queries and passes the real data
to the template, but:

- The Twig template ignores the `appointments` variable completely
- `assets/js/pages/scheduling/agenda.js` (~1067 lines) renders ~200 hardcoded
  appointments from a baked-in `RDVS` array spanning March 2026
- Veterinarians are hardcoded in a `VETS` object (4 fake vets)
- Animals/owners are hardcoded in an `ANIMALS` array (12 fake patients)
- There is no day/week navigation that actually changes the displayed data
- `SchedulingStory` fixture is empty so there is no seed data to test against
- The veterinarian list in the week view header is hardcoded; even if
  we sourced it from the returned appointments, a vet with zero
  appointments on the displayed week would disappear from the column
  header

Result: no matter which clinic the user selects or which date they navigate
to, they always see the same baked-in March 2026 demo data.

### Solution

Replace the hardcoded JS data with real server-injected data from the
controller:

1. Extend `GetAgendaForClinicDay` → `GetAgendaForClinicDateRange` so the
   week view can fetch 7 days in one query (current DBAL query already
   takes a start/end range internally — we just expose two parameters
   instead of one date).
2. Add a new query `ListClinicVeterinarians(clinicId)` in the Clinic BC
   that returns the clinic's memberships filtered by ACTIVE status and
   `role = VETERINARY` (**veterinarians only** — assistants do NOT take
   their own appointments and therefore do NOT appear as agenda columns).
   This is the **source of truth** for the week-view column headers —
   NOT the distinct `practitionerUserId` values from the returned
   appointments (a vet with zero appointments this week must still
   appear). Name chosen intentionally: `ListClinicVeterinarians` locks
   the semantics in the query name itself — if a different use case
   ever needs assistants in an agenda-like view, it will be a separate
   query with its own semantics, not a parameter added to this one.
3. Update `AgendaController` to accept `?date=` and `?view=day|week`,
   dispatch **both** queries (`GetAgendaForClinicDateRange` +
   `ListClinicVeterinarians`), and pass `appointments` + `veterinarians`
   + `view` + `selectedDate` to the template. The controller stays a
   thin dispatch-and-render single-action. **Architectural lock:** the
   Scheduling BC must NOT dispatch a Clinic BC query via the bus
   internally, and the DBAL handler must NOT JOIN across `scheduling__*`
   and `clinic__*` tables. The controller is the only place where the
   two BCs' data are combined — that's the pattern already used
   elsewhere in the project (each BC's query stays isolated). Date
   range computation goes into the query DTO's named constructors,
   veterinarian label generation ("Praticien A" / "Moi") goes into a
   small helper (likely JS, decided in Step 2).
4. Inject the data into JS via a **`<script type="application/json">`**
   block (not a `<script>` with executable JS). The template emits:
   ```twig
   <script type="application/json" id="agenda-data">
     {{ {
       appointments: appointments,
       veterinarians: veterinarians,
       view: view,
       selectedDate: selectedDate|date('Y-m-d'),
       currentUserId: currentUserId,
       clinicTimezone: clinicTimezone,
       checkinUrlTemplate: path('clinic_scheduling_appointment_checkin', {appointmentId: '__ID__'}),
     }|json_encode|raw }}
   </script>
   ```
   - `clinicTimezone` (string, e.g. `"Europe/Paris"`) — used by
     the JS to convert the UTC datetime strings from
     `AppointmentItem.startsAtUtc` into clinic-local display
     times.
   - `checkinUrlTemplate` — the `path()` function generates the
     route URL with the literal placeholder `__ID__` in place
     of the real appointment ID. The JS does a `.replace('__ID__', appointmentId)`
     at submit time. This keeps route shape changes in the
     server's hands (if the route ever gets a prefix, the JS
     keeps working silently).
   The JS reads it with
   `JSON.parse(document.getElementById('agenda-data').textContent)`.
   This approach is **strictly safer** than injecting into executable
   JS: the browser never parses the content as JavaScript, so no
   `</script>` escape issues, no `JSON_HEX_*` flags needed, no XSS risk
   from apostrophes/quotes in appointment motifs. The template must
   also include a Twig comment block above the `<script>` documenting
   the full expected JSON shape (the contract between server and JS).
5. Rewrite `agenda.js` to consume `window.AGENDA_DATA`. Remove the
   `RDVS`, `VETS`, `ANIMALS` constants. Keep all rendering / popup
   behaviour **except** the drag-to-create-appointment selection, which
   must be explicitly disabled in this iteration (no handlers wired, no
   visual affordance).
6. Veterinarian column labels: deterministic placeholders
   `Praticien A`, `Praticien B`, … assigned by position in the
   veterinarian list returned by `ListClinicVeterinarians`, with stable
   colors from a palette. (UI label stays "Praticien" in French even
   though the query returns veterinarians only — "Praticien" is the
   existing user-facing term in the design and reads naturally.)
   **Special case:** if `veterinarian.userId === currentUserId`, the
   label becomes "**Moi**" and uses a **distinctive reserved color**
   (NOT brand primary — brand primary is already used for buttons,
   links, sidebar highlights, and would visually blend). Pick a color
   dedicated to the "Moi" column that is not used anywhere else in the
   app (concrete value decided in Step 2 based on the existing palette).
   This lets the logged-in vet instantly recognise their own column
   without needing a real user profile.
7. Day/week navigation = full Turbo Drive page loads via GET
   `?date=YYYY-MM-DD&view=day|week` — no fetch, no JSON endpoint.
   The **controller pre-computes all navigation URLs** (`prevDate`,
   `nextDate`, `todayLink`, `weekViewLink`, `dayViewLink`) and
   passes them to the template. The template renders them as
   `<a href>` links with zero JS navigation logic.
8. Wire only the "**Salle d'attente**" button (check-in) in the RDV
   detail popup to a **single** hidden `<form id="checkin-form">`
   in the template. On popup open, JS sets `form.action` to the
   appointment-specific URL and the button triggers `form.submit()`.
   The controller validates the CSRF token (Task 4.0), dispatches
   the `CreateWaitingRoomEntryFromAppointment` command, adds a
   **plain-text success flash** `"Patient enregistré en salle
   d'attente."`, and redirects to `clinic_scheduling_agenda`. The
   user stays on the agenda. The flash renders as a JS toast (via
   the existing `kiveto/toast` system) — a clickable link to the
   waiting room is NOT possible in this iteration (flash system
   limitation, see Notes "UX debt — flash toast has no clickable
   link"). The "Cancel" button is **out of scope** (verified:
   `CancelAppointment` command+handler already exist in the
   Scheduling BC; only a thin Presentation controller is missing,
   which is ~100 lines of follow-up work).
9. CANCELLED appointments: **hidden from the grid in this iteration**.
   The existing JS filter (`agenda.js:809,884`:
   `a.status !== 'cancelled'`) **must be updated** to compare
   against uppercase `'CANCELLED'` because the real
   `AppointmentStatus` enum values are UPPERCASE (`PLANNED`,
   `COMPLETED`, `CANCELLED`, `NO_SHOW`) — the old hardcoded
   data used lowercase, the real server data doesn't. A future
   iteration will add a toggle to show / hide cancelled
   appointments (see Out of Scope). Past (COMPLETED) appointments
   remain visible with a pale background but keep the border
   color so the veterinarian association stays readable — the
   default styling already implemented in the current JS still
   applies once the case comparison is fixed.
10. Seed `SchedulingStory` with **at least 1 appointment per day** over
    the 7-day window anchored on `ClockInterface::now()->modify('monday this week')`,
    for both Paris and Lyon, across multiple veterinarians and all four
    statuses (PLANNED, COMPLETED, CANCELLED, NO_SHOW). Include at least
    one appointment whose motif/reason contains an apostrophe or accent
    to catch JSON-encoding bugs.

### Scope

**In Scope:**

- Extend `GetAgendaForClinicDay` query to `GetAgendaForClinicDateRange`
  (or add a new query that reuses the same handler logic — rename vs
  coexistence decided in Step 2 based on who calls the existing query).
  The new DTO has named constructors `::forDay($date, \DateTimeZone $tz)`
  and `::forWeek($anchorDate, \DateTimeZone $tz)` that take the
  **clinic's timezone** as an argument. The range is computed in the
  clinic's local timezone and converted to UTC for the SQL query — NOT
  in the server's default timezone (`new \DateTimeImmutable('monday
  this week')` is forbidden for this use). The controller loads the
  clinic aggregate via the existing `GetClinic` query to get the
  timezone, then passes it to the DTO.
- Add new query `ListClinicVeterinarians(clinicId)` in the Clinic BC
  (`Application/Query/Staff/ListClinicVeterinarians`). Returns a
  `list<ClinicVeterinarianItem>` with `userId`, `role`, `engagement`.
  Filtered by `ClinicMembershipStatus::ACTIVE` and **role = VETERINARY
  only** (assistants are out — see Solution §2 for rationale). Uses a
  dedicated read repository port + Doctrine adapter OR extends the
  existing `ClinicMembershipReadRepositoryInterface` — decision in
  Step 2. **Mandatory SQL ordering**: `ORDER BY created_at_utc ASC,
  user_id ASC` so the list is stable across reloads — label/color
  assignment in the UI depends on a deterministic order.
- Update `AgendaController` to:
  - accept `?date=YYYY-MM-DD` and `?view=day|week` query params
    (defaults: today in the clinic's timezone, week)
  - load the clinic via `GetClinic` to obtain its timezone
  - dispatch `GetAgendaForClinicDateRange` + `ListClinicVeterinarians`
  - pass `appointments`, `veterinarians`, `selectedDate`, `view`,
    `currentUserId` to the template
  - **no business logic in the controller** — it's dispatch + render.
    **Architectural lock**: the controller is the only place where
    data from Scheduling BC and Clinic BC meet. No cross-BC SQL JOINs,
    no Scheduling handler dispatching Clinic queries via the query bus.
- Template (`templates/clinic/scheduling/agenda/index.html.twig`):
  - inject data via a **`<script type="application/json"
    id="agenda-data">`** block fed by Twig's `|json_encode|raw`
    (see Solution §4 for the exact snippet and rationale)
  - include a Twig comment block above the `<script>` documenting
    the full expected shape of the payload (contract between server
    and JS)
  - the JS parses the payload with
    `JSON.parse(document.getElementById('agenda-data').textContent)`
    — no `window.AGENDA_DATA` global setter needed
- Rewrite `assets/js/pages/scheduling/agenda.js` to consume the parsed
  `agenda-data` payload. Remove `RDVS`, `VETS`, `ANIMALS`. Keep the
  render / popup / status-color logic. **Disable** the drag-to-create
  selection (no handlers bound, no cursor affordance). Target post-
  rewrite size: ~400 lines (from ~1067).
- Update the existing `status !== 'cancelled'` filter (lowercase) in
  day/week render pipelines (`agenda.js:809,884`) to
  `status !== 'CANCELLED'` (uppercase) — the real
  `AppointmentStatus` enum uses uppercase values, the old
  hardcoded data used lowercase. Same for any other status
  comparisons: `COMPLETED`, `PLANNED`. Cancelled appointments
  are hidden in this iteration; seeded CANCELLED appointments
  serve as a guard that the filter works (AC-Cancelled-hidden).
- "Praticien X" / "Moi" label logic: deterministic by index, with the
  special-case for `currentUserId`. Implemented as a small helper
  (likely JS function, since the data is already in the JS payload).
  **Reserved color for "Moi"** — not brand primary (which is already
  used for buttons/links/sidebar highlights and would visually blend).
  Pick a dedicated color from the existing palette in Step 2.
- Wire the **check-in** button in the RDV detail popup to a Turbo
  `<form>` POST to `clinic_scheduling_appointment_checkin/{appointmentId}`
  with CSRF token (`'submit'` — stateless, reusable). Full-page
  redirect response staying on the agenda.
- **Add server-side CSRF validation** in `CheckInAppointmentController`
  (the controller currently has none — verified). Use
  `CsrfTokenManagerInterface::isTokenValid()` with the `'submit'`
  token ID matching the form. Without this, the client-side
  `_token` is security theatre and AC-Checkin-CSRF cannot be
  verified.
- **Extend `ClinicMembershipDataStory`** (Clinic BC fixture) to
  add a 3rd Paris veterinarian user (e.g. `vet2@kiveto.local`)
  so AC-Empty-veterinarian has real data to assert against.
  This is a tiny cross-BC fixture addition; it respects the
  rule "memberships are written by Clinic BC stories only".
- Day/week navigation arrows update the URL (`?date=`, `?view=`) and let
  Turbo Drive reload the page (`<a href>` with query params, no JS
  state management).
- Seed `fixtures/Context/Scheduling/Story/SchedulingStory.php` with:
  - At least 1 appointment per day of the current week
  - Both Paris and Lyon clinics covered
  - At least 2 distinct veterinarians per clinic
  - All four statuses represented (PLANNED, COMPLETED, CANCELLED, NO_SHOW)
  - At least one motif containing an apostrophe / accent (e.g.
    `"Vaccination d'Artagnan"`, `"Contrôle poids"`)
  - Anchor: `ClockInterface::now()->modify('monday this week')`
- Integration test that loads `ClinicDataset` and asserts the scheduling
  seed data is persisted as expected (guards against silent seed
  breakage).
- `make ci` green, 100% coverage on new/changed code in Clinic +
  Scheduling BCs (Presentation layer still excluded).

**Out of Scope:**

- **Cancel appointment action** — no cancel route currently exists in
  the scheduling routes; adding one requires a new controller, route,
  command wiring, CSRF form, and test. Separate spec. The cancel button
  in the popup is visually disabled / hidden in this iteration.
- **Drag-to-create-appointment selection** — explicitly disabled in
  this iteration. The drag handlers are removed from the JS, not just
  left orphaned.
- **Real practitioner display names** (first name, last name) via a
  cross-BC `IdentityAccess → Clinic` port. `ClinicMembership` does not
  store any denormalised `displayName` / `fullName` field (verified),
  so placeholders "Praticien A/B/C" + "Moi" for the current user are
  the only honest option until a dedicated cross-BC feature is
  implemented.
- **Create-appointment workflow** (client / animal search, conflict
  validation, new-RDV popup submit). Separate spec.
- **Reschedule via drag-and-drop** (`RescheduleAppointment` command
  wiring). Separate spec.
- **Planning page** (`clinic_scheduling_planning`) — similar problem
  but different controller/template. Follow-up spec.
- **Waiting-room sidebar panel inside the agenda page** — the
  waiting-room page is already server-side functional; do not change
  it. `AgendaController` may continue passing `waitingRoomEntries` to
  the template if it already does; the template does not need to
  render it in this iteration.
- **JSON / fetch API endpoints.** Everything is Turbo Drive GETs and
  form POSTs.
- **Real-time updates** via Turbo Streams / Mercure.
- **Filter by practitioner, filter by status, search** — keep the
  filter UI in the template but do not wire it (non-functional for
  now). To be confirmed in Step 2: either hide the filter UI or leave
  it visibly disabled.
- **Toggle to show/hide cancelled appointments** — useful for audit /
  recovery workflows but non-essential for the operational agenda.
  Dedicated follow-up spec: add a toggle in the filter bar that
  switches the `status !== 'cancelled'` filter on/off, with user
  preference persisted in localStorage or session.

## Context for Development

### Codebase Patterns

- **Read queries:** `GetAgendaForClinicDayHandler` uses raw DBAL
  (`Connection::fetchAllAssociative`) for performance. The new
  `GetAgendaForClinicDateRange` should follow the same pattern and reuse
  the same table / index.
- **Write vs read repository split:** write repositories live in
  `Domain/Repository/`, read repositories in `Application/Port/`. The
  new `ListClinicVeterinarians` query is a pure read, so its port goes
  in `Application/Port/` and its adapter in
  `Infrastructure/Persistence/Doctrine/Repository/`.
- **Controller:** single-action `__invoke()` extending `AbstractController`;
  queries dispatched via `$this->queryBus->ask(...)`; `\assert()` used to
  narrow `mixed` return types for PHPStan. **No business logic inside
  the controller.**
- **Clinic context:** `CurrentClinicContextInterface::getCurrentClinicId()`
  gives the active clinic.
- **Template injection of JS data:** for the agenda page we use the
  **`<script type="application/json" id="agenda-data">`** pattern
  (strict JSON, parsed by JS with
  `JSON.parse(document.getElementById('agenda-data').textContent)`).
  This is safer than the `window.CLINICS` pattern used in
  `templates/clinic/select-clinic.html.twig:96` because the agenda
  payload contains user-generated content (appointment motifs and
  notes) where an apostrophe or `</script>` sequence could break or
  compromise an inline `<script>` block. For the select-clinic page
  the `window.*` pattern is fine because the data is constrained
  (clinic slug/name from a trusted source). **Rule of thumb**: use
  `<script type="application/json">` whenever the payload contains
  free-text user input; use `window.*` only for trusted, constrained
  data.
- **Cross-BC data in a template:** the controller dispatches multiple
  queries (one per BC) and combines the results in the template. No
  BC's query handler dispatches another BC's query via the query bus
  internally; no SQL handler JOINs tables from another BC. The
  controller is the integration point. Applied here: `AgendaController`
  dispatches `GetAgendaForClinicDateRange` (Scheduling BC) AND
  `ListClinicVeterinarians` (Clinic BC) AND `GetClinic` (Clinic BC,
  for the timezone), then hands all three results to the template.
- **Clinic timezone for date ranges:** time-sensitive queries that
  operate on a "day" or "week" concept must use the clinic's own
  timezone, not the server default. The clinic aggregate has a
  `TimeZone` VO — load it before computing any date range. A user
  who clicks "this week" on Sunday 23:45 Paris time expects the week
  starting Monday morning Paris time, not the UTC rollover.
- **Fixtures:** Foundry `PersistentProxyObjectFactory` + `Story`
  subclasses, loaded via `ClinicDataset`. Use `ClockInterface::now()`
  for anchor dates so seeds stay relevant over time.
- **Turbo Drive navigation:** all internal links are AJAX by default;
  `data-turbo="false"` is the escape hatch for full-page navigation.
  Day/week navigation arrows here stay Turbo-enabled (that's the
  point).
- **Form submits:** classic POST forms for auth pages, Turbo-enabled by
  default for VetApp pages; include CSRF token manually with
  `csrf_token('<token_id>')`.
- **Existing cancelled-style JS:** `agenda.js:429-477` already contains
  the `isCancelled` branch with `strikethrough` styling (dead code in
  this iteration — the upstream filter hides cancelled RDVs before
  they reach this branch). Do not delete the dead branch: the future
  "show/hide cancelled toggle" spec will reuse it.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php` | Current controller — add `?date=` and `?view=` query params, load clinic timezone, dispatch the extended query + veterinarians query, pass new variables to template |
| `src/Context/Scheduling/Application/Query/GetAgendaForClinicDay/GetAgendaForClinicDay.php` | **Rename** to `GetAgendaForClinicDateRange`, add named constructors `::forDay($date, $tz)` / `::forWeek($anchorDate, $tz)` |
| `src/Context/Scheduling/Application/Query/GetAgendaForClinicDay/GetAgendaForClinicDayHandler.php` | **Rename** to `GetAgendaForClinicDateRangeHandler`, extend SQL `BETWEEN` to cover the range, keep the raw DBAL pattern |
| `src/Context/Scheduling/Application/Query/GetAgendaForClinicDay/AppointmentItem.php` | Already flat scalar DTO — no changes. Shape: `id, clinicId, ownerId?, animalId?, practitionerUserId?, startsAtUtc (string "Y-m-d H:i:s" UTC), durationMinutes (int), status (string), reason?, notes?` |
| `tests/Integration/Context/Scheduling/Application/Query/GetAgendaForClinicDay/GetAgendaForClinicDayHandlerTest.php` | Rename + extend with a 7-day range test and a non-UTC timezone test |
| `src/Context/Clinic/Application/Port/ClinicMembershipReadRepositoryInterface.php` | **Extend** with a new method `findVeterinariansForClinic(ClinicId): list<ClinicVeterinarianItem>` (existing port currently has only `findAccessibleClinicsForUser`) |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepository.php` | Implement the new `findVeterinariansForClinic` method via DBAL (raw SQL, similar pattern to `findAccessibleClinicsForUser`) |
| **NEW** `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinarians.php` | New query DTO — constructor `(string $clinicId)` |
| **NEW** `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinariansHandler.php` | Thin handler that delegates to `findVeterinariansForClinic` |
| **NEW** `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ClinicVeterinarianItem.php` | Result DTO — `(userId: string, role: string, engagement: string)` |
| `src/Presentation/Clinic/Controller/Scheduling/Appointment/CheckInAppointmentController.php` | **Change success redirect** from `clinic_scheduling_agenda` to `clinic_scheduling_waiting_room` so AC-Smoke works (error path still returns to agenda) |
| `templates/clinic/scheduling/agenda/index.html.twig` | Remove hardcoded `selected_date: '2026-03-23'` in the aside include (line ~30), use real `selectedDate`. Add `<script type="application/json" id="agenda-data">…</script>` block with the full payload |
| `templates/clinic/scheduling/_scheduling_aside.html.twig` | **Remove hardcoded vet chips** (Rousseau/Martin/Dupont/Lambert, lines ~33-52). Replace with a loop that reads from the same `veterinarians` variable. Motif filter stays hardcoded (out of scope). |
| `assets/js/pages/scheduling/agenda.js` | Full rewrite — remove `VETS`, `ANIMALS`, `RDVS` (lines ~1-350). Read from `JSON.parse(document.getElementById('agenda-data').textContent)`. Keep render / popup / lane layout / now-line logic. Remove `openNewRdvGlobal`, `confirmNewRdv`, `searchAnimals`, `selectAnimal`, `editRdv`, `cancelRdv`, `openNewRdv` handlers (all out of scope). Keep `placerSalleAttente` but wire it to submit a `<form>` POST to the check-in route. Navigation (`prevWeek`, `nextWeek`, `goToday`, `setView`) becomes `<a href>` links with `?date=&view=` Turbo Drive reload. |
| `fixtures/Context/Scheduling/Story/SchedulingStory.php` | Currently empty — populate with seed data. Inject dependencies via constructor: `ClockInterface`, `CommandBusInterface` (for `ScheduleAppointment`), and repository accessors for existing fixtures. |
| `fixtures/Dataset/ClinicDataset.php` | Add `SchedulingStory::load()` call at the end of `build()` (after `AnimalDataStory::load()` so owners and animals exist) |
| `templates/clinic/scheduling/_agenda.html.twig` | **Dead code** — not referenced by any template. Flag for deletion in a follow-up chore (do NOT delete in this spec). |

### Technical Decisions

| # | Decision | Rationale |
|---|---|---|
| **D1** | **Rename** `GetAgendaForClinicDay` → `GetAgendaForClinicDateRange` (A1 from Winston#2) | Verified via `grep -r`: only `AgendaController` and its integration test call it. No other callers. Coexistence is unnecessary and leaves orphan code. |
| **D2** | **Extend** `ClinicMembershipReadRepositoryInterface` with `findVeterinariansForClinic` rather than creating a new port | The port is a single-method read repo for clinic memberships. Adding a second method is the natural evolution. A separate port would be 3 extra files for no gain. **Accepted ISP trade-off**: the two methods are on different aggregation axes (one user-centric, one clinic-centric) which technically violates strict Interface Segregation Principle, but enforcing ISP here would mean creating a new single-method port just for UI display data — over-engineering for the current scale. If a third unrelated method appears later (e.g. `countActiveMembershipsForClinicGroup`), split the port then. |
| **D3** | **Do NOT reuse** `MembershipEligibilityCheckerInterface::listEligiblePractitionerUsersForClinic` from the Scheduling BC | That port is designed for Scheduling handlers (appointment creation eligibility check). Using it from a Presentation controller to populate an agenda UI creates semantic coupling (the agenda "happens to query a scheduling eligibility port"). The new Clinic BC query `ListClinicVeterinarians` is the correct semantic home. Accept a tiny SQL near-duplication between `DbalMembershipEligibilityChecker` and the new `findVeterinariansForClinic` — both are < 20 lines of SQL, distinct in semantics (one checks eligibility at a point in time, the other lists current staff for UI display). |
| **D4** | **`<script type="application/json" id="agenda-data">`** pattern with `JSON.parse` (Amelia#3 from Round 2) | Strictly safer than injecting into executable JS: the browser never parses content as JavaScript, so apostrophes/quotes/`</script>` in free-text appointment notes cannot break the page or create XSS. No `JSON_HEX_*` flags needed. Twig `\|json_encode\|raw` is sufficient. |
| **D5** | **Clinic timezone for all date range math** (Amelia#5 from Round 2) | The existing `App\Shared\Domain\Localization\TimeZone` VO (at `src/Shared/Domain/Localization/TimeZone.php`) is the source of truth: `fromString()` validates against `\DateTimeZone::listIdentifiers()`, `toNative()` returns a `\DateTimeZone`. The `Clinic` aggregate **already uses** this VO (`Clinic.php:30: private TimeZone $timeZone`). The `ClinicDto::$timeZone` is a scalar string for Messenger serialization, but because all writes to the aggregate go through the VO, the DB can only contain valid IANA timezone strings. The controller does `TimeZone::fromString($clinic->timeZone)->toNative()` to get the `\DateTimeZone`. **Trust the VO**: since writes are VO-validated, we do NOT add defensive try/catch in the controller. If `fromString()` throws, it's a system-level bug (corrupted DB) that deserves a 500, not a user-facing error. Forbids `new \DateTimeImmutable('monday this week')` at server default timezone. |
| **D6** | **Keep `CheckInAppointmentController` success redirect on the agenda with plain-text success flash** (Round 4 final) | Rationale: teleporting the user to the waiting-room page after check-in is a harsh UX context switch — a vet processing 8 morning appointments in sequence should stay on the agenda. Keep the redirect on `clinic_scheduling_agenda`. **Flash message limitation discovered in Round 4**: the project's flash system is JS-toast-driven (`base.html.twig:21-25` + `app.js:33-42`), flashes are plain strings consumed as `dataset.message` — no HTML injection possible. The Round 3 vision of a clickable "Voir la salle d'attente →" link in the flash is NOT implementable. **Final decision**: use a plain text success flash `"Patient enregistré en salle d'attente."` and accept the UX debt (documented in Notes). The user manually navigates to the waiting-room via the sidebar if they want to verify. No base template modification, no new flash partial, zero scope creep. **Before touching this controller, Phase 0 Task 0.1 must `grep -rn "clinic_scheduling_agenda" tests/` to find any functional test asserting on the redirect URL.** |
| **D7** | **`ListClinicVeterinarians` SQL ordering**: `ORDER BY created_at_utc ASC, user_id ASC` | Deterministic ordering is required so that "Praticien A / B / C" labels and colors remain stable across reloads. Added `user_id ASC` as a tiebreaker for the extremely rare case of two memberships created in the same microsecond. |
| **D8** | **"Moi" reserved color** — hardcoded `#0d9488` (teal-600) or `#0891b2` (cyan-600), NOT `var(--brand-*)` | The project palette (`kiveto.css`) defines only `--brand-*` and `--slate-*` as main tokens. Using a brand color for "Moi" would visually blend with buttons/sidebar. Using `--success-*` or `--warning-*` conflicts with their semantic meaning. A one-off hardcoded hex outside the palette is acceptable for this single use — add a comment explaining why. Exact value (teal vs cyan vs other) decided during implementation by visual contrast test. |
| **D9** | **`SchedulingStory` receives `ClockInterface` via constructor DI** | Same pattern as `ClinicMembershipDataStory` which injects `CommandBusInterface`. Stories ARE DI-enabled. Using `$this->clock->now()->modify('monday this week')` makes the seed anchor deterministic within a single test run and allows overriding in tests via a fake Clock (future-proofing). Use the clinic's timezone too: `$this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->modify('monday this week')`. |
| **D10** | **Veterinarian filter in `_scheduling_aside.html.twig`** is wired to real data | The aside filter (`.vet-chip` blocks) currently hardcodes 4 demo vets with `onclick="toggleVet(this, 'rousseau')"` etc. These become a Twig `{% for %}` over the same `veterinarians` variable, with `data-vet-id="{{ vet.userId }}"` instead of hardcoded slugs. The JS `toggleVet()` uses the data attribute. **Motif filter** (line ~60) stays hardcoded — it's tied to the out-of-scope create-RDV popup. |
| **D11** | **Cancel stays out of scope but rationale corrected** | `CancelAppointment` command + handler **already exist** in the Scheduling BC (verified: `src/Context/Scheduling/Application/Command/CancelAppointment/`). Adding cancel to the agenda popup is a thin Presentation-only change: new controller (~30 lines), new POST route, CSRF form in the popup. Still out of scope for this spec to keep the diff focused on "wiring read data", but the follow-up spec is ~100 lines of work, not a full feature. The cancel button in the popup is visually disabled / hidden. |
| **D12** | **`_agenda.html.twig` partial is dead code** | Verified via `grep -r '_agenda.html.twig'`: only its own file and the WIP spec reference it. No `include()` or `embed()` anywhere. Flagged for deletion in a follow-up chore; NOT deleted in this spec to keep the diff minimal. |
| **D13** | **ONE check-in form in the template** (Round 3 correction) | NOT a hidden form per appointment (would bloat the DOM by ~60 KB for 200 RDVs). Instead: a **single** hidden `<form id="checkin-form">` in the Twig template with `method="POST"`, empty `action=""`, and hidden inputs for `_token` = `csrf_token('submit')`, `arrivalMode=STANDARD`, `priority=0`. On popup open, JS sets `form.action = AGENDA_DATA.checkinUrlTemplate.replace('__ID__', appointmentId)` and the "Salle d'attente" button triggers `form.submit()`. **Pattern already validated** on the select-clinic page (`<form id="clinic-select-form">` + JS set `action` + submit). The token ID `'submit'` is declared as a `stateless_token_ids` entry in `config/packages/csrf.yaml` — stateless tokens are explicitly reusable across multiple submits on the same session. **Task 4.0** adds the matching server-side validation (`isTokenValid('submit', $request->request->get('_token'))`) — without it the token is client-side theatre. |
| **D14** | **`arrivalMode` and `priority`** sent in the check-in form | The existing controller reads `arrivalMode` (default `'STANDARD'`) and `priority` (default `0`). The agenda popup form sends `arrivalMode=STANDARD` and `priority=0` as hidden fields. The agenda does NOT offer emergency/priority selection — if the staff needs to mark as urgent, they do it on the waiting-room page afterwards. |
| **D15** | **Day/week navigation — controller pre-computes all nav URLs** (Round 3 refinement) | Arrow buttons become `<a href>` links with query params — no JS navigation handlers. **The controller pre-computes and passes to the template:** `prevDate` (current date -7d in week view, -1d in day view), `nextDate` (symmetric), `todayLink` (the default agenda URL without params), `weekViewLink`, `dayViewLink`. The template just renders them as `<a href="{{ prevDate }}">…</a>`. This keeps **zero** navigation logic in JS. Turbo Drive handles the page swap; `data-turbo-temporary` on `#agenda-inner` and `#mini-cal-grid` ensures those containers are cleared (JS re-renders on each `turbo:load`). **Scroll position**: on `turbo:load`, the JS scrolls the agenda body to `HOUR_START * SLOT_H` (08:00 — the existing behaviour at `agenda.js:411-412`). No attempt to preserve the user's in-grid scroll position across navigation. |

## Implementation Plan

### Tasks

Ordered by dependency — each phase must be complete before the next starts.
Checklist format: mark `[x]` as you go.

#### Phase 0 — Prerequisites & discovery

- [ ] **Task 0.1: Grep for redirect targets in existing tests**
  - Action: Run `grep -rn "clinic_scheduling_agenda" tests/` and
    `grep -rn "clinic_scheduling_appointment_checkin" tests/`
  - Goal: Identify any functional/integration test that asserts on
    the redirect URL of `CheckInAppointmentController`. If none,
    proceed. If one exists, note the file + line to update in Phase
    4.
  - Output: A short list of files to touch later (or "none" if safe).

- [ ] **Task 0.2: Confirm `FrozenClock` exists**
  - Action: Verify `tests/Shared/Time/FrozenClock.php` exists
    (already confirmed during spec writing — smoke check).
    Confirm the class signature:
    `final class FrozenClock implements ClockInterface` with
    constructor `__construct(\DateTimeImmutable $now)` and
    method `now(): \DateTimeImmutable`.
  - Goal: Unblock Phase 9 (seed smoke test).
  - **Already verified during spec writing**: the class exists
    and has the expected signature. No action needed beyond a
    sanity grep.
  - **Also verified during spec writing**: `config/packages/csrf.yaml`
    declares `submit`, `authenticate`, `logout` as
    `stateless_token_ids` — these tokens are reusable. Task
    5.4 and 4.0 both use `'submit'` as the token ID.

- [ ] **Task 0.3: Confirm Scheduling command signatures AND state
      transitions for seeds**
  - Files to read:
    - `src/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointment.php`
      → exact constructor params for `ScheduleAppointment`
    - `src/Context/Scheduling/Application/Command/CancelAppointment/CancelAppointment.php`
      → exact constructor params for `CancelAppointment`
    - `src/Context/Scheduling/Domain/Appointment.php`
      → **critical**: read the state transition methods
      (`startService`, `complete`, `cancel`) and figure out the
      state machine. Specifically: can `PLANNED → COMPLETED`
      happen directly, or does it require going through
      `startService()` first (which likely requires a waiting-
      room entry)?
  - **Expected finding**: `COMPLETED` is almost certainly a
    terminal state that requires passing through the
    waiting-room flow (`CreateWaitingRoomEntryFromAppointment`
    → `StartServiceForWaitingRoomEntry` → close via
    `CloseWaitingRoomEntry` or a `CompleteAppointment` command
    if one exists).
  - **Decision upfront (per Djamil)**: if confirming the chain
    is too long for a simple fixture seed, **drop COMPLETED
    from the seed entirely**. Use only `PLANNED` and `CANCELLED`
    — simpler, valid per domain rules, and still covers the
    "demoted past appointments" visual style via `isPast`
    detection in the JS (which compares `startsAtUtc` to
    `now`, not status). The COMPLETED state is orthogonal to
    the "RDV is visually past" rendering.
  - Output: a ~5-line summary in Phase 0 of what commands to
    use and in what order, to unblock Task 8.2.
  - Goal: No guessing when writing the fixture; avoid the
    "dev spends 30 minutes debugging a seed that won't build"
    trap.

#### Phase 1 — Scheduling BC: rename + extend to date range

- [ ] **Task 1.1: Rename `GetAgendaForClinicDay` directory**
  - Old: `src/Context/Scheduling/Application/Query/GetAgendaForClinicDay/`
  - New: `src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/`
  - Action: Rename the directory, rename files inside:
    - `GetAgendaForClinicDay.php` → `GetAgendaForClinicDateRange.php`
    - `GetAgendaForClinicDayHandler.php` → `GetAgendaForClinicDateRangeHandler.php`
    - `AppointmentItem.php` → keep the name (it's the result DTO)
  - Update namespace declarations in each moved file.

- [ ] **Task 1.2: Update `GetAgendaForClinicDateRange` DTO with
      named constructors and range params**
  - File: `src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/GetAgendaForClinicDateRange.php`
  - Action: Change the constructor to accept a `[fromUtc, toUtc]`
    range (both `\DateTimeImmutable` in UTC). Add two public
    static named constructors that accept the clinic timezone
    as a native `\DateTimeZone` (the Shared `TimeZone` VO is
    converted to native by the caller — see Task 3.2):
    ```php
    public static function forDay(string $clinicId, \DateTimeImmutable $date, \DateTimeZone $clinicTz): self
    public static function forWeek(string $clinicId, \DateTimeImmutable $anchorDate, \DateTimeZone $clinicTz): self
    ```
  - `::forDay`: converts `$date` to clinic-local, sets 00:00:00,
    computes end = +1 day -1 second, converts back to UTC.
  - `::forWeek`: converts `$anchorDate` to clinic-local, computes
    "monday of that week" (Monday 00:00 clinic-local) and Sunday
    23:59:59, converts both to UTC.
  - Keep the optional `?string $practitionerUserId` param.
  - Constructor signature: `public function __construct(public string $clinicId, public \DateTimeImmutable $fromUtc, public \DateTimeImmutable $toUtc, public ?string $practitionerUserId = null)`.
  - **Rationale for `\DateTimeZone` (not `TimeZone` VO) as param**:
    the Scheduling BC should not depend on Clinic BC / Shared
    Localization types at the Application layer. Accepting the
    native `\DateTimeZone` keeps the boundary clean. The caller
    (controller, which is Presentation-layer) is the one who
    converts from the VO to native via `TimeZone::fromString($s)->toNative()`.

- [ ] **Task 1.3: Update `GetAgendaForClinicDateRangeHandler` SQL**
  - File: `src/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/GetAgendaForClinicDateRangeHandler.php`
  - Action: Replace `$startOfDay = $query->date->setTime(0, 0, 0);`
    and `$endOfDay = $query->date->setTime(23, 59, 59);` with direct
    use of `$query->fromUtc` and `$query->toUtc`. The SQL
    `WHERE starts_at_utc >= :from AND starts_at_utc <= :to` stays
    structurally identical, just parameter names change.
  - Keep the `ORDER BY starts_at_utc ASC` clause.
  - Keep the raw DBAL pattern + `hydrateRow` mapping.

- [ ] **Task 1.4: Update `AgendaController` import + query dispatch
      for the renamed query (first pass, without the new
      veterinarians query yet)**
  - File: `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php`
  - Action: Replace the `use` statement and the dispatch call with
    the new class. Keep it functionally equivalent for now (use
    `::forDay` with `new \DateTimeZone('UTC')` as a stopgap —
    **Task 3.2** will fix the timezone properly using the
    Shared TimeZone VO).
  - Goal: Keep the page loadable between Phase 1 and Phase 3.

- [ ] **Task 1.5: Rename & update integration test**
  - Old: `tests/Integration/Context/Scheduling/Application/Query/GetAgendaForClinicDay/GetAgendaForClinicDayHandlerTest.php`
  - New: `tests/Integration/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/GetAgendaForClinicDateRangeHandlerTest.php`
  - Action: Rename, update namespace, update the existing test
    cases to use the new query API (construct via `::forDay(...)`
    where a single day was tested).
  - **Add new test cases:**
    - `testForWeekReturnsAllAppointmentsInThe7DayRange`
    - `testForWeekWithClinicTimezoneParisAtSundayMidnightUtcReturnsCurrentParisWeek`
      (uses a seeded datetime at UTC Sunday 23:30, clinic tz
      `Europe/Paris` — expected `fromUtc` = Monday 23:00 UTC
      (= Monday 00:00 Paris) of the Paris week)
    - `testFilterByPractitionerUserIdStillWorks` (regression)
  - Delete the old integration test file.

- [ ] **Task 1.6: Unit test for `GetAgendaForClinicDateRange` DTO**
  - File: `tests/Unit/Context/Scheduling/Application/Query/GetAgendaForClinicDateRange/GetAgendaForClinicDateRangeTest.php` (new)
  - Tests:
    - `testForDayComputesMidnightToEndOfDayInClinicTimezone`
    - `testForWeekComputesMondayToSundayInClinicTimezone`
    - `testForWeekAtParisSundayLateNightDoesNotRollOverToNextWeek`
      (the timezone edge case from D5)
    - `testConstructorAcceptsOptionalPractitionerUserId`

#### Phase 2 — Clinic BC: `ListClinicVeterinarians` query

- [ ] **Task 2.1: Create `ClinicVeterinarianItem` result DTO**
  - File: `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ClinicVeterinarianItem.php` (new)
  - Action:
    ```php
    final readonly class ClinicVeterinarianItem
    {
        public function __construct(
            public string $userId,
            public string $role,       // always 'VETERINARY' for now, kept for future extension
            public string $engagement, // 'EMPLOYEE' or 'CONTRACTOR'
        ) {}
    }
    ```

- [ ] **Task 2.2: Create `ListClinicVeterinarians` query DTO**
  - File: `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinarians.php` (new)
  - Action:
    ```php
    final readonly class ListClinicVeterinarians implements QueryInterface
    {
        public function __construct(public string $clinicId) {}
    }
    ```

- [ ] **Task 2.3: Extend `ClinicMembershipReadRepositoryInterface`**
  - File: `src/Context/Clinic/Application/Port/ClinicMembershipReadRepositoryInterface.php`
  - Action: Add method signature:
    ```php
    /** @return list<ClinicVeterinarianItem> */
    public function findVeterinariansForClinic(ClinicId $clinicId): array;
    ```
  - Add the `use` for `ClinicId` from
    `App\Context\Clinic\Domain\ValueObject\ClinicId` and
    `ClinicVeterinarianItem`.

- [ ] **Task 2.4: Implement `findVeterinariansForClinic` in Doctrine
      adapter**
  - File: `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepository.php`
  - Action: Add the method with DBAL raw SQL:
    ```sql
    SELECT
        BIN_TO_UUID(user_id) AS user_id,
        role,
        engagement
    FROM clinic__clinic_memberships
    WHERE clinic_id = UUID_TO_BIN(:clinicId)
      AND status = 'ACTIVE'
      AND role = 'VETERINARY'
    ORDER BY created_at_utc ASC, user_id ASC
    ```
  - **No `DISTINCT`** — a user can have at most one ACTIVE
    membership per clinic (enforced at the domain level by
    `existsByClinicAndUser` before `save`). `DISTINCT` would be
    noise that obscures intent.
  - **`ORDER BY ... user_id ASC`** on the binary column, NOT on
    `BIN_TO_UUID(user_id)` — ordering on a function-computed
    column forces a filesort and blocks index usage. Ordering
    on the raw `BINARY(16)` value is deterministic and index-
    friendly. Note: the display layer (JS) doesn't care about
    the exact tie-breaker ordering (as long as it's stable
    across reloads), so ordering by binary UUID is fine.
  - Map each row to `ClinicVeterinarianItem`.
  - **Do NOT** filter by validity window (`valid_from_utc` /
    `valid_until_utc`) at this layer — the membership STATUS is
    the authoritative "active" flag for UI display. This is a
    deliberate divergence from the eligibility checker which
    does filter by validity window (rationale in D3).

- [ ] **Task 2.5: Create `ListClinicVeterinariansHandler`**
  - File: `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinariansHandler.php` (new)
  - Action:
    ```php
    #[AsMessageHandler]
    final readonly class ListClinicVeterinariansHandler
    {
        public function __construct(
            private ClinicMembershipReadRepositoryInterface $readRepository,
        ) {}

        /** @return list<ClinicVeterinarianItem> */
        public function __invoke(ListClinicVeterinarians $query): array
        {
            return $this->readRepository->findVeterinariansForClinic(
                ClinicId::fromString($query->clinicId),
            );
        }
    }
    ```

- [ ] **Task 2.6: Unit test for query DTO + handler**
  - File: `tests/Unit/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinariansTest.php` (new)
  - File: `tests/Unit/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinariansHandlerTest.php` (new)
  - DTO test: construction and property access.
  - Handler test: mock `ClinicMembershipReadRepositoryInterface`,
    verify `findVeterinariansForClinic` is called with the right
    `ClinicId`, pass-through the returned list.

- [ ] **Task 2.7: Integration test for
      `findVeterinariansForClinic`**
  - File: `tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepositoryTest.php`
  - Action: Add new test cases to the existing file:
    - `testFindVeterinariansForClinicReturnsOnlyActiveVeterinariesInOrder`
      — seed 3 veterinarians (ACTIVE, different `created_at`) +
      1 VETERINARY DISABLED + 1 VETERINARY_ASSISTANT + 1 MANAGER;
      assert only the 3 active vets in `created_at_utc ASC` order
      are returned.
    - `testFindVeterinariansForClinicReturnsEmptyArrayWhenNoVeterinarian`
      — clinic with only assistants / managers; assert empty list.
    - `testFindVeterinariansForClinicIsolatesClinicScope`
      — 2 clinics, assert the returned list only contains
      memberships of the target clinic.
  - Use Foundry factories + KernelTestCase.

#### Phase 3 — Presentation: AgendaController refactor

- [ ] **Task 3.1: Add query parameter parsing to `AgendaController`**
  - File: `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php`
  - Action: In `__invoke(Request $request)`, read:
    - `$dateParam = $request->query->get('date')` → parse as
      `\DateTimeImmutable` in the clinic timezone if present,
      otherwise use `$this->clock->now()`.
    - `$viewParam = $request->query->get('view', 'week')` →
      validate `in_array($viewParam, ['day', 'week'], true)`,
      fallback to 'week' otherwise.

- [ ] **Task 3.2: Load clinic timezone via the Shared TimeZone VO**
  - Same file.
  - Action: The existing code already dispatches
    `GetClinic($currentClinicId->toString())` → `$clinic` is a
    `ClinicDto` with `$clinic->timeZone` (scalar string, e.g.
    `"Europe/Paris"`).
  - Use the existing VO to convert to a native `\DateTimeZone`:
    ```php
    use App\Shared\Domain\Localization\TimeZone;
    // ...
    $clinicTz = TimeZone::fromString($clinic->timeZone)->toNative();
    ```
  - **No try/catch** — the `Clinic` aggregate already uses this
    VO (`Clinic.php:30: private TimeZone $timeZone`), so writes
    are validated. If `fromString()` throws here, it's a system-
    level bug (DB corruption), not a user case. Let the 500
    propagate.

- [ ] **Task 3.3: Dispatch the range query using clinic timezone**
  - Same file.
  - Action: Replace the existing `new GetAgendaForClinicDay(...)`
    with the appropriate named constructor:
    ```php
    $query = 'day' === $viewParam
        ? GetAgendaForClinicDateRange::forDay($currentClinicId->toString(), $selectedDate, $clinicTz)
        : GetAgendaForClinicDateRange::forWeek($currentClinicId->toString(), $selectedDate, $clinicTz);
    $appointments = $this->queryBus->ask($query);
    \assert(\is_array($appointments));
    ```

- [ ] **Task 3.4: Dispatch `ListClinicVeterinarians`**
  - Same file.
  - Action:
    ```php
    $veterinarians = $this->queryBus->ask(
        new ListClinicVeterinarians($currentClinicId->toString()),
    );
    \assert(\is_array($veterinarians));
    ```

- [ ] **Task 3.5: Pre-compute navigation URLs**
  - Same file.
  - Action: Compute `$prevDate`, `$nextDate`, `$prevLink`,
    `$nextLink`, `$todayLink`, `$weekViewLink`, `$dayViewLink`
    based on `$viewParam`:
    - Week view: prev = -7d, next = +7d
    - Day view: prev = -1d, next = +1d
  - Use `UrlGeneratorInterface` (the AbstractController provides
    `$this->generateUrl()`) to build the links:
    ```php
    $prevLink = $this->generateUrl('clinic_scheduling_agenda', [
        'date' => $prevDate->format('Y-m-d'),
        'view' => $viewParam,
    ]);
    ```

- [ ] **Task 3.6: Get current user ID**
  - Same file.
  - Action: `$user = $this->getUser()` then cast to `SecurityUser`,
    extract `$currentUserId = $user->id()`. Used by the template
    for the "Moi" column.

- [ ] **Task 3.7: Pass all new variables to the template**
  - Same file.
  - Action: Update the render call:
    ```php
    return $this->render('clinic/scheduling/agenda/index.html.twig', [
        'appointments'       => $appointments,
        'veterinarians'      => $veterinarians,
        'waitingRoomEntries' => $waitingRoomEntries,
        'selectedDate'       => $selectedDate,
        'view'               => $viewParam,
        'clinicTimezone'     => $clinic->timeZone,
        'currentUserId'      => $currentUserId,
        'currentClinicId'    => $currentClinicId->toString(),
        'currentClinicName'  => $clinic->name,
        'prevLink'           => $prevLink,
        'nextLink'           => $nextLink,
        'todayLink'          => $todayLink,
        'weekViewLink'       => $weekViewLink,
        'dayViewLink'        => $dayViewLink,
    ]);
    ```

- [ ] **Task 3.8: Verify controller still loads end-to-end**
  - Action: Reload the page in the browser as `vet@kiveto.local`
    → should render the existing template (still visually driven
    by hardcoded JS), no 500 error.
  - This is an intermediate checkpoint before touching the
    template/JS.

#### Phase 4 — Check-in flow (D6 revised: stay on agenda + flash banner)

- [ ] **Task 4.0: Add CSRF validation to `CheckInAppointmentController`**
  - File: `src/Presentation/Clinic/Controller/Scheduling/Appointment/CheckInAppointmentController.php`
  - Action: The controller currently has **zero CSRF validation**
    (verified via grep). Before dispatching the command, add:
    ```php
    use Symfony\Component\Security\Csrf\CsrfToken;
    use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
    // ...
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    public function __invoke(string $appointmentId, Request $request): Response
    {
        $token = new CsrfToken('submit', (string) $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Jeton CSRF invalide, veuillez réessayer.');

            return $this->redirectToRoute('clinic_scheduling_agenda');
        }
        // ... rest of existing logic
    }
    ```
  - **Error handling rationale**: do NOT use
    `createAccessDeniedException` on invalid CSRF. 99% of the
    time, an invalid CSRF token means the token expired
    (session rotated) or the user double-submitted — NOT an
    attack. Throwing `AccessDeniedException` produces a blank
    403 page, which is unhelpful and terrifying. Instead:
    flash error + redirect to agenda. The user sees a
    friendly error message, can try again, and stays in
    context. Defense against actual CSRF attacks still works
    (the dispatch never runs).
  - **Token ID**: use `'submit'` which is already declared in
    `config/packages/csrf.yaml` as a `stateless_token_ids` entry
    — this token is **stateless and reusable** on the same
    session by default. Using `'submit'` avoids adding a new
    config entry and is the project-wide convention for form
    submission tokens.
  - Without this task, D13/Task 5.4's `<input type="hidden"
    name="_token">` is CSRF theatre (client-side only) and
    AC-Checkin-CSRF would pass vacuously.

- [ ] **Task 4.1: Update `CheckInAppointmentController` flash message
      and preserve agenda context**
  - File: `src/Presentation/Clinic/Controller/Scheduling/Appointment/CheckInAppointmentController.php`
  - **Flash rendering verified during Phase 0**: the project's
    flash system is **100% JS-driven via toast**. The base
    template (`templates/base.html.twig:21-25`) emits each flash
    as a hidden `<div data-flash-toast data-type="X" data-message="Y">`,
    and `assets/app.js:33-42` imports `kiveto/toast` and calls
    `toast[type](message)` with the plain string. **HTML in the
    flash message is impossible** — the message is echoed as an
    HTML attribute (auto-escaped) and consumed as
    `element.dataset.message` text by the JS toast.
  - **Conclusion**: use a plain text success flash. The
    "Voir la salle d'attente →" clickable link concept from
    Round 3 is not implementable via the current flash system.
    Dette UX accepted.
  - Action: On success, replace the existing flash:
    ```php
    $this->addFlash('success', 'Patient enregistré en salle d\'attente.');

    return $this->redirectToRoute('clinic_scheduling_agenda');
    ```
  - Keep the redirect on `clinic_scheduling_agenda`. **Do not**
    attempt to preserve `?date=` via referer parsing in this
    iteration — referer handling is scope creep and the typical
    workflow is to check-in a RDV of today, so the user is
    already on today's agenda. If the user checked in a past
    RDV (unusual flow), they return to today's agenda and can
    navigate back with the date arrows.
  - **No template modifications.** No new flash type, no new
    partial, no base template change. The fix is entirely
    contained in this controller.

- [ ] **Task 4.2: Verify any test asserting on the old redirect URL**
  - Action: Use the output from Task 0.1 — if a test asserted on
    `clinic_scheduling_agenda` without `?date=`, it still passes
    (new behaviour is identical — same route, same success
    path). If no test exists, skip. If a test exists and
    references a specific URL shape (unlikely), update it.

#### Phase 5 — Template: agenda/index.html.twig

- [ ] **Task 5.1: Fix the hardcoded `selected_date` in the aside
      include**
  - File: `templates/clinic/scheduling/agenda/index.html.twig` (line ~30)
  - Action: Replace
    ```twig
    selected_date: '2026-03-23',
    ```
    with
    ```twig
    selected_date: selectedDate|date('Y-m-d'),
    ```

- [ ] **Task 5.2: Wire the navigation buttons to real `<a href>`
      links**
  - Same file (lines ~43-59).
  - Action: Replace the `<button onclick="prevWeek()">`,
    `onclick="nextWeek()"`, `onclick="goToday()"` with
    `<a href="{{ prevLink }}">`, `<a href="{{ nextLink }}">`,
    `<a href="{{ todayLink }}">`. Keep the same CSS classes
    (`btn btn-secondary btn-xs`) so visual appearance is
    preserved.
  - Same for the view toggle (`Semaine` / `Jour` — replace with
    `<a href="{{ weekViewLink }}">` / `<a href="{{ dayViewLink }}">`).

- [ ] **Task 5.3: Inject `<script type="application/json" id="agenda-data">`**
  - Same file, new block inside `{% block javascripts %}` OR just
    before the closing `</div>` of the content block.
  - Action: Add the documenting comment + JSON script:
    ```twig
    {# Server → client payload for agenda.js. Expected shape:
     # {
     #   appointments: list<AppointmentItem>  // {id, clinicId, ownerId?, animalId?, practitionerUserId?, startsAtUtc ("Y-m-d H:i:s"), durationMinutes, status, reason?, notes?}
     #   veterinarians: list<ClinicVeterinarianItem>  // {userId, role, engagement}
     #   view: 'day' | 'week'
     #   selectedDate: string ("Y-m-d")
     #   currentUserId: string (UUID)
     #   clinicTimezone: string (IANA, e.g. "Europe/Paris")
     # }
     #}
    <script type="application/json" id="agenda-data">
      {{ {
        appointments: appointments,
        veterinarians: veterinarians,
        view: view,
        selectedDate: selectedDate|date('Y-m-d'),
        currentUserId: currentUserId,
        clinicTimezone: clinicTimezone,
      }|json_encode|raw }}
    </script>
    ```

- [ ] **Task 5.4: Add the single hidden check-in form**
  - Same file, near the existing popups.
  - Action:
    ```twig
    <form id="checkin-form" method="POST" action="" style="display:none;">
      <input type="hidden" name="_token" value="{{ csrf_token('submit') }}"/>
      <input type="hidden" name="arrivalMode" value="STANDARD"/>
      <input type="hidden" name="priority" value="0"/>
    </form>
    ```
  - **Token ID**: use `'submit'` (declared as a
    `stateless_token_ids` entry in `config/packages/csrf.yaml`).
    Stateless tokens are explicitly reusable on the same session,
    which is exactly what we need for the one-form-per-page
    pattern.
  - **Controller coordination**: Task 4.0 validates the token
    with the matching ID `'submit'`. The two MUST use the same
    ID.

- [ ] **Task 5.5: Hide or remove the "Nouveau RDV" button + FAB +
      edit/cancel buttons**
  - Same file.
  - Action: Remove the `onclick="openNewRdvGlobal()"` button (line
    ~72-75) and the `.fab-rdv` FAB button (line ~20-23). The
    create-RDV workflow is out of scope — don't leave non-
    functional buttons in the UI.
  - The cancel/edit buttons in the popup are now rendered
    dynamically by the JS (Task 7.x), which will omit them in this
    iteration.

- [ ] **Task 5.6: Remove the dead `{% if false %}` legacy block**
  - Same file (lines ~91-200+).
  - Action: The template has a `{% if false %}` block containing
    legacy sidebar markup. This is already dead code (never
    rendered). Delete it to simplify the file.
  - Rationale: cleanup is safe (no template can reference an
    `{% if false %}` block).

#### Phase 6 — Template: `_scheduling_aside.html.twig`

- [ ] **Task 6.0: Handle the shared nature of the aside partial**
  - File: `templates/clinic/scheduling/_scheduling_aside.html.twig`
    is shared by BOTH `agenda/index.html.twig` AND
    `planning/index.html.twig` (verified via grep). Adding a
    required `veterinarians` variable to the partial without
    touching the planning page would break the planning page
    with `Variable "veterinarians" does not exist`.
  - **Two-part action:**
    1. In the partial, wrap the vet-chips block in
       `{% if veterinarians is defined and veterinarians|length > 0 %}`
       — falls back to showing nothing (or a small empty
       message) if the variable is not passed.
    2. In `planning/index.html.twig`, locate the `include()`
       call for `_scheduling_aside.html.twig` and explicitly
       pass `veterinarians: []` + `currentUserId: null` so the
       guarded block is silently skipped. This preserves the
       planning page's current behaviour (which didn't render
       real vet data anyway) and avoids Twig runtime errors.
  - **Scope note**: this is NOT wiring the planning page to
    real data (that's its own follow-up spec). It's just
    preventing a regression on the planning page caused by
    changes to the shared partial.

- [ ] **Task 6.1: Replace hardcoded vet chips with a loop over
      `veterinarians`**
  - File: `templates/clinic/scheduling/_scheduling_aside.html.twig`
  - Action: Remove the 4 hardcoded `.vet-chip` divs (Rousseau /
    Martin / Dupont / Lambert, lines ~33-52). Replace with
    (inside the `{% if veterinarians is defined ... %}` guard
    from Task 6.0):
    ```twig
    {% for index, vet in veterinarians %}
      {% set _is_me = vet.userId == currentUserId %}
      {% set _label = _is_me ? 'Moi' : ('Praticien ' ~ (index + 1)) %}
      {% set _palette = ['#4338ca','#0891b2','#059669','#db2777','#7c3aed','#b45309','#0369a1','#9d174d'] %}
      {% set _color = _is_me ? '#0d9488' : _palette[index % _palette|length] %}
      <div class="vet-chip" data-vet-id="{{ vet.userId }}" onclick="toggleVet(this, '{{ vet.userId }}')">
        <div class="vet-dot" style="background:{{ _color }};"></div>
        <span>{{ _label }}</span>
        <svg class="vet-check" data-vet-check="{{ vet.userId }}" width="14" height="14" fill="none" viewBox="0 0 16 16">
          <path d="M3 8l3.5 3.5 6.5-7" stroke="{{ _color }}" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    {% endfor %}
    ```
  - **Label scheme**: `Praticien 1`, `Praticien 2`, `Praticien 3`,
    … `Praticien N` — numeric, unlimited, deterministic, scales
    indefinitely. No alphabet cap.
  - **Color scheme**: modulo palette of 8 colors — beyond 8 vets,
    colors repeat (not ideal but better than the JS
    `String.fromCharCode(65+i)` bug which produces `[`, `\`, `]`
    at i≥26).
  - Note: The aside template needs access to `veterinarians` and
    `currentUserId`. These variables are passed via the
    `include()` in `agenda/index.html.twig` line ~28-32 — update
    the include to pass them:
    ```twig
    {{ include('clinic/scheduling/_scheduling_aside.html.twig', {
      page: 'agenda',
      selected_date: selectedDate|date('Y-m-d'),
      week_highlight: true,
      veterinarians: veterinarians,
      currentUserId: currentUserId,
    }) }}
    ```

- [ ] **Task 6.2: Handle the empty veterinarians list edge case**
  - Same file.
  - Action: Wrap the `{% for %}` with a conditional. If empty,
    show a small message "Aucun vétérinaire actif dans cette
    clinique" (no-op toggle buttons, keep the label block).

#### Phase 7 — JS rewrite: `assets/js/pages/scheduling/agenda.js`

- [ ] **Task 7.1: Delete hardcoded data constants**
  - Lines to remove: `VETS`, `ANIMALS`, `RDVS` / `appointments`
    array literal (lines ~1-350).

- [ ] **Task 7.2: Parse `#agenda-data` JSON payload on init**
  - Action: Near the top of the file / in the `init` block:
    ```js
    const AGENDA_DATA = JSON.parse(
      document.getElementById('agenda-data').textContent
    );
    const appointments = AGENDA_DATA.appointments;
    const veterinarians = AGENDA_DATA.veterinarians;
    const currentUserId = AGENDA_DATA.currentUserId;
    const clinicTimezone = AGENDA_DATA.clinicTimezone;
    const view = AGENDA_DATA.view;
    const selectedDate = AGENDA_DATA.selectedDate;
    ```

- [ ] **Task 7.3: Add `parseUtcDateTime` + clinic-local helpers**
  - Action:
    ```js
    function parseUtcDateTime(mysqlUtcString) {
      // MySQL "Y-m-d H:i:s" UTC → JS Date parsed as UTC
      return new Date(mysqlUtcString.replace(' ', 'T') + 'Z');
    }

    function toClinicLocalParts(utcDate) {
      // Returns { dateStr: 'Y-m-d', timeStr: 'HH:MM' } in the clinic timezone
      const opts = {
        timeZone: clinicTimezone,
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hour12: false,
      };
      const parts = new Intl.DateTimeFormat('en-GB', opts).formatToParts(utcDate);
      const get = type => parts.find(p => p.type === type).value;
      return {
        dateStr: `${get('year')}-${get('month')}-${get('day')}`,
        timeStr: `${get('hour')}:${get('minute')}`,
      };
    }
    ```

- [ ] **Task 7.4: Build a veterinarian index → label/color map**
  - Action:
    ```js
    const VET_COLORS = ['#4338ca','#0891b2','#059669','#db2777','#7c3aed','#b45309','#0369a1','#9d174d'];
    const ME_COLOR = '#0d9488'; // teal-600, intentionally outside the --brand palette (see tech-spec D8)

    const vetById = {};
    veterinarians.forEach((v, i) => {
      const isMe = v.userId === currentUserId;
      vetById[v.userId] = {
        userId: v.userId,
        label: isMe ? 'Moi' : `Praticien ${i + 1}`, // 1, 2, 3, … (numeric, unlimited)
        color: isMe ? ME_COLOR : VET_COLORS[i % VET_COLORS.length],
        isMe,
      };
    });
    ```
  - **Labels are numeric** (`Praticien 1`, `Praticien 2`, …) —
    no alphabet cap, consistent with Task 6.1's Twig logic.

- [ ] **Task 7.5: Adapt `createRdvBlock` to use real data**
  - Action: In `createRdvBlock(a)`:
    - Replace `const vet = VETS[a.vet]` with
      `const vet = vetById[a.practitionerUserId]` (+ fallback for
      unknown practitioner: `{ label: '?', color: '#64748b' }`).
    - Replace `const animal = ANIMALS.find(...)` with a placeholder
      (create-appointment workflow is out of scope → no animal
      data server-side). Use `a.reason` as the display label
      (e.g. `${a.reason || 'Consultation'}` in place of animal
      name).
    - Compute `startMin` from `parseUtcDateTime(a.startsAtUtc)` +
      `toClinicLocalParts(...)`:
      ```js
      const localParts = toClinicLocalParts(parseUtcDateTime(a.startsAtUtc));
      const [hh, mm] = localParts.timeStr.split(':').map(Number);
      const startMin = hh * 60 + mm;
      ```
    - Replace `a.durationMin` with `a.durationMinutes` (server
      DTO field name).
    - Replace `a.status === 'cancelled'` with
      `a.status === 'CANCELLED'` (server enum uses uppercase).
    - Keep the `isCancelled` branch but it's now dead code in
      this iteration (filter removes cancelled upstream). Do not
      delete — the future toggle spec will re-enable it.

- [ ] **Task 7.6: Update the week/day filter to use veterinarians'
      userId instead of slug**
  - Action: `activeVets` becomes a `Set<string>` of user IDs.
    Initialize with all vetById keys. `toggleVet(el, userId)`
    toggles by user ID. Filter predicates in `renderWeek` /
    `renderDay`:
    ```js
    .filter(a => activeVets.has(a.practitionerUserId) && a.status !== 'CANCELLED')
    ```

- [ ] **Task 7.7: Remove create-RDV handlers**
  - Action: Delete functions: `openNewRdv`, `openNewRdvGlobal`,
    `searchAnimals`, `selectAnimal`, `selectMotif`, `setDuration`,
    `confirmNewRdv`, `editRdv`, `cancelRdv` (the RDV-edit one),
    `resetNewRdvForm`, `updateEndTime`. Remove any code that
    references `MOTIF_COLORS` for the create-RDV popup (keep the
    map if it's used for existing RDV coloring).
  - Also remove the drag-to-create event handlers (search for
    `addEventListener('mousedown'` on day columns).

- [ ] **Task 7.8: Wire the check-in button to the hidden form**
  - Action: Replace the body of `placerSalleAttente()`:
    ```js
    function placerSalleAttente() {
      const popup = document.getElementById('rdv-popup');
      const appointmentId = popup.dataset.rdvId;
      if (!appointmentId) return;

      const form = document.getElementById('checkin-form');
      // checkinUrlTemplate comes from the server payload with
      // '__ID__' as placeholder — see tech-spec §Solution D4.
      form.action = AGENDA_DATA.checkinUrlTemplate.replace('__ID__', appointmentId);
      form.submit();
    }
    ```
  - **Do NOT hardcode the URL** in JS. The server-provided
    `checkinUrlTemplate` field in the JSON payload guarantees
    route shape changes won't silently break the JS.
  - Note: `popup.dataset.rdvId` is set in `showRdvPopup` (line
    ~545). Confirm the ID is the full UUID, not an index.

- [ ] **Task 7.9: Remove navigation handlers + mini-calendar
      auto-advance**
  - Action: Delete `prevWeek`, `nextWeek`, `goToday`, `setView`
    — they're replaced by server-side `<a href>` links. Keep
    `renderMiniCal` but it only renders the mini-calendar for
    the currently-selected week (from server payload), with no
    click-to-navigate (clicks on mini-cal dates become `<a>`
    links too — alternative: turn the mini-cal into `<a>` links
    when rendering).
  - **Simplification**: If mini-cal interactivity is complex,
    leave the mini-cal as-is rendering the server's
    `selectedDate` week and accept that clicking on it is a
    no-op. Flag this as a minor UX debt in the follow-up notes.

- [ ] **Task 7.10: Ensure `renderWeek()` is called on
      `turbo:load`**
  - Action: The existing code has an `init()` function called
    by the app.js dispatcher. Verify it runs `renderWeek()` on
    every `turbo:load`. The `data-turbo-temporary` attributes
    on `#agenda-inner` and `#mini-cal-grid` ensure these
    containers are cleared on navigation — so re-rendering from
    scratch is mandatory.

- [ ] **Task 7.11: Verify file size**
  - Action: After the rewrite, `wc -l agenda.js` should be
    around **550 lines** (± 50). If > 700, review what's still
    dead code from the old implementation.

#### Phase 8 — Scheduling fixtures: `SchedulingStory`

- [ ] **Task 8.1: Add Foundry/Clock DI to `SchedulingStory`**
  - File: `fixtures/Context/Scheduling/Story/SchedulingStory.php`
  - Action: Add a constructor that injects:
    ```php
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly CommandBusInterface $commandBus,
    ) {}
    ```

- [ ] **Task 8.2: Implement `build()` with seed data**
  - Same file.
  - Action:
    1. Retrieve the existing users created by `ClinicMembershipDataStory`:
       - `vet@kiveto.local` (VETERINARY in Paris + Lyon — already existing)
       - `contractor@kiveto.local` (VETERINARY contractor in Paris — already existing, check `ClinicMembershipDataStory.php` line 86 for the exact clinic)
       - `admin.clinic@kiveto.local` (MANAGER in Lyon — NOT a veterinarian, do not schedule appointments for them)
       - `assistant@kiveto.local` (ASV in Paris — NOT a veterinarian, do not schedule)
    2. Retrieve an owner + animal via the factory APIs provided by
       `ClientDataStory` / `AnimalDataStory` (use
       `OwnerFactory::first()` or similar accessors — check the
       actual factory APIs during implementation).
    3. Compute the week anchor in clinic timezone (Paris):
       ```php
       $mondayParis = $this->clock->now()
           ->setTimezone(new \DateTimeZone('Europe/Paris'))
           ->modify('monday this week')
           ->setTime(0, 0, 0);
       ```
    4. **Hardcode an explicit list of users to seed appointments
       for** — do NOT iterate over
       `findVeterinariansForClinic` or any dynamic query:
       ```php
       // Hardcoded — DO NOT replace with a dynamic query,
       // otherwise vet2@kiveto.local (added in Task 8.5) will
       // accidentally receive appointments and break
       // AC-Empty-veterinarian.
       $usersToSeed = [
           'paris-vet'        => $vetUser,         // vet@kiveto.local in Paris
           'paris-contractor' => $contractorUser,  // contractor@kiveto.local in Paris
           'lyon-vet'         => $vetUser,         // same user, Lyon context
       ];
       ```
    5. Create at least **one appointment per day of the Paris
       week** (7 appointments minimum) via `ScheduleAppointment`
       commands. **Statuses used**: only `PLANNED` and
       `CANCELLED`. **`COMPLETED` is dropped from the seed**
       (see Task 0.3): the PLANNED → COMPLETED chain requires
       going through the waiting-room flow, which is too heavy
       for a simple seed. `NO_SHOW` is also out of scope (no
       `MarkNoShow` command exists). The "past RDV pale
       background" visual is driven by date comparison in the
       JS (`startsAtUtc < now`), not by status — so a PLANNED
       appointment on last Tuesday renders as pale past RDV
       naturally.
       - Day 1 (Mon): Paris + vet user, PLANNED, 09:00, 30min,
         reason `"Consultation"`
       - Day 2 (Tue): Paris + contractor user, PLANNED, 10:00,
         20min, reason `"Vaccin"`
       - Day 3 (Wed): Lyon + vet user, PLANNED, 14:00, 60min,
         reason `"Chirurgie Stérilisation"` (accent — guards
         AC-Special-chars)
       - Day 4 (Thu): Paris + vet user, PLANNED, 08:30, 30min,
         reason `"Bilan annuel"`
       - Day 5 (Fri): Lyon + vet user, PLANNED, 11:00, 15min,
         reason `"Suivi post-op J+7"`
       - Day 6 (Sat): Paris + vet user, **CANCELLED**, 10:00,
         30min, reason `"Vaccination d'Artagnan"` (apostrophe —
         also guards AC-Special-chars)
       - Day 7 (Sun): Lyon + vet user, PLANNED, 15:00, 20min,
         reason `"Contrôle poids"` (accent)
       - **Bonus** Thursday morning Paris RDV for the
         contractor at 11:00, 20min, PLANNED, reason
         `"Vaccin"` — so Thursday has 2 distinct practitioners
         with appointments.
    6. Dispatch pattern:
       - For PLANNED: `$commandBus->dispatch(new ScheduleAppointment(...))`
       - For CANCELLED: dispatch `ScheduleAppointment` then
         `CancelAppointment(appointmentId: ...)` (see Task 0.3
         for the exact signatures).
  - **Scope boundary reminder:** `SchedulingStory` creates
    APPOINTMENTS only. It does NOT create clinic memberships —
    that's `ClinicMembershipDataStory`'s job (Clinic BC). The
    3rd Paris veterinarian needed for AC-Empty-veterinarian
    (`vet2@kiveto.local`) is added in Task 8.5, and
    `SchedulingStory` **does NOT seed any appointments for
    vet2** — that's how vet2 becomes the "empty column".

- [ ] **Task 8.5: (NEW) Extend `ClinicMembershipDataStory` for
      AC-Empty-veterinarian**
  - File: `fixtures/Context/Clinic/Story/ClinicMembershipDataStory.php`
  - Action: Ensure Paris has **at least 3 ACTIVE VETERINARY
    memberships**, with only 2 of them having appointments in
    the current week (via `SchedulingStory`). The 3rd
    veterinarian appears as an empty column in the agenda grid
    → satisfies AC-Empty-veterinarian.
    - Currently Paris has: `vet@kiveto.local` (VET) and
      `contractor@kiveto.local` (VET). Only 2 vets. Not enough
      for AC-Empty-vet.
    - Add a new user e.g. `vet2@kiveto.local` with a Paris
      VETERINARY membership, but **do NOT create appointments
      for them in `SchedulingStory`** — they stay empty on
      purpose.
  - Note: this technically extends the seed beyond "scheduling
    wiring" but it's a tiny addition needed for AC-Empty-vet
    and respects the BC boundary (memberships stay in Clinic BC
    stories, appointments stay in Scheduling BC stories).

- [ ] **Task 8.3: Register `SchedulingStory` in `ClinicDataset`**
  - File: `fixtures/Dataset/ClinicDataset.php`
  - Action: Add `SchedulingStory::load();` after
    `AnimalDataStory::load();` — scheduling depends on animals
    and owners being seeded first.

- [ ] **Task 8.4: Update `fixtures/Context/Scheduling/AppointmentFactory.php`
      (if needed)**
  - Action: Probably no change — the factory is already
    sufficient. Verify during implementation.

#### Phase 9 — Integration test: scheduling seed smoke

- [ ] **Task 9.1: Write the seed smoke integration test**
  - File: `tests/Integration/Fixtures/SchedulingSeedSmokeTest.php` (new, adjust path to match existing test convention)
  - Action:
    ```php
    final class SchedulingSeedSmokeTest extends KernelTestCase
    {
        use Factories;

        public function testSeedProducesAtLeastOneAppointmentPerDayOfCurrentWeek(): void
        {
            // Freeze the clock to a known Monday
            self::getContainer()->set(
                ClockInterface::class,
                new FrozenClock(new \DateTimeImmutable('2026-04-13 10:00:00', new \DateTimeZone('Europe/Paris'))),
            );

            // Load the full dataset
            ClinicDataset::load();

            // Query the DB directly via DBAL
            $conn = self::getContainer()->get(Connection::class);
            $rows = $conn->fetchAllAssociative(
                "SELECT DATE(starts_at_utc) AS day, COUNT(*) AS cnt
                 FROM scheduling__appointments
                 WHERE starts_at_utc >= :from AND starts_at_utc <= :to
                 GROUP BY DATE(starts_at_utc)",
                ['from' => '2026-04-13 00:00:00', 'to' => '2026-04-19 23:59:59'],
            );

            self::assertSame(7, count($rows), 'Each day of the current week should have at least one appointment');
        }

        public function testSeedContainsAtLeastOneAppointmentWithSpecialCharsInReason(): void
        {
            self::getContainer()->set(ClockInterface::class, new FrozenClock(new \DateTimeImmutable('2026-04-13 10:00:00')));
            ClinicDataset::load();

            $conn = self::getContainer()->get(Connection::class);
            $count = $conn->fetchOne(
                "SELECT COUNT(*) FROM scheduling__appointments
                 WHERE reason LIKE '%d\\'Artagnan%' OR reason LIKE '%Contrôle%'",
            );

            self::assertGreaterThan(0, (int) $count);
        }
    }
    ```

#### Phase 10 — CI + manual validation

- [ ] **Task 10.1: Run `make assets`**
  - Action: `make assets` after the JS rewrite to recompile
    tailwind and the asset-map.

- [ ] **Task 10.2: Run `make ci`**
  - Action: Must be green. php-cs-fixer, phpcs, phpstan max,
    phpunit all pass. Fix anything before proceeding.

- [ ] **Task 10.3: Manual smoke test (AC-Smoke)**
  - Steps:
    1. `make reset-db` + `make load-fixtures`
    2. Open `clinic.kiveto.local` in browser
    3. Login as `vet@kiveto.local` (password: `vet`)
    4. Click a clinic in the select-clinic page (e.g. Paris)
    5. Navigate to `/scheduling/agenda`
    6. **Verify**: the week view shows the seeded appointments,
       with one column labeled "**Moi**"
    7. Click a future-planned appointment → popup opens
    8. Click "Salle d'attente" → flash banner appears, user
       stays on the agenda
    9. Click the "Voir la salle d'attente →" link → lands on the
       waiting-room page with the appointment in the queue
  - Verify all 7 ACs (smoke, cancelled-hidden, seven-days,
    empty-vet, special-chars, timezone, checkin-csrf).

- [ ] **Task 10.4: Commit + PR**
  - Action: NOT in this spec. The user will commit when
    satisfied.

- **AC-Smoke (end-to-end manual flow):** Given `vet@kiveto.local`
  is logged in with Paris as the default clinic, when they navigate
  to `/scheduling/agenda`, then they see the seeded non-cancelled
  appointments of the current week, with their own column labeled
  "**Moi**". When they click an appointment and press "Salle
  d'attente", the page reloads back on the agenda with a success
  toast "Patient enregistré en salle d'attente." (appearing
  briefly in the top-right via the `kiveto/toast` system).
  Navigating manually to `/scheduling/waiting-room` shows the
  newly created `WaitingRoomEntry` at the bottom of the queue.
  All this happens in the same browser session with no manual
  reload of the agenda page beyond the Turbo Drive redirect
  after submit.
- **AC-Checkin-CSRF:** Given the agenda page is loaded with a single
  CSRF token embedded in the unique `<form id="checkin-form">`, when
  the user clicks "Salle d'attente" on 3 different appointments in
  sequence (each navigation is a Turbo Drive reload back to the
  agenda), then each submit uses a valid CSRF token and all 3
  check-ins succeed without token errors. (Guards against accidentally
  making the token single-use.)
- **AC-Cancelled-hidden:** Given the seeds include at least one
  CANCELLED appointment on the current week, when the agenda page
  loads, then the cancelled appointment is NOT rendered in the grid
  (guard against accidental removal of the JS filter).
- **AC-Seven-days:** The seeded fixtures produce at least one
  appointment per day of the current week for the `vet@kiveto.local`
  user in both Paris and Lyon — no empty day.
- **AC-Empty-veterinarian:** Given Paris has 3 ACTIVE veterinarians
  (`vet@kiveto.local`, `contractor@kiveto.local`,
  `vet2@kiveto.local`), and the week's seeded appointments only
  involve `vet@kiveto.local` and `contractor@kiveto.local` (vet2
  has zero appointments), when the agenda page loads in week
  view for Paris, then **all 3 veterinarian columns** appear in
  the grid header — the **1 column belonging to vet2** is visible
  as empty but present, while the other 2 columns contain the
  seeded appointments. (This is the AC that proves
  `ListClinicVeterinarians` is correctly used as the source of
  truth for column headers, not
  `distinct(appointments.practitionerUserId)`.)
- **AC-Special-chars:** The page loads without JS errors when at least
  one seeded appointment has an apostrophe or accent in its motif/notes.
  (This AC is implicitly satisfied by the
  `<script type="application/json">` approach, but a manual check
  with DevTools console open is still required.)
- **AC-Timezone:** Given the server is configured in UTC and the
  clinic has `Europe/Paris` as its timezone, when the user loads the
  agenda on Sunday 23:45 Paris time, then the "this week" view shows
  Monday-Sunday of the current Paris week — NOT the UTC-rollover
  week. (Can be tested by injecting a fixed Clock + forcing a clinic
  timezone in an integration test on the controller or the query DTO.)

## Additional Context

### Dependencies

- Scheduling BC domain + application layers are fully implemented
  (confirmed in prior exploration).
- `AgendaController` already wires `QueryBusInterface` and
  `CurrentClinicContextInterface`.
- No DB migration required — `scheduling__appointments` and
  `clinic__clinic_memberships` tables already have the needed indexes.
- **Verified: no `cancel` route exists** in the scheduling routes
  (`debug:router` output). Cancel is out of scope.
- **Verified: `ClinicMembership` aggregate has no denormalised
  displayName / fullName / firstName / lastName field.** Placeholder
  labels are the only option for this iteration.
- **`Shared\Domain\Localization\TimeZone` VO exists** (at
  `src/Shared/Domain/Localization/TimeZone.php`) — validates
  against IANA timezones at write, exposes `toNative(): \DateTimeZone`.
  The `Clinic` aggregate **already uses it**
  (`Clinic.php:14,21,30,43,79,134,145,225`). This is the VO
  consumed in Task 3.2 to convert the `ClinicDto::$timeZone`
  string back to a native `\DateTimeZone` for date range
  computation. **Do NOT create a new VO in `Clinic\Domain\ValueObject\`**
  — the Shared one is the right source.
- **`MarkNoShow` command does NOT exist** in the Scheduling BC.
  Only `ScheduleAppointment`, `CompleteAppointment`,
  `CancelAppointment`, and the various waiting-room commands
  are wired. Task 8.2 seeds therefore use only PLANNED,
  COMPLETED, CANCELLED statuses. NO_SHOW is a separate follow-up
  feature requiring a new command.
- **`CheckInAppointmentController` has no CSRF validation today**
  (verified via grep). Task 4.0 adds it — this is a security
  hardening in scope for this spec (otherwise the client-side
  `_token` input would be theatre and AC-Checkin-CSRF would
  pass vacuously).
- **Scheduling BC has `MembershipEligibilityChecker` port** —
  the `listEligiblePractitionerUsersForClinic` method already
  exists and takes `allowedRoles` as a parameter. We do NOT
  reuse it (D3 rationale: layering — UI column listing belongs
  in Clinic BC, not Scheduling BC). But it's a cross-check
  that the Scheduling BC accepts veterinarians (and only them)
  as appointment practitioners, consistent with our
  `ListClinicVeterinarians` filter.

### Testing Strategy

- **Unit tests:**
  - `GetAgendaForClinicDateRange` DTO (named constructors
    `::forDay($date, $tz)`, `::forWeek($anchorDate, $tz)`, property
    access). Must include a test proving that computing
    `::forWeek(Sunday 23:45 Paris, Europe/Paris)` returns the range
    `Monday 00:00 Paris → Sunday 23:59 Paris` in UTC, not the UTC-
    rollover week.
  - `ListClinicVeterinarians` query DTO + handler (with mocked read
    repository)
  - Any helper that maps veterinarian index → "Praticien X" / "Moi"
    label
- **Integration tests:**
  - DBAL query: `GetAgendaForClinicDateRangeHandler` against a seeded
    DB over a 7-day range (via Foundry factories), with an explicit
    test case covering a clinic in a non-UTC timezone.
  - Doctrine read repository: `ListClinicVeterinarians` — three
    veterinarians in one clinic, one disabled, one in another clinic,
    one assistant (role ≠ VETERINARY), assert only the two active
    VETERINARIES in the target clinic are returned, in the expected
    order (`created_at_utc ASC, user_id ASC`).
  - Fixture smoke: load `ClinicDataset` with **`App\Tests\Shared\Time\FrozenClock`**
    (confirmed to already exist at `tests/Shared/Time/FrozenClock.php`
    — no new helper needed). Override the container binding with
    `self::getContainer()->set(ClockInterface::class, new FrozenClock(new \DateTimeImmutable('2026-04-15 10:00:00')))`
    before `ClinicDataset::load()` to guarantee zero flakiness on
    Sunday night runs. Then query `scheduling__appointments` and
    assert seed count + at least one row per day of the week anchored
    on the frozen Clock.
- **Presentation layer (Twig / JS):** no automated tests (excluded
  from 100% coverage), covered by the manual AC-Smoke flow.
- **Manual smoke test:** per AC-Smoke above.

### Notes

- **File size target (Round 3 revised):** `agenda.js` drops from
  ~1067 lines to **~550 lines** (not ~400 — earlier target was
  optimistic). Breakdown:
  - Removed: `VETS`/`ANIMALS`/`RDVS` constants (~350 lines), drag-
    to-create handlers (~100), create-RDV popup (~100), motif
    filter wiring (~30) → -580 lines
  - Kept/adapted: render week/day (~240), `createRdvBlock` (~80),
    RDV popup show/hide (~100), vet filter (dynamic) (~40), nav
    (now thin — links) (~15), toast/utils/mini-cal (~60) → ~535
    lines
  - Plus: `parseUtcDateTime()` helper, `toClinicLocalTime()` helper
    for timezone conversion (~15 lines new)
  - **Realistic post-rewrite: ~550 lines.**
- **Veterinarian color assignment** must be deterministic (stable
  across reloads) — derive from the veterinarian index in the
  server-provided list. Stability requires both: (a) a deterministic
  `ORDER BY` in the `ListClinicVeterinarians` SQL query, and
  (b) a static color palette indexed by position. The "Moi" column
  uses a **reserved dedicated color** distinct from brand primary
  (exact value picked in Step 2) regardless of index.
- **`_agenda.html.twig` partial (D12):** Verified dead code — not
  referenced by any template. Flagged for deletion in a follow-up
  chore; NOT deleted in this spec.
- **Non-functional filter UI (D10):** The **vet filter** in
  `_scheduling_aside.html.twig` becomes fully wired (client-side
  only — toggles show/hide a column, no server round-trip). The
  **motif filter** stays hardcoded (it's tied to the out-of-scope
  create-RDV popup). No filter is hidden.
- **JSON encoding safety — DECIDED:** use the
  `<script type="application/json" id="agenda-data">{{ data|json_encode|raw }}</script>`
  pattern + `JSON.parse(document.getElementById('agenda-data').textContent)`
  on the JS side. This is strictly safer than injecting into a
  `<script>` block with executable JS (no `JSON_HEX_*` flags needed,
  no XSS surface from user input, no `</script>` escape issue — the
  browser never parses the content as JavaScript). Pattern established
  as the project standard for any JS payload containing user-generated
  free-text.
- **"Moi" vs "Praticien N" label:** if the current user is not a
  veterinarian in the current clinic (e.g. admin, assistant, or
  manager viewing the agenda), no column gets the "Moi" label —
  all columns use the generic "Praticien 1/2/3/…" scheme. This
  is a natural consequence of `ListClinicVeterinarians` filtering
  on `role = VETERINARY` only. The numeric scheme scales to
  any clinic size (no alphabet cap like A/B/C/…/Z).
- **UX debt — "Salle d'attente" button label:** the existing
  popup button reads "Salle d'attente" which is ambiguous
  (does it send me to the waiting room, or does it register
  the patient there?). The true intent in the check-in flow
  is "register patient arrival". A future UX pass should
  rename this to "Enregistrer l'arrivée" or "Check-in" for
  clarity. **Not in scope** for this spec — copying the
  existing label keeps the visual unchanged during wiring.
- **UX debt — flash toast has no clickable link:** the
  project's flash system is 100% JS-toast-driven (see
  `templates/base.html.twig:21-25` + `assets/app.js:33-42`).
  Flash messages are plain-text strings, no HTML, no
  interactive elements. The Round 3 vision of a "Patient
  enregistré · Voir la salle d'attente →" banner with a
  clickable link is **not implementable** via the current
  flash system without introducing a custom sticky-banner
  component (scope creep). The controller therefore uses a
  plain "Patient enregistré en salle d'attente." flash, and
  the user navigates manually via the sidebar link if needed.
  A future spec can add a proper sticky action banner
  component to the UI kit if this UX becomes painful.
- **Query naming rationale:** `ListClinicVeterinarians` (not
  `ListClinicPractitioners`) locks the semantics in the name itself.
  If a future feature needs to list assistants for a different
  purpose (e.g. an assistants' planning board), it will be a
  separate query with its own name and semantics, not a parameter
  added to this one. This avoids the ambiguity trap where someone
  reopens the question "should this include assistants?" in 3
  months.
- **Rename decision (D1):** Verified that `GetAgendaForClinicDay` is
  called only by `AgendaController` and its integration test.
  → **Rename** to `GetAgendaForClinicDateRange` (no coexistence).
- **`CancelAppointment` command exists (D11):** The Scheduling BC
  already has `CancelAppointment` + handler implemented. Only the
  Presentation controller + CSRF form are missing. Cancel remains
  out of scope for this spec, but the follow-up spec is ~100 lines
  of wiring, not a full feature. The cancel button in the popup is
  visually disabled / hidden in this iteration.
- **`startsAtUtc` parsing from the JSON payload:** `AppointmentItem.startsAtUtc`
  is a MySQL datetime string (`"Y-m-d H:i:s"`, e.g. `"2026-04-15 09:30:00"`)
  representing UTC. The JS `new Date()` constructor is **not strict
  ISO 8601 compliant** with space separators and parses this as
  local time on some browsers. **Mandatory JS helper** in the
  rewrite:
  ```js
  function parseUtcDateTime(mysqlUtcString) {
    // Convert "2026-04-15 09:30:00" → "2026-04-15T09:30:00Z"
    // so Date() parses it unambiguously as UTC.
    return new Date(mysqlUtcString.replace(' ', 'T') + 'Z');
  }
  ```
  Then to display in the clinic timezone, use
  `date.toLocaleString('fr-FR', { timeZone: clinicTimezone, ... })`
  or compute offsets manually. The clinic timezone string is in
  `window.AGENDA_DATA.clinicTimezone` (added to the payload via D4).
- **Perf smoke test (Winston Round 3):** With ~200 appointments in
  a week view, re-rendering everything on each Turbo Drive navigation
  should stay under **500ms** (soft target). If it blows up during
  implementation, measure first (`performance.now()` around
  `renderWeek()`) before optimizing. Not blocking for this spec.
- **Step 3 prerequisites (verified during spec writing):**
  1. **`FrozenClock`** — `tests/Shared/Time/FrozenClock.php`
     EXISTS with the expected signature. No new helper needed.
  2. **CSRF token reusability** — `config/packages/csrf.yaml`
     declares `submit`, `authenticate`, `logout` as
     `stateless_token_ids`. The `submit` token is explicitly
     reusable on the same session — Task 4.0 and Task 5.4
     both use this token ID.
  3. **`Shared\Domain\Localization\TimeZone` VO** — EXISTS at
     `src/Shared/Domain/Localization/TimeZone.php`. `fromString()`
     validates against `\DateTimeZone::listIdentifiers()`,
     `toNative()` returns `\DateTimeZone`. The `Clinic` aggregate
     already uses this VO — the DB can only contain valid
     timezone strings. Used by Task 3.2.
  4. **`MarkNoShow` command** — DOES NOT EXIST in the Scheduling
     BC. Only `ScheduleAppointment`, `CompleteAppointment`,
     `CancelAppointment` exist. NO_SHOW is therefore
     out-of-scope for fixture seeds (Task 8.2 uses only
     PLANNED / COMPLETED / CANCELLED).
  5. **`created_at_utc` column on `clinic__clinic_memberships`**
     — EXISTS (verified in `ClinicMembershipEntity.php:47`).
     Used by Task 2.4's SQL `ORDER BY`.
- **Phase 0 in-spec verification tasks** (still needed at dev time):
  1. `grep -rn "clinic_scheduling_agenda" tests/` — identify
     any functional test asserting on the redirect URL of
     check-in or other controllers. Update if needed.
  2. `grep -rn "_scheduling_aside" templates/` — confirm the
     shared partial is also used by the planning page (Task
     6.0 fix).
