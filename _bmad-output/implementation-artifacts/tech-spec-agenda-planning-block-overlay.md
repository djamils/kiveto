---
title: 'Agenda — Planning Block Overlay'
slug: 'agenda-planning-block-overlay'
created: '2026-04-19'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['PHP 8.5', 'Symfony 7.4', 'Twig', 'Turbo 7.3', 'Stimulus', 'Tailwind CSS v4', 'Vanilla JS (agenda.js)']
files_to_modify:
  - 'src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php'
  - 'templates/clinic/scheduling/agenda/index.html.twig'
  - 'assets/js/pages/scheduling/agenda.js'
code_patterns:
  - 'QueryBus::ask() for read queries (no new domain, no migration)'
  - 'JSON payload via <script type="application/json" id="agenda-data"> inside turbo-frame'
  - 'position:absolute overlays in day-col, coordinate system: topPx = ((startMin - HOUR_START*60)/60)*SLOT_H'
  - 'freeSlotsActive toggle pattern (bool state + localStorage + is-active CSS class)'
  - 'activeVets Set filter applied per-vet in render loop'
test_patterns:
  - 'Presentation layer excluded from 100% coverage requirement'
  - 'Handler already tested: tests/Unit/Context/Scheduling/Application/Query/ListPlanningBlocksForClinicDateRange/'
  - 'No new unit tests required for this feature (pure JS + controller wire-up)'
---

# Tech-Spec: Agenda — Planning Block Overlay

**Created:** 2026-04-19

## Overview

### Problem Statement

The agenda grid displays appointments without contextual activity information for each practitioner. Users scheduling a new appointment cannot see at a glance whether a vet is in a consultation block, surgery, or on leave — leading to risk of booking incompatible appointments (blocked by the PlanningBlock hard constraints) and unnecessary UX friction.

### Solution

Overlay planning blocks as semi-transparent background bands on the agenda grid columns (day and week views), using activity-type colors, with vet+activity labels. A toggle button in the toolbar controls visibility; state persists in localStorage. Zero new backend domain — reuses the existing `ListPlanningBlocksForClinicDateRange` query that already expands recurring occurrences via `RecurrenceExpander`.

### Scope

**In Scope:**
- `AgendaController`: add `ListPlanningBlocksForClinicDateRange` call, pass serialized blocks to template
- `agenda.html.twig`: add `planningBlocks` to JSON payload + toggle button in toolbar
- `agenda.js`: `PLANNING_BLOCK_TYPES` constant, `planningBlocksVisible` state, `togglePlanningOverlay()`, `renderPlanningOverlays(col, iso)` injected before hour slots in day-col build loop
- Day view + Week view (month view not implemented in product)
- Per-vet filtering: only blocks for vets in `activeVets` are rendered; rechecked on vet filter toggle
- localStorage persistence: key `'kiveto.agenda.planning-overlay'`, default `false`

**Out of Scope:**
- Month view (not implemented)
- Click/interaction on overlay blocks (informational only in v1)
- Hover tooltip / enriched popover on overlays (v1)
- Appearance animation on toggle (simple show/hide)
- Server-side user preference storage
- Editing a planning block from the agenda

---

## Context for Development

### Codebase Patterns

**Coordinate system** (agenda.js lines 22-24):
```js
const HOUR_START = 7;   // grid starts at 07:00
const HOUR_END   = 20;  // grid ends at 20:00
const SLOT_H     = 80;  // px per hour
```
Position formula (same as appointments and free-slot blocks):
```js
topPx    = ((startMin - HOUR_START * 60) / 60) * SLOT_H + 1
heightPx = ((endMin   - startMin)        / 60) * SLOT_H - 2
```

**Free-slot toggle pattern** (agenda.js lines 32, 829-833) — exact model to follow:
```js
let freeSlotsActive = false;         // module-level bool
// toggle:
freeSlotsActive = !freeSlotsActive;
btn.classList.toggle('is-active', freeSlotsActive);
renderWeek();                         // full re-render — handles both day and week views
```

**`renderWeek()` is the only render dispatcher** (confirmed in codebase — there is no `renderDay()`). It reads the module-level `view` variable and renders either 1 column (day view) or 7 columns (week view). Always call `renderWeek()` to trigger a full re-render.

