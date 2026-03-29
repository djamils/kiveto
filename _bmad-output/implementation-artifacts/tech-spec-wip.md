---
title: 'Kiveto Theme Migration — VetSaaS Design System'
type: 'refactor'
created: '2026-03-29'
status: 'draft'
context: []
---

# Kiveto Theme Migration — VetSaaS Design System

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The application uses an outdated KTUI/Keenicons theme with dual-layout inconsistency (Layout-14 and Layout-15 coexisting in 70 templates), no unified design system, and 718 disorganized asset files. New pages lack templates and routes.

**Approach:** Replace `assets/` with the VetSaaS design system (pure CSS custom-property components + JS UI modules from `docs/frontend-theme/vetsaas-kit/`), replace all Twig templates using the vetsaas-twig reference components and vetsaas-layouts HTML mockups, add controllers and routes for new pages (hospitalisation, lock screen), and deliver everything on a new branch `feature/kiveto-theme`.

## Boundaries & Constraints

**Always:**
- Work on branch `feature/kiveto-theme` (create from current HEAD)
- Use Symfony AssetMapper; CSS via `@import` chain in `app.css` — no `@tailwind` directives, no webpack/npm build
- Preserve all existing Symfony/DDD/CQRS architecture and all existing routes/controllers
- Keep Keenicons for icons (move from `assets/theme/vendors/keenicons/` → `assets/vendors/keenicons/`)
- VetSaaS JS modules exposed via importmap as named specifiers (e.g. `vs/toast`)
- All new Twig pages extend `clinic/base.html.twig` (VetSaaS app shell)
- Login and lock screen extend `base.html.twig` (no sidebar/topbar)

**Ask First:**
- Any change to controller logic or data variables passed to templates
- Any route URL change
- Addition of new database schema or domain entities for hospitalisation

**Never:**
- Add webpack, npm, or node build steps
- Modify PHP domain layer (Entities, Commands, Queries, Handlers)
- Change authentication/security configuration
- Skip Stimulus/Turbo — keep existing JS controllers working

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Visit any migrated page | Authenticated user, valid clinic | Page renders with VetSaaS sidebar + topbar + content | No 500, no missing template |
| Visit /login | Unauthenticated | Login page renders standalone (no sidebar) | — |
| Visit /lock | Authenticated | Lock screen renders standalone | — |
| Visit /clinic/{id}/hospitalisations | Authenticated + clinic selected | Hospitalisation list renders with stub data | 404 if route missing |
| CSS loads | Browser requests app.css | All 7 CSS files resolved via @import, --brand-600 = #4338ca | — |
| Flash message | Any form submit | Toast notification rendered via vs/toast | — |

</frozen-after-approval>

## Code Map

**Source (read-only reference):**
- `docs/frontend-theme/vetsaas-kit/assets/styles/` -- VetSaaS CSS source (vetsaas.css + 6 component files)
- `docs/frontend-theme/vetsaas-kit/assets/js/ui/` -- VetSaaS JS modules (6 files)
- `docs/frontend-theme/vetsaas-twig/templates/` -- Reference Twig components (base + components)
- `docs/frontend-theme/vetsaas-layouts/` -- HTML mockups for 10 pages

