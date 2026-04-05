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
