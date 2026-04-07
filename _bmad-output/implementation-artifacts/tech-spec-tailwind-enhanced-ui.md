---
title: 'POC Tailwind + enhanced micro-animations on Modal, Popover, Select'
type: 'feature'
created: '2026-04-05'
status: 'done'
commits: '3f5bf15, f3e4180, 16c61c4, 808ac10, d0ad5f4, 27a22b5'
baseline_commit: 'f595611'
context:
  - '_bmad-output/project-context.md'
---

# POC Tailwind + enhanced micro-animations on Modal, Popover, Select

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The current UI feels raw — overlays snap open/closed with basic `ease` timing, no scale transforms, no backdrop blur. The design system is named "VetSaaS" instead of "Kiveto". Tailwind CSS is not installed despite being the desired utility framework.

**Approach:** Three-phase progressive approach: (1) install Tailwind + rename VetSaaS→Kiveto + map tokens, (2) enhance existing overlay animations with reui.io-inspired micro-animations, (3) build a custom Select as the first full-Tailwind component.

## Boundaries & Constraints

**Always:**
- Cohabitate with existing CSS — Tailwind adds utilities, existing component CSS stays functional
- Keep all existing component JS APIs intact (`modal.open()`, `popover.open()`, etc.)
- Use `cubic-bezier(0.4, 0, 0.2, 1)` as default easing, `cubic-bezier(0.16, 1, 0.3, 1)` for overlays
- New Select component must be a Stimulus controller with `data-*` attribute API
- All code comments in English
- Each phase must be independently testable before moving to the next

**Ask First:**
- Changing the base layout template structure
- Modifying any existing Stimulus controller beyond `hello_controller.js`

**Never:**
- Rewrite all existing component CSS to Tailwind (deferred to full-migration goal)
- Add Node.js, npm, or Webpack
- Break existing pages — this is additive only

</frozen-after-approval>

## Code Map

- `composer.json` -- add `symfonycasts/tailwind-bundle`
- `tailwind.config.js` -- new; Kiveto design tokens (colors, spacing, radius, shadows, z-index)
- `assets/styles/kiveto.css` -- renamed from `vetsaas.css`; add Tailwind directives
- `assets/styles/app.css` -- update import path vetsaas→kiveto
- `importmap.php` -- rename `vs/*` entries to `kiveto/*`
- `assets/js/ui/*.js` -- update internal imports from `vs/` to `kiveto/`
- `assets/app.js` -- update imports from `vs/ui` to `kiveto/ui`
- `assets/styles/components/overlays.css` -- enhanced animations (fade+scale, backdrop blur, exit anims)
- `assets/js/ui/modal.js` -- exit animation support
- `assets/js/ui/popover.js` -- enter/exit animations with directional nudge
- `assets/controllers/select_controller.js` -- new Stimulus controller for custom select
- `templates/components/select.html.twig` -- new Twig partial for enhanced select markup

## Tasks & Acceptance

### Phase 1 — Foundations (Tailwind + rename + tokens)

- [ ] `composer.json` + `config/` -- install `symfonycasts/tailwind-bundle`, run `php bin/console tailwind:init`
- [ ] `tailwind.config.js` -- configure with Kiveto tokens mapped from current CSS custom properties
- [ ] `assets/styles/vetsaas.css` → `assets/styles/kiveto.css` -- rename file, add `@tailwind base; @tailwind components; @tailwind utilities;`
- [ ] `assets/styles/app.css` -- update import path vetsaas→kiveto
- [ ] `importmap.php` + `assets/js/ui/*.js` + `assets/app.js` -- rename all `vs/` references to `kiveto/`
- [ ] Verify: `php bin/console tailwind:build` compiles, `grep -ri "vetsaas\|\"vs/" assets/ importmap.php` returns zero matches

### Phase 2 — Enhanced overlay animations (Modal, Popover, Drawer)

- [ ] `assets/styles/components/overlays.css` -- new keyframes: `animate-in` (opacity 0→1 + scale 0.95→1, .15s) and `animate-out` (reverse); `backdrop-filter: blur(4px)` on overlay backgrounds; easing `cubic-bezier(0.16, 1, 0.3, 1)`
- [ ] `assets/js/ui/modal.js` -- add exit animation: apply animate-out class, wait for `animationend`, then hide
- [ ] `assets/js/ui/popover.js` -- add enter/exit animations: fade+scale + directional nudge (slide-down when below, slide-up when above anchor)
- [ ] Verify: modal/popover open and close smoothly with no snap, backdrop has blur

### Phase 3 — Custom Select component (first full-Tailwind component)

- [ ] `assets/controllers/select_controller.js` -- Stimulus controller: open/close dropdown with fade+scale animation, keyboard nav (arrow keys, enter, escape), search/filter, click-outside-close, sync value to hidden input
- [ ] `templates/components/select.html.twig` -- Twig partial: trigger button + dropdown panel styled 100% with Tailwind utilities, slots for option customization
- [ ] Verify: select opens/closes smoothly, keyboard nav works, search filters options, value syncs

**Acceptance Criteria:**
- Given Tailwind is installed, when running `php bin/console tailwind:build`, then CSS compiles without errors
- Given a modal is opened, when observing the transition, then it fades in with scale 0.95→1 over 150ms with cubic-bezier easing and backdrop blur
- Given a modal is closed, when observing, then it fades out with scale 1→0.95 before being hidden
- Given a popover opens below its anchor, when observing, then it fades in with scale + slight downward nudge
- Given the custom select is clicked, when the dropdown appears, then it animates identically to popover
- Given the custom select is open, when typing, then options filter in real-time
- Given the custom select is open, when pressing arrow keys + enter, then navigation and selection work
- Given any page loads, when inspecting, then zero references to "vetsaas" or "vs/" remain in JS/CSS imports

## Verification

**Commands:**
- `php bin/console tailwind:build` -- expected: successful CSS compilation
- `grep -ri "vetsaas\|\"vs/" assets/ importmap.php` -- expected: zero matches
- `php bin/console cache:clear && php bin/console asset-map:compile` -- expected: no errors

**Manual checks:**
- Open a modal → smooth fade+scale+blur animation in and out
- Open a popover → smooth directional fade+scale
- Use the custom select → smooth dropdown, keyboard navigation, search filters