**Assets (replace):**
- `assets/styles/app.css` -- CSS entry point → replace with VetSaaS @import chain
- `assets/styles/vetsaas.css` -- NEW: design tokens + reset (copy from vetsaas-kit)
- `assets/styles/components/*.css` -- NEW: 6 component CSS files (copy from vetsaas-kit)
- `assets/js/ui/*.js` -- NEW: 6 VetSaaS JS modules (copy from vetsaas-kit)
- `assets/vendors/keenicons/` -- MOVED from assets/theme/vendors/keenicons/
- `assets/theme/` -- DELETE after keenicons move (718 files)
- `importmap.php` -- Add vs/* named module entries

**Base templates (replace/create):**
- `templates/base.html.twig` -- Minimal HTML root (no sidebar, for login/lock)
- `templates/clinic/base.html.twig` -- VetSaaS app shell (sidebar + topbar + content blocks)
- `templates/components/layout/sidebar.html.twig` -- Sidebar component
- `templates/components/layout/topbar.html.twig` -- Topbar component
- `templates/components/layout/modal.html.twig` -- Modal component
- `templates/components/layout/drawer.html.twig` -- Drawer/bottom-sheet component
- `templates/form/kiveto_form_theme.html.twig` -- Symfony form theme
- `config/packages/twig.yaml` -- Set form_themes

**Page templates (from mockups):**
- `templates/security/login.html.twig` -- from vetos-login.html
- `templates/clinic/select-clinic.html.twig` -- from vetos-clinic-picker.html
- `templates/clinic/dashboard.html.twig` -- from vetos-app-demo.html
- `templates/clinic/clients/list.html.twig` -- from vetos-fiche-client.html (list view)
- `templates/clinic/clients/view.html.twig` -- from vetos-fiche-client.html (detail)
- `templates/clinic/scheduling/dashboard.html.twig` -- from vetos-agenda.html
- `templates/clinic/scheduling/planning.html.twig` -- CREATE from vetos-planning.html
- `templates/clinic/scheduling/waiting_room.html.twig` -- from vetos-salle-attente.html
- `templates/clinic/clinical_care/consultation_details.html.twig` -- from vetos-consultation.html
- `templates/clinic/hospitalisations/index.html.twig` -- NEW from vetos-hospitalisation.html
- `templates/security/lock.html.twig` -- NEW from vetos-lock.html
- `templates/backoffice/base.html.twig` -- Extend clinic/base.html.twig (free design)
- `templates/backoffice/index.html.twig` -- Update to extend new backoffice base
- other `templates/backoffice/**` -- Update extends

**Controllers to update (template path only):**
- `src/Presentation/Clinic/Controller/HomeController.php` -- render → 'clinic/dashboard.html.twig'
- `src/Presentation/Clinic/Controller/SelectClinicController.php` -- render → new paths
- `src/Presentation/Clinic/Controller/Scheduling/DashboardController.php` -- render → 'clinic/scheduling/dashboard.html.twig'
- `src/Presentation/Clinic/Controller/Client/ListClientsController.php` -- render → 'clinic/clients/list.html.twig'
- `src/Presentation/Clinic/Controller/Client/ViewClientController.php` -- render → 'clinic/clients/view.html.twig'
- `src/Presentation/Clinic/Controller/Animal/ListAnimalsController.php` -- render → 'clinic/animals/list.html.twig'
- `src/Presentation/Clinic/Controller/ClinicalCare/ConsultationDetailsController.php` -- render → 'clinic/clinical_care/consultation_details.html.twig'

**New controllers:**
- `src/Presentation/Clinic/Controller/Security/LockController.php` -- NEW: GET /lock → renders security/lock.html.twig
- `src/Presentation/Clinic/Controller/Hospitalisations/ListHospitalisationsController.php` -- NEW: GET /clinic/{clinicId}/hospitalisations → stub

**Cleanup (delete after migration):**
- `templates/clinic/base_layout15.html.twig`
- `templates/clinic/layout-14.html.twig`
- `templates/clinic/dashboard-layout14.html.twig`
- `templates/clinic/dashboard_layout15.html.twig`
- `templates/clinic/partials/` (entire directory)
- `templates/clinic/clients/form.html.twig`, `list.html.twig`, `view.html.twig` (old non-layout15)
- `templates/clinic/clients/list_layout15.html.twig`, `view_layout15.html.twig`
- `templates/clinic/animals/list_layout15.html.twig`, `form_layout15.html.twig`, `view_layout15.html.twig`
- `templates/clinic/scheduling/dashboard_layout15.html.twig`

## Tasks & Acceptance

**Execution:**

*Branch:*
- [ ] `(git)` -- CREATE branch feature/kiveto-theme from bounded-context/bmad HEAD -- isolate theme migration from BMAD work

*Assets — CSS:*
- [ ] `assets/styles/vetsaas.css` -- COPY from docs/frontend-theme/vetsaas-kit/assets/styles/vetsaas.css -- design tokens source of truth
- [ ] `assets/styles/components/layout.css` -- COPY from vetsaas-kit -- app shell, sidebar, topbar classes
- [ ] `assets/styles/components/navigation.css` -- COPY from vetsaas-kit -- tabs, breadcrumbs
- [ ] `assets/styles/components/forms.css` -- COPY from vetsaas-kit -- buttons, inputs, selects, toggles
- [ ] `assets/styles/components/data.css` -- COPY from vetsaas-kit -- tables, badges, vet-specific chips
- [ ] `assets/styles/components/overlays.css` -- COPY from vetsaas-kit -- modals, drawers, popovers
- [ ] `assets/styles/components/feedback.css` -- COPY from vetsaas-kit -- toasts, alerts, progress
- [ ] `assets/styles/app.css` -- REPLACE content with: Google Fonts import + vetsaas.css + 6 component @imports (no @tailwind directives)

*Assets — JS:*
- [ ] `assets/js/ui/drawer.js` -- COPY from vetsaas-kit/assets/js/ui/drawer.js
- [ ] `assets/js/ui/modal.js` -- COPY from vetsaas-kit/assets/js/ui/modal.js
- [ ] `assets/js/ui/toast.js` -- COPY from vetsaas-kit/assets/js/ui/toast.js
- [ ] `assets/js/ui/popover.js` -- COPY from vetsaas-kit/assets/js/ui/popover.js
- [ ] `assets/js/ui/tabs.js` -- COPY from vetsaas-kit/assets/js/ui/tabs.js
- [ ] `assets/js/ui/index.js` -- COPY from vetsaas-kit/assets/js/ui/index.js
- [ ] `importmap.php` -- ADD entries: vs/drawer, vs/modal, vs/toast, vs/popover, vs/tabs, vs/ui (each pointing to ./assets/js/ui/*.js); keep existing Stimulus/Turbo entries

*Assets — Icons & cleanup:*
- [ ] `assets/vendors/keenicons/` -- COPY from assets/theme/vendors/keenicons/ -- preserve icon library
- [ ] `assets/theme/` -- DELETE entire directory after keenicons copy

*Base templates:*
- [ ] `templates/base.html.twig` -- REWRITE as minimal HTML skeleton: charset, viewport, importmap('app'), stylesheets block, body block, javascripts block — no sidebar
- [ ] `templates/clinic/base.html.twig` -- REWRITE as VetSaaS app shell from vetsaas-twig/templates/base.html.twig; update keenicons CSS path to asset('vendors/keenicons/...'); update flash→toast script to use importmap name 'vs/toast'
- [ ] `templates/components/layout/sidebar.html.twig` -- CREATE from vetsaas-twig/templates/components/layout/sidebar.html.twig
- [ ] `templates/components/layout/topbar.html.twig` -- CREATE from vetsaas-twig/templates/components/layout/topbar.html.twig
- [ ] `templates/components/layout/modal.html.twig` -- CREATE from vetsaas-twig/templates/components/layout/modal.html.twig
- [ ] `templates/components/layout/drawer.html.twig` -- CREATE from vetsaas-twig/templates/components/layout/drawer.html.twig
- [ ] `templates/form/kiveto_form_theme.html.twig` -- CREATE from vetsaas-twig/templates/form/vetsaas_form_theme.html.twig
- [ ] `config/packages/twig.yaml` -- ADD form_themes: ['kiveto_form_theme.html.twig']

*Page templates — Security (no sidebar):*
- [ ] `templates/security/login.html.twig` -- REWRITE as Twig extending base.html.twig, from vetos-login.html mockup; keep existing form variables (error, last_username)
- [ ] `templates/security/lock.html.twig` -- CREATE extending base.html.twig, from vetos-lock.html mockup

*Page templates — Clinic (extend clinic/base.html.twig):*
- [ ] `templates/clinic/select-clinic.html.twig` -- REWRITE from vetos-clinic-picker.html; keep clinics variable
- [ ] `templates/clinic/dashboard.html.twig` -- REWRITE from vetos-app-demo.html; keep clinic variable
- [ ] `templates/clinic/no-clinic-access.html.twig` -- REWRITE to use VetSaaS empty state component
- [ ] `templates/clinic/clients/list.html.twig` -- REWRITE from vetos-fiche-client.html list view; keep clients variable
- [ ] `templates/clinic/clients/view.html.twig` -- REWRITE from vetos-fiche-client.html detail view; keep client, animals variables
- [ ] `templates/clinic/animals/list.html.twig` -- REWRITE using VetSaaS data table; keep animals variable
- [ ] `templates/clinic/scheduling/dashboard.html.twig` -- REWRITE from vetos-agenda.html; keep existing scheduling data variables
- [ ] `templates/clinic/scheduling/planning.html.twig` -- CREATE from vetos-planning.html; use same scheduling variables
- [ ] `templates/clinic/scheduling/waiting_room.html.twig` -- REWRITE from vetos-salle-attente.html; keep existing waiting room variables
- [ ] `templates/clinic/clinical_care/consultation_details.html.twig` -- REWRITE from vetos-consultation.html; keep consultation variable
- [ ] `templates/clinic/hospitalisations/index.html.twig` -- CREATE from vetos-hospitalisation.html; stub (no real data needed)

*Backoffice (free design, extend clinic/base.html.twig):*
- [ ] `templates/backoffice/base.html.twig` -- REWRITE extending clinic/base.html.twig with admin-specific topbar title block
- [ ] `templates/backoffice/index.html.twig` -- UPDATE extends to 'backoffice/base.html.twig'
- [ ] `templates/backoffice/clinics/index.html.twig` -- UPDATE extends
- [ ] `templates/backoffice/clinics/new.html.twig` -- UPDATE extends
- [ ] `templates/backoffice/clinics/edit.html.twig` -- UPDATE extends
- [ ] `templates/backoffice/users/index.html.twig` -- UPDATE extends
- [ ] `templates/backoffice/clinic-groups/index.html.twig` -- UPDATE extends
- [ ] `templates/backoffice/clinic-memberships/index.html.twig` -- UPDATE extends
- [ ] `templates/backoffice/clinic-memberships/new.html.twig` -- UPDATE extends
- [ ] `templates/backoffice/translations/index.html.twig` -- UPDATE extends

*Controller updates:*
- [ ] `src/Presentation/Clinic/Controller/HomeController.php` -- UPDATE render path to 'clinic/dashboard.html.twig'
- [ ] `src/Presentation/Clinic/Controller/SelectClinicController.php` -- UPDATE render paths to 'clinic/select-clinic.html.twig' and 'clinic/dashboard.html.twig'
- [ ] `src/Presentation/Clinic/Controller/Scheduling/DashboardController.php` -- UPDATE render to 'clinic/scheduling/dashboard.html.twig'
- [ ] `src/Presentation/Clinic/Controller/Client/ListClientsController.php` -- UPDATE render to 'clinic/clients/list.html.twig'
- [ ] `src/Presentation/Clinic/Controller/Client/ViewClientController.php` -- UPDATE render to 'clinic/clients/view.html.twig'
- [ ] `src/Presentation/Clinic/Controller/Animal/ListAnimalsController.php` -- UPDATE render to 'clinic/animals/list.html.twig'
- [ ] `src/Presentation/Clinic/Controller/ClinicalCare/ConsultationDetailsController.php` -- UPDATE render to 'clinic/clinical_care/consultation_details.html.twig'

*New controllers:*
- [ ] `src/Presentation/Clinic/Controller/Security/LockController.php` -- CREATE: AbstractController, #[Route('/lock', name: 'app_lock')], GET only, renders 'security/lock.html.twig'
- [ ] `src/Presentation/Clinic/Controller/Hospitalisations/ListHospitalisationsController.php` -- CREATE: AbstractController, #[Route('/clinic/{clinicId}/hospitalisations', name: 'app_clinic_hospitalisations')], stub render with empty array

*Cleanup old files:*
- [ ] `templates/clinic/base_layout15.html.twig` -- DELETE
- [ ] `templates/clinic/layout-14.html.twig` -- DELETE
- [ ] `templates/clinic/dashboard-layout14.html.twig` -- DELETE
- [ ] `templates/clinic/dashboard_layout15.html.twig` -- DELETE
- [ ] `templates/clinic/partials/` -- DELETE entire directory
- [ ] `templates/clinic/clients/form.html.twig` -- DELETE
- [ ] `templates/clinic/clients/list.html.twig` (old, before replaced above) -- superseded
- [ ] `templates/clinic/clients/view.html.twig` (old) -- superseded
- [ ] `templates/clinic/clients/list_layout15.html.twig` -- DELETE
- [ ] `templates/clinic/clients/view_layout15.html.twig` -- DELETE
- [ ] `templates/clinic/clients/_contact_method_row*.html.twig` -- DELETE (inline in new view)
- [ ] `templates/clinic/animals/list_layout15.html.twig` -- DELETE
- [ ] `templates/clinic/animals/form_layout15.html.twig` -- DELETE
- [ ] `templates/clinic/animals/view_layout15.html.twig` -- DELETE
- [ ] `templates/clinic/scheduling/dashboard_layout15.html.twig` -- DELETE
- [ ] `templates/clinic/scheduling/_agenda.html.twig` -- DELETE (inline in new dashboard)
- [ ] `templates/clinic/index.html.twig` -- DELETE (superseded by dashboard.html.twig)

**Acceptance Criteria:**
- Given user visits /login, when page loads, then login form renders with VetSaaS styles and no sidebar
- Given authenticated user visits /clinic/{id}/dashboard, when page loads, then page renders with VetSaaS sidebar + topbar and clinic name in sidebar
- Given authenticated user visits any migrated page, when page loads, then no 500 error and no "template not found" error occurs
- Given user visits /lock, when page loads, then lock screen renders without sidebar
- Given user visits /clinic/{id}/hospitalisations, when page loads, then hospitalisation stub page renders
- Given app.css is requested, when CSS is parsed, then --brand-600 resolves to #4338ca and .app-shell class is defined
- Given VetSaaS JS modules are imported, when importmap is checked, then vs/toast, vs/modal, vs/drawer are resolvable
- Given any form is submitted with validation errors, when errors are displayed, then they use kiveto_form_theme styling

## Design Notes

**CSS @import with AssetMapper:** AssetMapper resolves CSS `@import` statements at serve-time. All imported files must live under `assets/` paths. The `app.css` imports only relative paths (`./vetsaas.css`, `./components/layout.css` etc.) — this is AssetMapper-compatible.

**JS modules via importmap:** VetSaaS JS modules cannot use absolute `/assets/js/ui/toast.js` paths (AssetMapper fingerprints them). Instead, expose them as named importmap specifiers (`vs/toast`), then import with `import('vs/toast')` in templates. Example in base template flash handling:
```js
import('vs/toast').then(({ toast }) => toast.success('Saved'));
```

**Keenicons path update:** All icon references in new templates use `{{ asset('vendors/keenicons/css/keenicons-duotone.css') }}` (new location). The old path `theme/vendors/keenicons/` is gone.

**Login/Lock standalone:** These pages extend the root `templates/base.html.twig` (no sidebar), not `clinic/base.html.twig`.

## Verification

**Commands:**
- `php bin/console cache:clear` -- expected: no errors
- `php bin/console lint:twig templates/` -- expected: 0 errors
- `php bin/console debug:router | grep -E 'app_lock|hospitalisations'` -- expected: 2 new routes listed
- `php bin/console debug:asset-map | grep 'vs/'` -- expected: vs/toast, vs/modal etc. listed

**Manual checks:**
- Browse /login, /lock, /clinic/1/dashboard, /clinic/1/clients, /clinic/1/scheduling — all render with VetSaaS design
- DevTools network tab: no 404 on CSS/JS assets
- Flash a success message: toast appears top-right
