---
title: 'Extract inline JS to external modules with Turbo lifecycle'
type: 'refactor'
created: '2026-04-04'
status: 'done'
baseline_commit: 'a98677d'
context: ['_bmad-output/project-context.md']
---

# Extract inline JS to external modules with Turbo lifecycle

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Every clinic page embeds 400-1000 lines of inline JS in `{% block javascripts %}`. Turbo Drive replaces the `<body>` on navigation but does not re-execute inline `<script>` tags, resulting in broken pages after link clicks. Additionally, `app.js` inits UI on `DOMContentLoaded` instead of `turbo:load`, so the VetSaaS UI kit never re-initializes after navigation.

**Approach:** Extract each page's inline JS into an ES module under `assets/js/pages/`, register them in `importmap.php`, and load them from templates via `<script type="module">`. Each module exports `init()` and `cleanup()` functions. A shared bootstrap in `app.js` listens to `turbo:load` and `turbo:before-cache` to manage page lifecycle. Remove `data-turbo-track="reload"` from `<style>` tags since JS modules handle the lifecycle properly.

## Boundaries & Constraints

**Always:**
- Use AssetMapper importmap — no webpack/npm
- Each page module is self-contained: `init()` sets up everything, `cleanup()` tears down listeners/intervals
- Keep all existing functionality intact — no feature removal
- All code comments in English, user-facing strings in French

**Ask First:**
- Any change to the VetSaaS UI kit modules (`assets/js/ui/`)

**Never:**
- Modify PHP controllers or domain layer
- Change route URLs
- Remove fixture data (needed for demo pages)

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Navigate sidebar link | Click "Hospitalisations" from dashboard | Page loads with working JS, no console errors | — |
| Navigate back | Browser back button | Previous page re-initializes correctly | — |
| Turbo cache restore | Fast back navigation (cached) | Page shows cached HTML, then re-inits on turbo:load | cleanup() runs on turbo:before-cache |
| Page without JS module | Visit /catalogue (no page JS) | Page works fine, no errors | — |

</frozen-after-approval>

## Code Map

- `assets/app.js` -- Switch from DOMContentLoaded to turbo:load; add page module bootstrap
- `importmap.php` -- Register page modules under `pages/*` namespace
- `assets/js/pages/dashboard.js` -- Extract from dashboard.html.twig
- `assets/js/pages/scheduling-agenda.js` -- Extract from scheduling/dashboard.html.twig
- `assets/js/pages/scheduling-planning.js` -- Extract from scheduling/planning.html.twig
- `assets/js/pages/scheduling-waiting-room.js` -- Extract from scheduling/waiting_room.html.twig
- `assets/js/pages/hospitalisations.js` -- Extract from hospitalisations/index.html.twig
- `assets/js/pages/clients-list.js` -- Extract from clients/list.html.twig
- `assets/js/pages/client-view.js` -- Extract from clients/view.html.twig
- `assets/js/pages/consultation.js` -- Extract from clinical_care/consultation_details.html.twig
- `assets/js/pages/select-clinic.js` -- Extract from select-clinic.html.twig
- Templates (9 files) -- Replace `{% block javascripts %}<script>...</script>{% endblock %}` with `<script type="module">` import

## Tasks & Acceptance

**Execution:**

- [ ] `assets/app.js` -- REFACTOR: replace `DOMContentLoaded` with `turbo:load` for UI init; add page-module dispatcher that reads `data-page` attribute from `<main>` and dynamically imports the matching module
- [ ] `templates/clinic/base.html.twig` -- ADD `data-page` attribute on `<main>` using route name: `data-page="{{ app.request.attributes.get('_route') }}"`
- [ ] `assets/js/pages/dashboard.js` -- CREATE: extract JS from dashboard.html.twig, wrap in `export function init()` / `export function cleanup()`
- [ ] `assets/js/pages/scheduling-agenda.js` -- CREATE: extract from scheduling/dashboard.html.twig
- [ ] `assets/js/pages/scheduling-planning.js` -- CREATE: extract from scheduling/planning.html.twig
- [ ] `assets/js/pages/scheduling-waiting-room.js` -- CREATE: extract from scheduling/waiting_room.html.twig
- [ ] `assets/js/pages/hospitalisations.js` -- CREATE: extract from hospitalisations/index.html.twig
- [ ] `assets/js/pages/clients-list.js` -- CREATE: extract from clients/list.html.twig
- [ ] `assets/js/pages/client-view.js` -- CREATE: extract from clients/view.html.twig
- [ ] `assets/js/pages/consultation.js` -- CREATE: extract from clinical_care/consultation_details.html.twig
- [ ] `assets/js/pages/select-clinic.js` -- CREATE: extract from select-clinic.html.twig
- [ ] `importmap.php` -- ADD entries for all 9 page modules
- [ ] Templates (9 files) -- REPLACE `{% block javascripts %}` content with empty or minimal import; remove `data-turbo-track="reload"` from `<style>` tags
- [ ] `templates/security/login.html.twig`, `lock.html.twig` -- VERIFY: these pages don't extend clinic/base, check if they need Turbo handling

**Acceptance Criteria:**
- Given user navigates between any two clinic pages via sidebar, when page loads, then JS initializes correctly with no console errors
- Given user clicks browser back button, when cached page restores, then cleanup ran and page re-initializes on turbo:load
- Given user visits a page with no JS module (catalogue, rapports), when page loads, then no errors occur

## Design Notes

**Page-module dispatcher pattern:**
```js
// assets/app.js
import './styles/app.css';
import { initUI } from 'vs/ui';

const PAGE_MODULES = {
  clinic_dashboard: 'pages/dashboard',
  clinic_scheduling_dashboard: 'pages/scheduling-agenda',
  clinic_scheduling_planning: 'pages/scheduling-planning',
  clinic_scheduling_waiting_room: 'pages/scheduling-waiting-room',
  clinic_hospitalisations: 'pages/hospitalisations',
  clinic_clients_list: 'pages/clients-list',
  clinic_clients_view: 'pages/client-view',
  clinic_consultation_details: 'pages/consultation',
  clinic_select_clinic: 'pages/select-clinic',
};

let currentCleanup = null;

function onLoad() {
  initUI();
  const page = document.querySelector('[data-page]')?.dataset.page;
  if (page && PAGE_MODULES[page]) {
    import(PAGE_MODULES[page]).then(mod => {
      currentCleanup = mod.cleanup || null;
      mod.init();
    });
  }
}

function onBeforeCache() {
  if (currentCleanup) { currentCleanup(); currentCleanup = null; }
}

document.addEventListener('turbo:load', onLoad);
document.addEventListener('turbo:before-cache', onBeforeCache);
```

**Module structure:**
```js
// assets/js/pages/example.js
let state = {};
export function init() { /* set up DOM, listeners, render */ }
export function cleanup() { /* remove listeners, clear intervals, reset state */ }
```

## Verification

**Commands:**
- `php bin/console cache:clear` -- expected: no errors
- `php bin/console lint:twig templates/` -- expected: 0 errors
- `php bin/console debug:asset-map | grep pages/` -- expected: 9 page modules listed

**Manual checks:**
- Navigate dashboard → clients → agenda → hospitalisations via sidebar: each page works, no console errors
- Browser back: pages re-init correctly
- Ctrl+Shift+J console: zero errors during navigation cycle