**Overlay injection order**: planning block divs must be **prepended** (first children) of `day-col` so they sit behind hour slots and appointments in the paint order (all siblings share the same stacking context with `z-index: auto`, stacked in DOM order). `pointer-events: none` ensures clicks pass through to slots and appointments beneath.

**Z-index stacking contract** in `day-col` (`position: relative`):
- Planning overlays: first children, no explicit `z-index` → paint layer 0 (behind everything)
- Hour slots + appointments: later siblings, no explicit `z-index` → paint layer 1+ (on top)
- Free-slot overlays: appended last by `renderFreeSlots()` → paint layer N (top)
- Drag preview: explicit `z-index: 5`
- No change to any existing element's `z-index` required.

**`activeVets` filter**: `Set<string>` of active vet userIds. Apply `activeVets.has(block.vet)` before rendering each block, mirroring the appointments filter (agenda.js line 347-350).

**PLANNING_BLOCK_TYPES**: a new constant created in `agenda.js`, duplicating only the `color`/`bg`/`label` data from `ACTIVITY_TYPES` in `planning.js` (lines 17-26). No import — avoids cross-module coupling. **Important**: when a new `PlanningBlockType` enum value is added to `PlanningBlockType.php`, this constant must be updated manually. Add a comment: `// Sync with ACTIVITY_TYPES in planning.js and PlanningBlockType.php when adding new types`.

**Date range for planning blocks query — timezone handling**:
- `$selectedDate` is already constructed in `$clinicTz` (line 52 of `AgendaController`). `modify('monday this week')` inherits that timezone automatically — no extra conversion needed.
- Day view: `fromDate = toDate = $selectedDate->format('Y-m-d')`
- Week view: `$weekStart = $selectedDate->modify('monday this week')->setTime(0,0,0)`, then `fromDate = $weekStart->format('Y-m-d')`, `toDate = $weekStart->modify('+6 days')->format('Y-m-d')`. This mirrors `GetAgendaForClinicDateRange::forWeek()` which uses the same `modify('monday this week')` in the clinic timezone.

**JSON serialization** — `PlanningBlockView` PHP property names differ from the JS payload keys. The `array_map` in Task 1 performs the explicit remapping:
```
PlanningBlockView::$staffUserId → 'vet'
PlanningBlockView::$startTime   → 'start'
PlanningBlockView::$endTime     → 'end'
PlanningBlockView::$date        → 'date'
PlanningBlockView::$type        → 'type'
```

**Same-week calendar navigation bypass**: in `agenda.js`, clicking a date within the current week triggers `patchHeaderNav()` without calling `renderWeek()` (line ~1326). This means planning overlays for the newly-selected day are **not re-rendered** in week view when navigating within the same week. Since week-view overlays span all 7 columns simultaneously and are not day-specific, this is acceptable — all overlays remain visible. However, if the vet filter changes between same-week clicks, the overlays may be stale. The implementer should note this limitation and not attempt to fix it in this spec (it would require refactoring the same-week optimization path, out of scope).

### Files to Reference

| File | Purpose |
|------|---------|
| `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php` | Add `ListPlanningBlocksForClinicDateRange` call + `planningBlocks` render var |
| `src/Context/Scheduling/Application/Query/ListPlanningBlocksForClinicDateRange/ListPlanningBlocksForClinicDateRange.php` | Query class — params: `clinicId`, `fromDate` (Y-m-d), `toDate` (Y-m-d) |
| `src/Context/Scheduling/Application/Query/ListPlanningBlocksForClinicDateRange/PlanningBlockView.php` | DTO fields: `staffUserId`, `date`, `startTime`, `endTime`, `type` |
| `templates/clinic/scheduling/agenda/index.html.twig` | JSON payload (lines 107-117), toolbar (lines 36-81) |
| `assets/js/pages/scheduling/agenda.js` | `HOUR_START/END/SLOT_H` (lines 22-24), `freeSlotsActive` pattern (lines 32, 829-833), `renderFreeSlots` (lines 845-921), day-col build loop (lines 306-392), `renderWeek()` (line 247) |
| `assets/js/pages/scheduling/planning.js` | `ACTIVITY_TYPES` color/bg source (lines 17-26) — reference only, do not import |

### Technical Decisions

