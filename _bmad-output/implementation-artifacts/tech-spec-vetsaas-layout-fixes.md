---
title: 'VetSaaS layout fixes — rpanel, agenda sidebar, planning page, nav wiring'
type: 'bugfix'
created: '2026-04-03'
status: 'done'
baseline_commit: 'd570542'
context: ['_bmad-output/project-context.md']
---

# VetSaaS layout fixes — rpanel, agenda sidebar, planning page, nav wiring

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Three layout bugs remain after VetSaaS migration: (1) dashboard rpanel spans full viewport height instead of sitting below topbar, hiding the language selector; (2) agenda page shows the standard nav sidebar when it should only show its own mini-cal/vet-filter panel; (3) planning page returns 500 because its template is missing. Additionally, sidebar nav items for Planning and Salle d'attente are dead links.

**Approach:** Restructure `clinic/base.html.twig` to wrap scroll area + rpanel in a `main-wrap` flex row below topbar; add a `show_sidebar` Twig block so scheduling pages can hide the standard sidebar; create the planning template from the `vetos-planning.html` mockup; wire the two remaining scheduling nav links.

## Boundaries & Constraints

**Always:**
- Use existing route names — do not rename routes
- Planning template extends `clinic/base.html.twig`
- All code comments in English; user-facing strings in French
- No npm/webpack — AssetMapper only

**Ask First:**
- Any structural change to root `base.html.twig`

**Never:**
- Modify PHP domain layer
- Change existing route URLs
- Add new commands or domain services

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Dashboard rpanel desktop | GET /dashboard, ≥1025px | rpanel (310px) starts below topbar; topbar spans full width | — |
| Dashboard rpanel tablet | GET /dashboard, ≤1024px | rpanel hidden; toggle button visible in topbar | — |
| Agenda page | GET /scheduling/dashboard | No standard nav sidebar; only mini-cal + vet-filter panel visible | — |
| Planning page | GET /scheduling/planning | Planning grid renders; no standard nav sidebar; no 500 | — |
| Sidebar Planning link | Click "Planning" | Navigates to /scheduling/planning | — |
| Sidebar Salle d'attente | Click "Salle d'attente" | Navigates to /scheduling/waiting-room | — |

</frozen-after-approval>

## Code Map

- `templates/clinic/base.html.twig` -- App shell: add `app-main-wrap` wrapper, add `show_sidebar` block
- `assets/styles/components/layout.css` -- Add `.app-main-wrap` flex style; adjust `.app-rpanel` responsive
- `templates/clinic/dashboard.html.twig` -- Migrate rpanel to use `app-rpanel` class from layout.css
- `templates/clinic/scheduling/dashboard.html.twig` -- Override `show_sidebar` to false
- `templates/clinic/scheduling/planning.html.twig` -- CREATE from vetos-planning.html mockup
- `templates/components/layout/sidebar.html.twig` -- Wire Planning + Salle d'attente routes

## Tasks & Acceptance

**Execution:**

- [ ] `templates/clinic/base.html.twig` -- RESTRUCTURE: inside `app-content`, after topbar, wrap `app-scroll` + `rpanel` block inside `<div class="app-main-wrap">`; add `{% block show_sidebar %}true{% endblock %}` conditional around sidebar include
- [ ] `assets/styles/components/layout.css` -- ADD `.app-main-wrap { display: flex; flex: 1; overflow: hidden; min-height: 0; }` and responsive rules for `.app-rpanel` tablet drawer
- [ ] `templates/clinic/dashboard.html.twig` -- UPDATE rpanel block to use `.app-rpanel` from layout.css instead of inline `.rpanel`; remove duplicated rpanel CSS
- [ ] `templates/clinic/scheduling/dashboard.html.twig` -- ADD `{% block show_sidebar %}false{% endblock %}` to hide standard nav sidebar
- [ ] `templates/clinic/scheduling/planning.html.twig` -- CREATE: vet-row horizontal grid from vetos-planning.html, override `show_sidebar` to false, include mini-cal + vet filters in own panel
- [ ] `templates/components/layout/sidebar.html.twig` -- WIRE `clinic_scheduling_planning` and `clinic_scheduling_waiting_room` route names on Planning and Salle d'attente items

**Acceptance Criteria:**
- Given user visits /dashboard, when page loads, then rpanel starts below topbar and topbar spans full content width
- Given user visits /scheduling/dashboard, then standard nav sidebar is hidden and agenda-specific mini-cal panel is visible
- Given user visits /scheduling/planning, then page renders without error and shows planning grid
- Given user clicks Planning or Salle d'attente in sidebar, then navigation goes to a real page

## Design Notes

**`main-wrap` pattern:** The VetSaaS mockup nests rpanel inside `.content` as a flex sibling to the scroll area, both below topbar:
```
.app-content (flex: column)
├── topbar (52px, flex-shrink: 0)
└── .app-main-wrap (flex: 1, display: flex)
    ├── .app-scroll (flex: 1, overflow-y: auto)
    └── {% block rpanel %} (.app-rpanel, 310px)
```

**Sidebar hide:** A Twig block `show_sidebar` defaults to `true`. Agenda and Planning override to `false`. When hidden, `.app-content` naturally expands since the sidebar flex item is absent.

## Verification

**Commands:**
- `php bin/console cache:clear` -- expected: no errors
- `php bin/console lint:twig templates/` -- expected: 0 errors

**Manual checks:**
- /dashboard — rpanel below topbar, language selector visible
- /scheduling/dashboard — no nav sidebar, agenda panel visible
- /scheduling/planning — renders without 500
- Sidebar Planning + Salle d'attente links work
