# Deferred Work

## Placeholder pages for sidebar navigation (2026-04-03)

**Context:** Split from "Complete VetSaaS theme" spec — secondary goal deferred.

**Goals:**
- Create `CatalogueController` + `templates/clinic/catalogue/index.html.twig` (empty-state "Bientôt disponible")
- Create `ReportingController` + `templates/clinic/reporting/index.html.twig` (empty-state "Bientôt disponible")
- Create `ListConsultationsController` + `templates/clinic/clinical_care/index.html.twig` (stub consultation list)
- Wire sidebar links: Consultations→`clinic_consultations`, Catalogue→`clinic_catalogue`, Rapports→`clinic_reporting`

## Waiting room sidebar consistency (2026-04-03)

**Context:** Surfaced by edge-case review of VetSaaS layout fixes spec.

**Goals:**
- Add `{% block show_sidebar %}false{% endblock %}` to `templates/clinic/scheduling/waiting_room.html.twig` for consistency with agenda/planning
- Ensure sidebar-less scheduling pages have alternative navigation (back button or compact topbar nav)

## rpanel tablet overlay/close mechanism (2026-04-03)

**Context:** Surfaced by blind + edge-case review. The rpanel becomes a fixed drawer on tablet but has no overlay or close button.

**Goals:**
- Add rpanel overlay (similar to sidebar-overlay) for tablet breakpoint
- Add a close button inside the rpanel header on tablet
- Wire JS toggle for the rpanel drawer

## Full migration des composants UI vers Tailwind + micro-animations (2026-04-05)

**Context:** Deferred from POC Tailwind enhanced-UI. The POC validates the approach on 2-3 components (Modal, Popover, Select). This item covers the full rollout.

**Goals:**
- Migrate all VetSaaS CSS components to Tailwind utility classes (buttons, forms, data tables, navigation, layout, feedback)
- Add reui.io-inspired micro-animations to all overlay/interactive components (Drawer, Toast, Tabs, Accordion/Collapsible)
- Replace all native `<select>` elements with the custom animated Select component
- Remove legacy VetSaaS component CSS files once fully migrated

## ~~Turbo cache flash on navigation (2026-04-05)~~ — DONE

Fixed in commit 9600920: all page module `init()` functions are now idempotent.

## Align border-radius & shadow systems with Metronic convention (2026-04-06)

**Context:** Surfaced during sidebar popover refinement. Metronic uses a base `--radius: 0.5rem` (8px) with calc-based sizes: `sm = calc(--radius - 4px)`, `md = calc(--radius - 2px)`, `lg = var(--radius)`, `xl = calc(--radius + 4px)`. Our current scale is `sm: 4px, md: 8px, lg: 12px`. Shadows are similarly lighter in Metronic (e.g. `shadow-md shadow-black/5`).

**Goals (do this on a separate branch — impacts 104+ CSS occurrences for radius alone):**

**Border radius:**
- Restructure `@theme` tokens to match Metronic naming:
  - `--radius-sm`: 4px (unchanged)
  - `--radius-md`: 8px → 6px
  - `--radius-lg`: 12px → 8px
  - Add `--radius-xl`: 12px
- Audit every component using `rounded-md` / `rounded-lg` and decide the target size per component (cards, modals, inputs, nav-items, buttons, etc.)
- Update components accordingly — some that were 12px may need to stay at 12px (→ `rounded-xl`)
- Remove the hardcoded `border-radius: 6px` on the popover (would become `@apply rounded-md`)

**Shadows:**
- Review all `--shadow-*` tokens in `@theme` and lighten to match Metronic's subtle approach (opacity 5% instead of 8-14%)
- Audit every component using `shadow-*` utilities and adjust accordingly
- Remove the hardcoded shadow on the popover (would become `@apply shadow-md`)