- **No new query/handler**: reuse `ListPlanningBlocksForClinicDateRange` — it already expands recurring blocks via `RecurrenceExpander`.
- **Prepend (not append) overlays**: planning block divs are inserted as first children of `day-col` so they are behind all later-painted elements (slots, appointments, free-slots, now-line) without explicit z-index changes. `pointer-events: none` ensures clicks pass through.
- **Duplicate ACTIVITY_TYPES color data**: a small `PLANNING_BLOCK_TYPES` map in `agenda.js` avoids coupling page modules. Only `color`, `bg`, `label` are needed. Must be kept in sync manually with `PlanningBlockType.php` — add a sync comment.
- **Always call `renderWeek()` on toggle**: confirmed — this is the only render dispatcher and it handles both day and week views based on the `view` module variable.
- **Client-side vet filtering**: `activeVets.has(block.vet)` applied during render. Note `vetById` is keyed by lowercase UUID; `PlanningBlockView.staffUserId` is serialized via the same PHP source → case will match.

---

## Implementation Plan

### Tasks

- [ ] **Task 1: AgendaController — inject planning blocks into template**
  - File: `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php`
  - Add imports:
    ```php
    use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\ListPlanningBlocksForClinicDateRange;
    use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\PlanningBlockView;
    ```
  - Compute date range **after** `$selectedDate` is resolved (it already carries `$clinicTz` from line 52 — no extra conversion needed):
    ```php
    if ('day' === $viewParam) {
        $fromDate = $selectedDate->format('Y-m-d');
        $toDate   = $fromDate;
    } else {
        $weekStart = $selectedDate->modify('monday this week')->setTime(0, 0, 0);
        $fromDate  = $weekStart->format('Y-m-d');
        $toDate    = $weekStart->modify('+6 days')->format('Y-m-d');
    }
    ```
  - Add query call **after** the `$appointments` call:
    ```php
    $planningBlocks = $this->queryBus->ask(new ListPlanningBlocksForClinicDateRange(
        clinicId: $currentClinicId->toString(),
        fromDate: $fromDate,
        toDate:   $toDate,
    ));
    \assert(\is_array($planningBlocks));
    // Note: PlanningBlockView property names differ from the JS payload keys.
    // staffUserId→vet, startTime→start, endTime→end to align with planning.js convention.
    $planningBlocksJs = array_map(static function (mixed $b): array {
        \assert($b instanceof PlanningBlockView);
        return [
            'vet'   => $b->staffUserId,
            'date'  => $b->date,
            'start' => $b->startTime,
            'end'   => $b->endTime,
            'type'  => $b->type,
        ];
    }, $planningBlocks);
    ```
  - Add `'planningBlocks' => $planningBlocksJs` to the `render()` call's array.

- [ ] **Task 2: agenda.html.twig — payload + toggle button**
  - File: `templates/clinic/scheduling/agenda/index.html.twig`
  - In the `agenda-data` JSON payload (lines 107-117), add `planningBlocks: planningBlocks` key to the object:
    ```twig
    {{ {
      appointments: appointments,
      veterinarians: veterinarians,
      view: view,
      selectedDate: selectedDate|date('Y-m-d', clinicTimezone),
      currentUserId: currentUserId,
      clinicTimezone: clinicTimezone,
      clientProfileUrlTemplate: clientProfileUrlTemplate,
      planningBlocks: planningBlocks,
    }|json_encode|raw }}
    ```
  - In the toolbar (`scheduling-page-header`, near the free-slots button around line 66), add:
    ```twig
    <button id="planning-overlay-btn"
            onclick="togglePlanningOverlay()"
            class="data-filter-btn data-filter-btn--sm"
            aria-label="Afficher les blocs de planning">
      <svg ...><!-- layers or calendar-check icon from Lucide, consistent with toolbar --></svg>
      Blocs planning
    </button>
    ```

