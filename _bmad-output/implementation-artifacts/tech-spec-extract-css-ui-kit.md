---
title: 'Extract inline CSS — common overrides + 4 heaviest pages'
type: 'refactor'
created: '2026-04-04'
status: 'done'
baseline_commit: '7ead513'
context: ['_bmad-output/project-context.md']
---

# Extract inline CSS — common overrides + 4 heaviest pages

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** 10 Twig templates contain ~3500 lines of inline CSS in `<style>` blocks. ~500 lines are pure duplicates of existing component styles (`.btn`, `.form-input`, `.modal-*`, `.badge`). Shared patterns (full-height layout overrides, toast, form-grid) are copy-pasted across 5+ templates. This makes the templates bloated and the UI kit fragmented.

**Approach:** Create `assets/styles/pages/common.css` for shared page patterns, extract page-specific CSS from the 4 heaviest templates (clients-list, hospitalisations, scheduling-agenda, scheduling-planning) into dedicated files, remove all duplicate component redefinitions, and import via `app.css`. Templates keep only an empty `{% block stylesheets %}` or a Lucide CDN script.

## Boundaries & Constraints

**Always:**
- Use AssetMapper `@import` chain in `app.css` — no webpack
- Keep all visual rendering identical — zero visual regression
- All code comments in English

**Ask First:**
- Any modification to existing component CSS files (layout.css, forms.css, etc.)

**Never:**
- Modify PHP controllers
- Change HTML structure or JS modules
- Remove page-specific CSS that has no equivalent in components

</frozen-after-approval>

## Code Map

- `assets/styles/app.css` -- Add `@import` for new page CSS files
- `assets/styles/pages/common.css` -- CREATE: full-height overrides, toast, form-grid, mini-cal, vet-filter, scrollbar
- `assets/styles/pages/clients-list.css` -- CREATE: extract from clients/list.html.twig (~170 lines unique)
- `assets/styles/pages/hospitalisations.css` -- CREATE: extract from hospitalisations/index.html.twig (~150 lines unique)
- `assets/styles/pages/scheduling-agenda.css` -- CREATE: extract from scheduling/dashboard.html.twig (~140 lines unique)
- `assets/styles/pages/scheduling-planning.css` -- CREATE: extract from scheduling/planning.html.twig (~140 lines unique)
- 4 Twig templates -- REMOVE `<style>` blocks (keep only Lucide CDN if needed)

## Tasks & Acceptance

**Execution:**

- [ ] `assets/styles/pages/common.css` -- CREATE: extract shared patterns from 5+ templates: full-height overrides (.page-full-height .app-scroll, .app-body, .app-main-wrap), scrollbar thin styles, toast (#toast), form-grid (.form-row, .form-grid-2, .form-grid-3), mini-cal (.mini-cal-*), vet-filter (.vet-filter, .vet-chip, .vet-dot), page sidebar (.page-sidebar pattern for agenda/planning own sidebar), btn-burger override, drawer-overlay page override
- [ ] `assets/styles/pages/clients-list.css` -- CREATE: extract unique page CSS (filter pills .fp, table .tbl-*, client rows .client-row-*, detail panel .detail-panel .dp-*, pagination .pg-btn, column picker .col-picker-*, slide/bottom-sheet, responsive breakpoints). Remove all .btn/.form-input/.modal-*/.badge redefinitions that exist in components.
- [ ] `assets/styles/pages/hospitalisations.css` -- CREATE: extract unique page CSS (.hosp-panel, .hcard, .soin-row, .soin-check, .evo-*, .hydra-*, .severity-bar, detail tabs .dtab, .irow). Remove component redefinitions.
- [ ] `assets/styles/pages/scheduling-agenda.css` -- CREATE: extract unique page CSS (.agenda-shell, .agenda-sidebar, .agenda-content, .agenda-wrap, .agenda-head, .agenda-body, .rdv-block, .free-slot, .rdv-popup, .new-rdv-popup, .motif-chip, .now-line, .now-dot, responsive breakpoints). Remove component redefinitions.
- [ ] `assets/styles/pages/scheduling-planning.css` -- CREATE: extract unique page CSS (.planning-wrap, .planning-head, .planning-body, .vet-row, .vet-label, .vet-row-grid, .hour-slot, .act-block-*, .popup-overlay, .popup-box, .month-grid, .month-cell, .view-btn, responsive breakpoints). Remove component redefinitions.
- [ ] `assets/styles/app.css` -- ADD @import for pages/common.css and 4 page files
- [ ] `templates/clinic/clients/list.html.twig` -- REMOVE entire `<style>` content from stylesheets block
- [ ] `templates/clinic/hospitalisations/index.html.twig` -- REMOVE `<style>` content, keep Lucide CDN script if present
- [ ] `templates/clinic/scheduling/dashboard.html.twig` -- REMOVE `<style>` content
- [ ] `templates/clinic/scheduling/planning.html.twig` -- REMOVE `<style>` content

**Acceptance Criteria:**
- Given user visits any of the 4 refactored pages, when page loads, then visual rendering is identical to before (zero regression)
- Given user inspects page source, then no `<style>` block exists in the HTML (except Lucide CDN)
- Given `php bin/console debug:asset-map | grep pages/`, then 5 CSS files listed (common + 4 pages)

## Design Notes

**CSS class scoping:** Page CSS files use page-specific class names that don't collide. The `common.css` file uses utility patterns shared across pages. Component redefinitions (`.btn`, `.badge`, `.modal-*`, etc.) are simply deleted since the global component CSS already provides them.

**Full-height pattern:** Instead of each page repeating `.app-scroll{overflow:hidden!important}`, `common.css` defines a `.page-full-height` class that templates apply on `<main>`: `<main class="app-body page-full-height">`. This removes the need for `!important` overrides.

## Verification

**Commands:**
- `php bin/console cache:clear` -- expected: no errors
- `php bin/console lint:twig templates/` -- expected: 0 errors
- `php bin/console debug:asset-map | grep pages/` -- expected: 5 CSS files

**Manual checks:**
- Navigate to clients list, hospitalisations, agenda, planning — all render identically
- DevTools: no inline `<style>` in page source for these 4 pages