- [ ] **Task 3: agenda.js — state, constants, toggle, render**
  - File: `assets/js/pages/scheduling/agenda.js`

  **3a. Add `PLANNING_BLOCK_TYPES` constant** near the top of the file, after existing module-level constants:
  ```js
  // Sync with ACTIVITY_TYPES in planning.js and PlanningBlockType.php when adding new types.
  const PLANNING_BLOCK_TYPES = {
    consultation: { color: '#4338ca', bg: '#eef2ff', label: 'Consultation' },
    chirurgie:    { color: '#b45309', bg: '#fef3c7', label: 'Chirurgie'    },
    garde:        { color: '#7c3aed', bg: '#f5f3ff', label: 'Garde'        },
    bilan:        { color: '#059669', bg: '#ecfdf5', label: 'Bilans/Labo'  },
    formation:    { color: '#0891b2', bg: '#ecfeff', label: 'Formation'    },
    conge:        { color: '#dc2626', bg: '#fef2f2', label: 'Congé'        },
    admin:        { color: '#64748b', bg: '#f8fafc', label: 'Admin'        },
    urgence:      { color: '#ea580c', bg: '#fff7ed', label: 'Urgences'     },
  };
  const PLANNING_OVERLAY_LS_KEY = 'kiveto.agenda.planning-overlay';
  ```

  **3b. Add `planningBlocksVisible` state** near `freeSlotsActive` (line 32):
  ```js
  let planningBlocksVisible = false;
  ```

  **3c. Add `togglePlanningOverlay()` function** near `toggleFreeSlots()` (around line 829):
  ```js
  function togglePlanningOverlay() {
    planningBlocksVisible = !planningBlocksVisible;
    localStorage.setItem(PLANNING_OVERLAY_LS_KEY, planningBlocksVisible ? '1' : '0');
    const btn = document.getElementById('planning-overlay-btn');
    if (btn) btn.classList.toggle('is-active', planningBlocksVisible);
    renderWeek(); // renderWeek() handles both day and week views — no renderDay() exists
  }
  ```

  **3d. Read localStorage in `bootstrapFromPayload()`** (around line 1200+, where agenda-data is parsed):
  ```js
  planningBlocksVisible = localStorage.getItem(PLANNING_OVERLAY_LS_KEY) === '1';
  const overlayBtn = document.getElementById('planning-overlay-btn');
  if (overlayBtn) overlayBtn.classList.toggle('is-active', planningBlocksVisible);
  ```

  **3e. Add `renderPlanningOverlays(col, iso)` function** near `renderFreeSlots` (around line 845):
  ```js
  function renderPlanningOverlays(col, iso) {
    if (!planningBlocksVisible) return;
    const blocks = (AGENDA_DATA.planningBlocks || []).filter(
      (b) => b.date === iso && activeVets.has(b.vet),
    );
    blocks.forEach((b) => {
      const [sh, sm] = b.start.split(':').map(Number);
      const [eh, em] = b.end.split(':').map(Number);
      // Clamp to visible grid range
      const startMin   = Math.max(sh * 60 + sm, HOUR_START * 60);
      const endMin     = Math.min(eh * 60 + em, HOUR_END   * 60);
      if (endMin <= startMin) return; // block entirely outside visible range
      const topPx    = ((startMin - HOUR_START * 60) / 60) * SLOT_H + 1;
      const heightPx = ((endMin - startMin)          / 60) * SLOT_H - 2;
      if (heightPx < 6) return;

      const type    = PLANNING_BLOCK_TYPES[b.type] ?? PLANNING_BLOCK_TYPES.consultation;
      const vet     = vetById[b.vet];
      const vetName = vet ? (vet.displayName ?? vet.name ?? '') : '';
      const label   = vetName ? `${vetName} — ${type.label}` : type.label;

      const el = document.createElement('div');
      el.style.cssText = [
        'position:absolute',
        `top:${topPx}px`,
        `height:${heightPx}px`,
        'left:0',
        'right:0',
        `background:${type.bg}`,
        `border-left:3px solid ${type.color}`,
        'opacity:0.85',
        'pointer-events:none',
        'overflow:hidden',
      ].join(';');
      el.innerHTML = `<span style="display:block;padding:2px 5px;font-size:var(--text-xs);font-weight:var(--weight-medium);color:${type.color};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${label}</span>`;

      col.prepend(el); // prepend = behind all later-appended children (slots, appointments)
    });
  }
  ```

  **3f. Call `renderPlanningOverlays` in the day-col build loop** — insert as the **first** operation in the per-column loop, before the hour-slots loop (line ~320 inside `renderWeek()`):
  ```js
  renderPlanningOverlays(col, iso); // must be first — prepend order determines stacking
  ```

  **3g. Expose `togglePlanningOverlay` on `window`** (in the `window.*` assignments block):
  ```js
  window.togglePlanningOverlay = togglePlanningOverlay;
  ```

  **3h. Clean up in `cleanup()`**: add `delete window.togglePlanningOverlay;`

### Acceptance Criteria

- [ ] **AC1**: Given toggle is ON and a vet is active with a consultation block 08:00–12:00 on a given day, when the agenda renders that day's column, then a semi-transparent `#eef2ff` band with a `#4338ca` left border and label "[VetName] — Consultation" is visible from 08:00 to 12:00, behind all appointments.

- [ ] **AC2**: Given toggle is ON and 3 active vets each have different activity types on the same day, when the agenda renders that column, then 3 overlapping bands are visible (each with their own activity color and vet+activity label) and appointments remain fully readable in front.

- [ ] **AC3**: Given toggle is ON and a vet has a "Congé" block covering the full day, when the agenda renders, then a `#fef2f2` / `#dc2626` band covers the full column height with label "[VetName] — Congé".

- [ ] **AC4**: Given toggle is ON and a vet has a weekly recurring planning block, when the user navigates to the next week on the agenda, then the overlay block appears on the same weekday of the new week (RecurrenceExpander handles expansion server-side).

- [ ] **AC5**: Given toggle is OFF (default on first visit), when the agenda renders, then no planning block bands are visible — the grid looks exactly as it did before this feature.

- [ ] **AC6**: Given toggle is ON and a vet is unchecked in the aside filter, when the grid re-renders, then that vet's overlay bands disappear immediately along with their appointments.

- [ ] **AC7**: Given toggle is ON in week view, when the user switches to day view, then overlay bands are visible for the selected day's column.

- [ ] **AC8**: Given the user activates the toggle and reloads the page, when the agenda loads, then the toggle is still ON (localStorage persisted) and overlays are rendered on first paint.

- [ ] **AC9**: Given a planning block that starts before `HOUR_START` (e.g. 06:00–09:00), when rendered, then only the portion within `HOUR_START`–`HOUR_END` is shown (clamped). A block entirely outside the visible range (e.g. 05:00–06:30) renders nothing.

---

## Additional Context

### Dependencies

- `ListPlanningBlocksForClinicDateRange` query + handler — already implemented and tested in `tests/Unit/Context/Scheduling/Application/Query/ListPlanningBlocksForClinicDateRange/`
- `RecurrenceExpander` domain service — already wired into the handler
- No new migrations, no new domain objects, no new Symfony services

### Testing Strategy

- **No new unit tests required**: AgendaController is in the Presentation layer (excluded from 100% coverage requirement per CLAUDE.md). The handler that expands recurring blocks already has its own test suite.
- **Manual testing checklist** (fixture setup required for AC2, AC3, AC4):
  1. Navigate to agenda — confirm toggle is OFF by default ✓
  2. Create planning blocks via `/scheduling/planning` covering at least: one partial-day consultation, one full-day Congé, one weekly-recurring block across multiple vets ✓
  3. Return to agenda, toggle ON — verify overlay positions match block start/end times ✓
  4. Verify overlays in both day view and week view ✓
  5. Uncheck one vet in the aside → confirm their overlays disappear ✓
  6. Reload page → confirm toggle state preserved ✓
  7. Click on an appointment over an overlay → confirm appointment popup opens (pointer-events: none working) ✓
  8. Navigate prev/next week → confirm recurring blocks appear on correct weekday ✓
  9. Create a block starting at 05:00 (before HOUR_START=7) → confirm only 07:00–block_end portion renders ✓
  10. `make ci` green ✓

### Notes

- **Known limitation — same-week navigation**: clicking a different day within the same week uses an optimisation path (`patchHeaderNav()`, agenda.js ~line 1326) that does not call `renderWeek()`. Planning overlays are therefore not re-applied on same-week day switches in week view. This is acceptable since all 7 columns remain visible and overlays for each day are already rendered. Only a vet filter change within a same-week navigation would leave stale overlays; this edge case is out of scope.
- **`vetById` key case**: both `vetById` and `PlanningBlockView.staffUserId` originate from the same `ClinicVeterinarianItem.userId` source — verify lowercase consistency during implementation.
- **Future v2**: overlay click → navigate to `/scheduling/planning?date=X&view=day` once UX pattern is validated on terrain.
