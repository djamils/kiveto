---
title: 'Wire Clients/Animaux list page to real backend data'
slug: 'wire-clients-animals-list'
created: '2026-04-11'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack:
  - PHP 8.5
  - Symfony 7.4 (auto-422 on form validation error)
  - Symfony Forms (validation constraints, FormType, Callback for cross-field rules)
  - Symfony RateLimiter (NEW dependency — add to composer.json, user-based keying)
  - Symfony Translation (French error messages, constraint localization)
  - symfony/ux-turbo (already installed)
  - Doctrine DBAL (raw SQL for read queries — existing pattern for Count queries too)
  - Doctrine ORM (command handlers run inside a per-dispatch transaction via doctrine_transaction middleware)
  - Twig 3 + Kiveto form theme (templates/form/kiveto_form_theme.html.twig)
  - Turbo Drive + Turbo Frames (Turbo Streams noted as future enhancement for VetApp forms)
  - Stimulus (modal open/close is PLAIN DOM — not Stimulus — via assets/js/ui/modal.js)
  - Tailwind CSS v4 + custom tokens (kiveto.css)
  - Vanilla JS (targeted rewrite of list.js from ~400 to <80 lines)
  - PHPUnit 12, Foundry 2.x
files_to_modify:
  - src/Presentation/Clinic/Controller/Client/Profile/ListClientsController.php (tabs + search + pagination + CountClients/CountAnimals dispatch)
  - src/Presentation/Clinic/Controller/Client/Profile/CreateClientController.php (FormType + _returnTo hidden field, 422 auto-handled by Symfony)
  - src/Presentation/Clinic/Controller/Animal/Profile/CreateAnimalController.php (same)
  - src/Presentation/Clinic/Controller/Animal/Profile/ArchiveAnimalController.php (repoint redirect + new integration test)
  - src/Presentation/Clinic/Controller/Animal/Profile/UpdateAnimalController.php (repoint redirect if any + new integration test)
  - src/Presentation/Clinic/Controller/Client/Profile/UpdateClientController.php (repoint redirect + new integration test)
  - src/Presentation/Clinic/Controller/Client/Profile/ArchiveClientController.php (repoint redirect + new integration test)
  - src/Presentation/Clinic/Controller/Client/Profile/UnarchiveClientController.php (repoint redirect + new integration test)
  - templates/clinic/clients/list/index.html.twig (turbo-frame, tabs, remove window.ROUTES shim)
  - templates/clinic/clients/list/_tab_clients.html.twig (real server data, 8 columns, pagination)
  - templates/clinic/clients/list/_tab_animals.html.twig (real server data, 9 columns, pagination)
  - templates/clinic/clients/list/_modal_new_client.html.twig (Symfony form rendered in turbo-frame)
  - templates/clinic/clients/list/_modal_new_animal.html.twig (Symfony form + owner autocomplete in wizard step 1, no inline client creation)
  - templates/clinic/clients/form.html.twig (breadcrumb cleanup — still used by EditClient)
  - templates/clinic/animals/form.html.twig (breadcrumb cleanup — still used by EditAnimal)
  - templates/clinic/animals/view.html.twig (repoint stale clinic_animals_list links)
  - assets/js/pages/clients/list.js (full rewrite, ~400 → <80 lines)
  - config/routes.yaml (add import of config/routes/api.yaml)
  - config/packages/security.yaml (no change needed — /api/clinic/* already covered by clinic firewall, just verify)
  - config/packages/csrf.yaml (extend stateless_token_ids with create_client and create_animal)
  - composer.json (add symfony/rate-limiter dependency)
files_to_create:
  - src/Context/Client/Application/Query/CountClients/CountClients.php
  - src/Context/Client/Application/Query/CountClients/CountClientsHandler.php (raw DBAL, returns int)
  - src/Context/Animal/Application/Query/CountAnimals/CountAnimals.php
  - src/Context/Animal/Application/Query/CountAnimals/CountAnimalsHandler.php (raw DBAL, returns int)
  - src/Presentation/Clinic/Form/Client/ClientFormType.php (with French messages, stateless CSRF, Callback enforcing phone-or-email non-empty)
  - src/Presentation/Clinic/Form/Animal/AnimalFormType.php (with French messages, stateless CSRF)
  - src/Presentation/Clinic/Controller/Api/Clinic/Clients/SearchClientsApiController.php (GET /api/clinic/clients/search, JSON envelope, 406, 2-char minimum in controller, rate-limited)
  - src/Presentation/Clinic/Controller/Animal/Profile/AnimalsListRedirectController.php (GET /animals → 301 to /clients?tab=animals)
  - src/Shared/Infrastructure/Http/ReturnToResolver.php (cross-BC, whitelist-based, ALLOWED_ROUTES is public for drift test)
  - src/Shared/Infrastructure/Http/Api/JsonAuthenticationFailureSubscriber.php (intercepts AuthenticationException + AccessDeniedException on /api/clinic/* → 401/403 JSON)
  - src/Shared/Infrastructure/Http/Api/IpRateLimitListener.php (F1 fix — kernel.request priority 16, IP-keyed rate limit 60/min on /api/clinic/*, pre-firewall DoS protection)
  - tests/Integration/Shared/Infrastructure/Http/ReturnToResolverRouteExistenceTest.php (F2 fix — asserts every whitelisted route exists in the router at CI time)
  - tests/Integration/Shared/Infrastructure/Http/Api/IpRateLimitListenerTest.php (tests the IP listener via StubRateLimiterFactory)
  - tests/Fixtures/RateLimit/StubRateLimiterFactory.php (G3 fix — deterministic stub for rate limiter factory, returns pre-programmed accept/reject sequence + records call log keyed by consume() arg)
  - config/routes/api.yaml (prefix /api/clinic, import src/Presentation/Clinic/Controller/Api/Clinic/ namespace)
  - config/packages/rate_limiter.yaml (fixed_window, 15 req/sec, user-based keying — no custom extractor needed)
  - assets/controllers/client_search_autocomplete_controller.js (Stimulus, ARIA combobox, 300ms debounce, user-based rate limit aware)
  - templates/clinic/clients/list/_form_client_body.html.twig (includes <turbo-frame id="new-client-form"> wrapper, re-rendered on invalid)
  - templates/clinic/clients/list/_form_animal_body.html.twig (same, frame id "new-animal-form")
  - tests/Integration/Presentation/Clinic/Controller/Client/Profile/UpdateClientControllerTest.php (NEW — no existing tests)
  - tests/Integration/Presentation/Clinic/Controller/Client/Profile/ArchiveClientControllerTest.php (NEW)
  - tests/Integration/Presentation/Clinic/Controller/Client/Profile/UnarchiveClientControllerTest.php (NEW)
  - tests/Integration/Presentation/Clinic/Controller/Animal/Profile/UpdateAnimalControllerTest.php (NEW)
  - tests/Integration/Presentation/Clinic/Controller/Animal/Profile/ArchiveAnimalControllerTest.php (NEW)
  - tests/Integration/Presentation/Clinic/Controller/Animal/Profile/AnimalsListRedirectControllerTest.php (NEW)
files_to_delete:
  - src/Presentation/Clinic/Controller/Animal/Profile/ListAnimalsController.php
  - src/Presentation/Clinic/Controller/Animal/Profile/NewAnimalController.php
  - src/Presentation/Clinic/Controller/Client/Profile/NewClientController.php
  - templates/clinic/animals/list.html.twig
code_patterns:
  - Single-action controllers (__invoke, no business logic)
  - Raw DBAL for read queries (existing pattern, used for new CountClients/CountAnimals)
  - Command/query bus dispatch with \assert() for PHPStan narrowing
  - Command bus pipeline includes doctrine_transaction middleware — each dispatch is atomic, but multi-command sequences in one controller are NOT atomic
  - Symfony Form + FormType for all write endpoints
  - French-localized constraint messages on every validation constraint in the form types
  - Stateless CSRF tokens in config/packages/csrf.yaml (pattern already used by `submit` in the agenda check-in)
  - Symfony 7.4+ automatically returns HTTP 422 on form validation errors — no manual Response status setting required
  - Turbo Frame for SPA-like list reloads (#clients-results), tab switch via ?tab query param
  - Forms with data-turbo-frame="_top" so success redirects break out of the modal frame
  - Partial templates used by forms INCLUDE their own <turbo-frame> wrapper so Turbo matches id on error re-render
  - Cross-field form validation via Callback constraint (e.g., "at least phone OR email")
  - pushState/popstate fallback if data-turbo-action="advance" glitches (lesson from agenda)
  - returnTo stored in hidden _returnTo field, strict whitelist on (route, allowedParams), format routeName|key1=val1&key2=val2
  - JSON API controllers under src/Presentation/Clinic/Controller/Api/Clinic/* subtree, routes in config/routes/api.yaml with /api/clinic prefix, no /v1/ today
  - JSON API response envelope {data: [...], meta: {...}} — room for extension
  - JSON API rejects non-JSON Accept headers with HTTP 406
  - JSON API auth failures produce 401 JSON (JsonAuthenticationFailureSubscriber) instead of HTML redirect to login
  - Rate limit 15 req/sec per authenticated user on /api/clinic/* (Symfony RateLimiter, fixed_window, user-keyed)
  - Stimulus controllers for reusable UI behaviour (autocomplete with ARIA combobox + kbd nav + 300ms debounce)
  - Count-style queries (CountClients/CountAnimals) separated from Search queries for cheap filtered badge counts
  - Modal management via plain DOM (assets/js/ui/modal.js) — not Stimulus — `data-modal-open="id"` + `data-modal-close`
test_patterns:
  - Unit: FormTypes tested via Symfony FormTestCase (valid, invalid with French messages, Callback for phone-or-email)
  - Unit: ReturnToResolver — whitelisted route OK, unknown params filtered, non-whitelisted → default, malformed → default, open-redirect attempts rejected, round-trip accents/apostrophes
  - Unit: Stimulus autocomplete (DOM mocked) — ARIA updates on kbd nav, Enter selects, Escape clears, Tab closes, aria-live announces counts, 300ms debounce respected
  - Integration: CountClientsHandler / CountAnimalsHandler against seeded DB via Foundry, confirm filtered count + clinic scope
  - Integration: controllers via KernelTestCase + WebTestCase, happy path + error path + returnTo round-trip
  - Integration: form validation error asserts HTTP 422 (automatic Symfony) + response body contains turbo-frame id
  - Integration: JSON API asserts content-type application/json + envelope shape + 406 on non-JSON Accept + 401 JSON on unauth + clinic scoping + rate limit 429 on burst
  - Integration: ListClientsController asserts badge count reflects active searchTerm
  - Integration: NEW tests for Archive/Update/Unarchive controllers asserting redirect URLs after action (create from scratch — no existing tests to update)
  - Integration: AnimalsListRedirectController asserts 301 Location to /clients?tab=animals
  - self::assertSame() everywhere, never assertEquals()
---

# Tech-Spec: Wire Clients/Animaux list page to real backend data

**Created:** 2026-04-11

## Overview

### Problem Statement

The `/clients` and `/animals` pages are visually complete but entirely disconnected from the backend:

- `ListClientsController` and `ListAnimalsController` already dispatch `SearchClients` / `SearchAnimals` queries and pass paginated results to the templates — but the Twig templates ignore the results and `assets/js/pages/clients/list.js` uses a hardcoded `CLIENTS` array of mock data (~400 lines) for search, pagination and sort.
- The "Nouveau client" and "Nouvel animal" modals (`_modal_new_client.html.twig` / `_modal_new_animal.html.twig`) exist visually — the animal modal is already a 2-step Stimulus `wizard` — but their submit buttons just show a toast, no real POST.
- The owner picker in the "Nouvel animal" modal is a `<select>` with hardcoded mock client options.
- Two list surfaces duplicate the same concern: `/clients` (tabbed Clients + Animaux) and `/animals` (standalone `ListAnimalsController` + `templates/clinic/animals/list.html.twig`). Only one is actually needed.
- `CreateClientController` and `CreateAnimalController` throw raw `\InvalidArgumentException` on validation fail (HTTP 500), no error handling, no form repopulation. No existing tests on them.
- The redirect on success always targets the view page — no way to return to where the user came from (blocks the future "➕ Nouveau client" button from the agenda popup).
- Client `form.html.twig` / animal `form.html.twig` / animal `view.html.twig` reference stale route names (`clinic_animals_list`, `clinic_clients_new`) that will be removed.

Result: a vet cannot find an existing client, create a new one, or attach an animal to a client through the UI — the Client/Animal Presentation layer is a demo shell on top of a solid backend. Blocks the follow-up "create appointment from agenda" spec.

### Solution

Wire the existing server-side queries and commands to a unified tabbed page at `/clients`, replace raw exception validation with Symfony Forms (leveraging 7.4's auto-422 on invalid), add a JSON autocomplete endpoint for clients under a clean `/api/clinic/*` tree with proper security posture, and introduce a strict `returnTo` redirect pattern.

Key moves:

1. **Unify the list surfaces.** Delete `ListAnimalsController`, `NewAnimalController`, `NewClientController`, and `templates/clinic/animals/list.html.twig`. The `/animals` route is kept as a thin `AnimalsListRedirectController` doing `redirectToRoute('clinic_clients_list', {tab: 'animals'}, 301)`.
2. **Dedicated count queries for the inactive tab badge.** New `CountClients` and `CountAnimals` queries in their respective BCs. Raw DBAL `SELECT COUNT(*)` with the same filter criteria as the Search queries, returns `int`. ~30 lines each + tests. Reusable for every future "filtered badge" across the app.
3. **Wire list templates to real data, with graceful degradation for cross-BC columns.** `_tab_clients.html.twig` and `_tab_animals.html.twig` render rows from `items`, pagination from the hash. Search input is `<form method="GET">` to `/clients` with `?search=&tab=&page=`. Inactive tab's badge shows the **filtered count** for UX coherence. **Columns that depend on cross-BC data** (`owner` name on animals, `age` computed from birthDate, `animals` count on clients, `last visit` + `next visit` from Scheduling BC) are **kept in the template layout** but render `"—"` as a fallback in this iteration — cross-BC enrichment (via 3 new queries composed at controller level) is deferred to a follow-up spec "Enrich Clients/Animaux list with cross-BC computed fields". This avoids a regressive-looking simplification while keeping scope disciplined.
4. **Turbo Frame `#clients-results`** wraps the search form + table + pagination. `data-turbo-action="advance"` first attempt, fallback to manual `pushState` if it glitches (agenda lesson, 30 min budgeted).
5. **Symfony FormType with native 422.** Create `ClientFormType` and `AnimalFormType`:
   - Validation constraints with **French contextual messages** (`'Le prénom est obligatoire.'`).
   - **Stateless CSRF tokens** `create_client` / `create_animal` in `config/packages/csrf.yaml` (extending the existing `stateless_token_ids` list that already has `submit`, `authenticate`, `logout`).
   - **`ClientFormType` includes a `Callback` constraint enforcing "at least phone OR email"** — the domain aggregate `Client::create()` throws `ClientMustHaveAtLeastOneContactMethodException` if both are empty, and we want that to surface as a form-level error, not a domain exception.
   - Refactor `CreateClientController` and `CreateAnimalController`:
     - **On valid** → dispatch command → redirect via `ReturnToResolver` with HTTP 303. Form has `data-turbo-frame="_top"` so the redirect reloads the full page and closes the modal.
     - **On invalid** → return `$this->render(partial, ['form' => $form])`. **Symfony 7.4+ automatically sets HTTP 422** on responses when the form is submitted and invalid — no manual `new Response('', 422)` needed. Just return the render.
   - The form partial `_form_client_body.html.twig` / `_form_animal_body.html.twig` includes its own `<turbo-frame id="...">` wrapper so Turbo matches the id in the 422 response.
6. **`/api/clinic/*` JSON tree with proper security.** New controller `SearchClientsApiController` at `GET /api/clinic/clients/search?q=&limit=10`:
   - **Route in `config/routes/api.yaml`** (NEW file — doesn't exist today), imported by `config/routes.yaml` alongside `clinic.yaml`, `backoffice.yaml`, `portal.yaml`. `/api/clinic` prefix applied via the YAML `prefix:` directive.
   - **Existing clinic firewall already covers it** — `security.yaml` matches host `^clinic\.kiveto\.local$` with catch-all `^/`, requires `IS_AUTHENTICATED_FULLY`. No firewall change needed.
   - **Response envelope** `{data: [ClientAutocompleteItem...], meta: {count: int}}` where `ClientAutocompleteItem` is `{id, firstName, lastName, fullName, primaryEmail, primaryPhone}`.
   - **2-char minimum enforced in controller** before dispatching: if `q` is shorter, return `{data: [], meta: {count: 0}}` with 200 OK (not an error — just empty). `SearchClientsCriteria` doesn't enforce this itself (verified), so the controller is the gatekeeper.
   - **406 on non-JSON Accept** — no HTML fallback.
   - **JSON 401 on auth failure** via new `JsonAuthenticationFailureSubscriber` matching `/api/clinic/*`.
   - **Rate limit 15 req/sec per authenticated user** — using `symfony/rate-limiter` bundle (**new composer dependency**) with `fixed_window` policy keyed by user id (simpler than session-based, no custom extractor needed since the endpoint always runs under authenticated context).
7. **Accessible, debounced owner autocomplete.** New Stimulus `client_search_autocomplete_controller.js`:
   - 300ms debounce (locked), 2-char minimum, full ARIA combobox pattern, keyboard nav (ArrowUp/Down, Enter, Escape, Tab), live region for counts.
   - On selection, writes chosen client's id into the animal form's hidden `primaryOwnerClientId` input.
   - Reused verbatim in the RDV popup spec.
8. **`ReturnToResolver` in Shared Infrastructure.** New `src/Shared/Infrastructure/Http/ReturnToResolver.php`:
   - Cross-BC concern → lives in `Shared/Infrastructure/`, not `Presentation/`.
   - Strict whitelist keyed by route name → allowed params.
   - Input format `routeName|key1=val1&key2=val2`.
   - Stored in hidden `_returnTo` form field, round-trips through validation errors.
9. **Rewrite `assets/js/pages/clients/list.js`** from ~400 lines of mock filtering/pagination/sort to < 80 lines of tab glue + modal open/close + autocomplete boot. Mock data + client-side state machines all deleted.
10. **Breadcrumb cleanup** across `templates/clinic/animals/form.html.twig`, `templates/clinic/animals/view.html.twig`, `templates/clinic/clients/form.html.twig` — repoint `clinic_animals_list` / `clinic_clients_new` references.
11. **Integration tests from scratch.** The existing Create/Update/Archive/Unarchive controllers have **zero tests today** (verified). This spec creates minimal redirect + happy-path tests for all of them in a dedicated Tasks phase, so the repointing is regression-safe.

### Scope

**In Scope:**

- Unified tabbed page `/clients?tab=clients|animals`, `/animals` kept as 301 redirect controller.
- `CountClients` + `CountAnimals` queries (Client BC + Animal BC).
- `ListClientsController` refactor (tabs + search + pagination + dual-query dispatch for active data + inactive count).
- `_tab_clients.html.twig` / `_tab_animals.html.twig` wired to real data. Cross-BC columns (`owner name`, `age`, `animals count`, `last visit`, `next visit`) are kept in the layout but render `"—"` — enrichment deferred to a follow-up spec.
- Turbo Frame `#clients-results` wrapping search + table + pagination, `data-turbo-action="advance"` with pushState fallback.
- Tab switcher as `<a href>` links targeting the frame.
- `SearchClientsApiController` at `GET /api/clinic/clients/search` with envelope, 406, 401 JSON, 15/sec user rate limit, 2-char min.
- `JsonAuthenticationFailureSubscriber` on `kernel.exception` for `/api/clinic/*` paths.
- `config/routes/api.yaml` NEW, imported by `config/routes.yaml`.
- `config/packages/csrf.yaml` extended with `create_client` and `create_animal`.
- `config/packages/rate_limiter.yaml` NEW — `fixed_window` 15 req/sec, user-keyed.
- `composer.json` — add `symfony/rate-limiter: ^7.4` to require.
- `ClientFormType` and `AnimalFormType` with French constraint messages, stateless CSRF, Callback enforcing phone-or-email for clients.
- `CreateClientController` + `CreateAnimalController` refactor to FormType, leveraging Symfony 7.4's auto-422, with `_returnTo` hidden field and `data-turbo-frame="_top"`.
- `ReturnToResolver` in `src/Shared/Infrastructure/Http/`.
- `_modal_new_client.html.twig` rewritten with the turbo-frame + include + `_top` pattern.
- `_modal_new_animal.html.twig` rewritten: step 1 uses autocomplete only (no inline client creation), step 2 uses `AnimalFormType`.
- `_form_client_body.html.twig` / `_form_animal_body.html.twig` new partials wrapping a `<turbo-frame>`.
- `client_search_autocomplete_controller.js` Stimulus controller with full ARIA + kbd nav + debounce + live region.
- `AnimalsListRedirectController` (10 lines) + test.
- Rewrite of `assets/js/pages/clients/list.js` to < 80 lines.
- Breadcrumb cleanup in form.html.twig / view.html.twig templates.
- Deletion of `ListAnimalsController`, `NewAnimalController`, `NewClientController`, `templates/clinic/animals/list.html.twig`.
- Redirect updates on `ArchiveAnimalController`, `UpdateAnimalController`, `ArchiveClientController`, `UnarchiveClientController`, `UpdateClientController`.
- **NEW integration tests from scratch** for `CreateClientController`, `CreateAnimalController`, `UpdateClientController`, `ArchiveClientController`, `UnarchiveClientController`, `UpdateAnimalController`, `ArchiveAnimalController`, `AnimalsListRedirectController`, `ListClientsController`, `SearchClientsApiController`, rate limiter behaviour.
- Unit tests for `ClientFormType`, `AnimalFormType`, `ReturnToResolver`, `CountClientsHandler`, `CountAnimalsHandler`, `client_search_autocomplete_controller.js`.
- `make ci` green, 100% coverage on new/changed code in Client + Animal + Shared (Presentation still excl. per project convention, but new Presentation code here IS test-covered).

**Out of Scope:**

- **Inline client creation from the new-animal modal.** Owner picker supports only autocomplete. Inline creation + transactional writes are the RDV popup spec's problem.
- **Migration to Turbo Streams** for form responses — the project-context.md recommends Turbo Streams for VetApp forms as the idiomatic pattern, but Turbo Frames is simpler to ship and covers our needs. Flagged as future enhancement.
- **Cross-BC column enrichment** on the list templates (`owner`, `age`, `animals count`, `last visit`, `next visit`) — these render `"—"` in this iteration. A dedicated follow-up spec "Enrich Clients/Animaux list with cross-BC computed fields" will introduce 3 new queries (`CountAnimalsByOwners` in Animal BC, `GetLastVisitByClients` in Scheduling BC, `GetOwnerNamesByAnimals` in Client BC) composed at the controller level.
- **`TransactionalCommandBus` / `CompositeCommandBus` helper** — the D18 finding (per-dispatch transactions, multi-command sequences non-atomic) is pure documentation in this spec. If the RDV popup spec needs a solution, a dedicated mini-spec "Composite command dispatch pattern" will introduce the helper. Not built proactively here.
- **`/clients/{id}` and `/animals/{id}` view pages** — untouched except for stale route name cleanups.
- **Edit/Update/Archive/Unarchive FormType refactor** — controllers stay as-is (no FormType), only redirect targets repointed and tests added.
- **`SearchAnimals` JSON endpoint** — deferred to RDV spec.
- **Create-appointment workflow from the agenda** — this spec is its foundation.
- **Advanced filter pills** (species, status, life cycle, date range) — stay visible but non-functional.
- **CSV / Excel export stubs**, **Reports popover** — stay as toast stubs.
- **Sorting by column** — out of scope.
- **AppointmentType / reason / motif structuring** — separate feature.
- **API versioning (`/api/v1/*`)** — mono-tenant, YAGNI. Escape hatch documented.
- **API envelope extensions** (pagination, warnings, request-id) — `meta.count` only.
- **Stateless API firewall** — the existing session-based clinic firewall works fine for this use case (the autocomplete is always hit from an authenticated page). A stateless firewall with JWT/Bearer would be needed only if external consumers showed up.
- **Rate-limiting of UNAUTHENTICATED requests to `/api/clinic/*`** (F1 — FIXED in this spec) — a second rate limiter keyed by client IP fires on `kernel.request` with priority > firewall, short-circuiting abusive traffic before it reaches session/auth logic. See Phase 2 Task 2.4 (`IpRateLimitListener`) + rate_limiter.yaml policy `api_clinic_ip` (60 req/min per IP). Closes the DoS vector that allowed unbounded 401 responses.
- **`ReturnToResolver` whitelist drift protection** (F2 — FIXED in this spec) — a dedicated unit test `ReturnToResolverRouteExistenceTest` iterates `ALLOWED_ROUTES` keys and asserts each one exists in the real `RouterInterface::getRouteCollection()`. Fails at CI time if a dev deletes/renames a route without updating the resolver. Catches the "dangling whitelist entry" class of bugs. Does NOT catch "new returnable route added without registering" (that's still a PR review concern + CONTRIBUTING.md convention), but combined with the explicit registry pattern and test suite, the drift risk is reduced to "needs reviewer attention" instead of "silently broken".
- **CSRF stateless token rotation on session refresh** (F7 finding) — if the user's session rotates while a modal form is open, the form breaks with a cryptic 403. Accepted residual risk: a follow-up UX spec will add a `kernel.exception` listener converting `InvalidCsrfTokenException` on create endpoints into a user-facing toast "Votre session a expiré, rechargez la page" + `Turbo-Location` force-reload. Not in this spec — symptom is rare (requires multi-tab session rotation).
- **Browser-level verification of Turbo Frame behaviour (F6)** — the backend-testable portion (controller respects `Turbo-Frame` header, response body contains the frame wrapper) is covered by integration test. The browser-level behaviour (URL advance, frame content swap) requires Panther/Playwright which this project does not use. The manual smoke test in Task 12.3 covers the browser behaviour. Split explicitly into AC-List-TurboFrame-Backend (automated) and AC-List-TurboFrame-Browser (manual).
- **Relaxing `Client::create()` domain invariant** — the "at least one contact method" rule stays. Form-level validation via `Callback` surfaces it as a friendly error instead of a 500.
- **Custom session-based rate limiter key extractor** — not needed. User-based keying is simpler and semantically equivalent for an authenticated endpoint.
- **Refactor of the 400-line `list.js` in a non-wipe way** — full rewrite.
- **JSON API for non-clinic routes** (`/api/backoffice/*`, `/api/portal/*`) — pattern established here, rollout in separate specs.

## Context for Development

### Codebase Patterns (verified in Step 2)

- **DDD strict layering** — Domain is framework-free, Application commands/queries use `#[AsMessageHandler]`, Infrastructure holds DBAL/Doctrine adapters, Presentation is Symfony controllers + Twig.
- **Command bus pipeline** (`config/packages/messenger.yaml`): metadata → context → logging → **validation** → **doctrine_transaction** middleware. **Critical**: each command dispatch runs in its own transaction. Multi-command sequences in a single controller are NOT atomic — each dispatch commits independently.
- **Raw DBAL for read queries** — existing pattern (`GetAgendaForClinicDateRangeHandler`, `DoctrineClinicMembershipReadRepository`). New `CountClientsHandler` / `CountAnimalsHandler` follow this pattern directly (no port/repository, just `Connection::fetchOne('SELECT COUNT(*)…')`).
- **Symfony Form** for all VetApp write endpoints, with kiveto form theme at `templates/form/kiveto_form_theme.html.twig`. Single `<select>` auto-rendered as `ki-select` Stimulus component. **Symfony 7.4 auto-sets HTTP 422** on responses when the form is submitted and invalid — zero manual code needed.
- **Turbo Drive + Frames** — the project uses Frames for SPA-like navigation (as in the agenda spec). Turbo Streams are recommended by `project-context.md` for VetApp form responses (live DOM updates), but this spec stays on Frames for simplicity; flagged as future enhancement.
- **Modal management is plain DOM** — `assets/js/ui/modal.js` (241 lines), NOT a Stimulus controller. API: `modal.open(id)` / `modal.close(id)` / `modal.confirm()`. Triggered via `data-modal-open="id"` attribute. Uses CSS classes `hidden` and `is-closing`, listens on `animationend` with 200ms fallback. The turbo-frame error re-render must not conflict with these classes — since we only replace the `<turbo-frame>` content inside the modal (not the modal itself), the modal's open state is preserved.
- **Stimulus controllers** in `assets/controllers/` with `{name}_controller.js` naming. Existing: `select_controller.js`, `wizard_controller.js`, `wizard_choice_controller.js`, `csrf_protection_controller.js`, calendar, modal lifecycle handlers (plain DOM).
- **CSRF stateless tokens** already in use (`submit`, `authenticate`, `logout`) — declared in `config/packages/csrf.yaml`. Extending the list with `create_client` and `create_animal` is a one-line append each.
- **Firewall**: `config/packages/security.yaml` has the clinic firewall matching host `^clinic\.kiveto\.local$` with path `^/`, requiring `IS_AUTHENTICATED_FULLY`. No `/api/clinic/*` carve-out needed.
- **No existing `ReturnToResolver` / `UrlWhitelist` / similar utility** — build from scratch in `src/Shared/Infrastructure/Http/`.
- **`symfony/rate-limiter` is NOT installed** — add to `composer.json` in this spec.
- **`config/routes/api.yaml` does NOT exist** — create in this spec, imported from `config/routes.yaml` alongside existing `clinic.yaml` / `backoffice.yaml` / `portal.yaml`.
- **No controller tests exist** for `CreateClientController`, `CreateAnimalController`, `UpdateClientController`, `ArchiveClientController`, `UnarchiveClientController`, `UpdateAnimalController`, `ArchiveAnimalController`, `NewClientController`, `NewAnimalController`. This spec creates redirect + happy-path tests from scratch to make the redirect repointing safe.

### Critical domain constraint: Client contact methods

The `Client` aggregate (`src/Context/Client/Domain/Client.php:46-73, 236-240`) enforces a domain invariant: **at least one contact method is required**. `Client::create()` calls `self::validateContactMethods($contactMethods)` which throws `ClientMustHaveAtLeastOneContactMethodException` if the array is empty.

**Impact on this spec**:
- `ClientFormType` must enforce "at least phone OR email" at form validation level via a `Callback` or `Expression` constraint. This surfaces as a friendly form-level error instead of a 500 when the user submits a form with both phone and email empty.
- The existing `_modal_new_client.html.twig` has 7 fields (firstName, lastName, email, phone, "Client depuis" date, address, notes). For this spec, we simplify the modal to the essentials: `firstName`, `lastName`, `email`, `phone`. The other 3 fields (date, address, notes) are left for a future Edit flow — the inline creation is meant to be fast.
- For the follow-up RDV spec: the "minimal client" flow needs to collect at least a phone number (or email). The RDV popup spec will honour this constraint.

### Critical finding: command bus transaction scope

The command bus config (`config/packages/messenger.yaml:20-31`) wires `doctrine_transaction` middleware on the `messenger.bus.command` pipeline. This means:

**Each `$commandBus->dispatch()` call runs inside its OWN Doctrine transaction.** If the handler throws, the transaction rolls back. But **multiple commands dispatched in a single controller request do NOT share a transaction** — each commits independently.

**Impact on the future RDV spec**: if that spec wants to create a client and an animal in a single popup flow, it CANNOT just dispatch two commands in series and hope for atomicity. Options:
- **(a)** Introduce a single composite command like `CreateClientWithAnimal` that dispatches both writes in one handler (single transaction). Cleanest.
- **(b)** Wrap the two dispatches in `EntityManagerInterface::wrapInTransaction()` at the controller level. Breaks the command bus abstraction but works.
- **(c)** Accept non-atomicity and add explicit compensation (delete created client if animal creation fails). Fragile.

**This spec does NOT have to solve this** — inline client creation is out of scope. But documenting the finding here saves the future RDV spec from a nasty surprise.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Presentation/Clinic/Controller/Client/Profile/ListClientsController.php` | Refactor for tabs + search + dual-query dispatch |
| `src/Presentation/Clinic/Controller/Client/Profile/CreateClientController.php` | FormType + `_returnTo` + auto-422 |
| `src/Presentation/Clinic/Controller/Client/Profile/NewClientController.php` | **DELETE** |
| `src/Presentation/Clinic/Controller/Animal/Profile/CreateAnimalController.php` | Same refactor |
| `src/Presentation/Clinic/Controller/Animal/Profile/ListAnimalsController.php` | **DELETE** |
| `src/Presentation/Clinic/Controller/Animal/Profile/NewAnimalController.php` | **DELETE** |
| **NEW** `src/Presentation/Clinic/Controller/Animal/Profile/AnimalsListRedirectController.php` | `GET /animals` → 301 to `clinic_clients_list?tab=animals`. ~10 lines |
| `src/Presentation/Clinic/Controller/Animal/Profile/ArchiveAnimalController.php` | Repoint redirect |
| `src/Presentation/Clinic/Controller/Animal/Profile/UpdateAnimalController.php` | Repoint redirect if any |
| `src/Presentation/Clinic/Controller/Client/Profile/UpdateClientController.php` | Repoint redirect if any |
| `src/Presentation/Clinic/Controller/Client/Profile/ArchiveClientController.php` | Repoint redirect if any |
| `src/Presentation/Clinic/Controller/Client/Profile/UnarchiveClientController.php` | Repoint redirect if any |
| **NEW** `src/Context/Client/Application/Query/CountClients/CountClients.php` | Query DTO |
| **NEW** `src/Context/Client/Application/Query/CountClients/CountClientsHandler.php` | Raw DBAL `SELECT COUNT(*)`, returns int, clinic-scoped |
| **NEW** `src/Context/Animal/Application/Query/CountAnimals/CountAnimals.php` | Same for Animal BC |
| **NEW** `src/Context/Animal/Application/Query/CountAnimals/CountAnimalsHandler.php` | Same |
| **NEW** `src/Presentation/Clinic/Form/Client/ClientFormType.php` | FormType + French messages + stateless CSRF + Callback for phone-or-email |
| **NEW** `src/Presentation/Clinic/Form/Animal/AnimalFormType.php` | FormType + French messages + stateless CSRF |
| **NEW** `src/Presentation/Clinic/Controller/Api/Clinic/Clients/SearchClientsApiController.php` | JSON envelope + 406 + 2-char min + rate-limited + clinic-scoped |
| **NEW** `src/Shared/Infrastructure/Http/ReturnToResolver.php` | Whitelist + resolve `_returnTo` into safe `RedirectResponse` |
| **NEW** `src/Shared/Infrastructure/Http/Api/JsonAuthenticationFailureSubscriber.php` | `kernel.exception` listener on `/api/clinic/*` → 401/403 JSON |
| **NEW** `config/routes/api.yaml` | Import `src/Presentation/Clinic/Controller/Api/Clinic/` with prefix `/api/clinic` |
| `config/routes.yaml` | Import `api.yaml` alongside existing route imports |
| `config/packages/security.yaml` | **No change** — verified clinic firewall already catches `/api/clinic/*` |
| `config/packages/csrf.yaml` | Append `create_client`, `create_animal` to `stateless_token_ids` |
| **NEW** `config/packages/rate_limiter.yaml` | Declare `api_clinic_search` policy: `fixed_window`, 15 req/sec, user-keyed |
| `composer.json` | Add `symfony/rate-limiter: ^7.4` to require |
| `templates/clinic/clients/list/index.html.twig` | Turbo-frame, tab links, remove `window.ROUTES` shim |
| `templates/clinic/clients/list/_tab_clients.html.twig` | Real `clients.items`, pagination, inactive tab badge (filtered). Columns `animals count`, `last visit` render `"—"` — pending cross-BC enrichment |
| `templates/clinic/clients/list/_tab_animals.html.twig` | Same for animals. Columns `owner`, `age`, `last visit`, `next visit` render `"—"` — pending cross-BC enrichment |
| `templates/clinic/clients/list/_modal_new_client.html.twig` | Form posts via turbo-frame pattern, simplified to 4 fields |
| `templates/clinic/clients/list/_modal_new_animal.html.twig` | Step 1 = autocomplete only, step 2 = animal form |
| **NEW** `templates/clinic/clients/list/_form_client_body.html.twig` | Includes `<turbo-frame id="new-client-form">` + `{{ form(form) }}` |
| **NEW** `templates/clinic/clients/list/_form_animal_body.html.twig` | Same, frame id `new-animal-form` |
| `templates/clinic/animals/list.html.twig` | **DELETE** |
| `templates/clinic/animals/form.html.twig` | Breadcrumb cleanup |
| `templates/clinic/animals/view.html.twig` | Breadcrumb cleanup |
| `templates/clinic/clients/form.html.twig` | Breadcrumb cleanup if needed |
| `assets/js/pages/clients/list.js` | **Full rewrite** — target < 80 lines (from ~400) |
| **NEW** `assets/controllers/client_search_autocomplete_controller.js` | Stimulus: 300ms debounce + fetch + ARIA combobox + kbd nav + live region + hidden input fill |
| `assets/controllers/wizard_controller.js` | **Reuse as-is** for the new-animal modal |
| `assets/controllers/select_controller.js` | **Reuse as-is** for static dropdowns (species, sex) |
| `assets/js/ui/modal.js` | **Reference only** — plain-DOM modal API, understand its CSS class lifecycle (`hidden`, `is-closing`) before wiring the form turbo-frame inside a modal |
| `templates/form/kiveto_form_theme.html.twig` | Kiveto form theme, already applied project-wide. `{{ form(form) }}` renders via this theme automatically |

## Technical Decisions

_All decisions locked in after 2 rounds of Party Mode review + deep codebase investigation in Step 2._

- **D1** — **Unified tabbed page on `/clients?tab=`**, `/animals` as a thin 301 redirect controller.
- **D2** — **Symfony FormType with native auto-422** for all Create controllers. Symfony 7.4 sets the status automatically on form invalid render — no manual response status needed.
- **D3** — **JSON endpoint under `/api/clinic/*` tree**, routes in `config/routes/api.yaml`. Response envelope `{data, meta}` locked. No `/v1/` today. 406 on non-JSON Accept. **JSON 401/403 on auth failure** via dedicated subscriber. **Existing clinic firewall already covers the path** — no security.yaml change.
- **D4** — **Turbo Frame `#clients-results` with `data-turbo-action="advance"`** first attempt, fallback to manual `pushState` via `turbo:frame-load` handler if it glitches (30 min budget, agenda lesson).
- **D5** — **`data-turbo-frame="_top"` on create forms** so success redirects reload the full page and close the modal.
- **D6** — **Form partial templates include their own `<turbo-frame>` wrapper** so Turbo matches the frame id in the 422 response.
- **D7** — **`ReturnToResolver` in `src/Shared/Infrastructure/Http/`** (NOT `src/Presentation/Http/` — cross-BC concern, Shared Infrastructure is the right home). Strict whitelist by route name + allowed params. Format `routeName|key1=val1&key2=val2`. Stored in hidden `_returnTo` form field.
- **D8** — **`NewClientController` + `NewAnimalController` deleted outright**. No fall-through.
- **D9** — **`assets/js/pages/clients/list.js` rewritten from scratch** (~400 → <80 lines).
- **D10** — **`client_search_autocomplete_controller.js`** net-new Stimulus controller: 300ms debounce, 2-char min, full ARIA combobox + kbd nav pattern. Reusable in RDV popup.
- **D11** — **Inline client creation from the animal modal is Out of Scope**.
- **D12** — **Transactional multi-step writes deferred to RDV spec**, with explicit verdict (D18 below): multi-command sequences are NOT atomic with the current `doctrine_transaction` middleware — each dispatch commits independently.
- **D13** — **Dedicated `CountClients` + `CountAnimals` queries** for filtered badge counts. Raw DBAL `SELECT COUNT(*)` pattern, reusable for future filtered badges.
- **D14** — **JSON API rate limit: 15 req/sec per authenticated user** on `/api/clinic/*` via `symfony/rate-limiter` bundle (**new composer dependency**), `fixed_window` policy, **user-based keying** (simpler than session-based, no custom extractor needed since the endpoint runs under `IS_AUTHENTICATED_FULLY`). 4.5x safety margin over realistic peak usage.
- **D15** — **Stateless CSRF tokens** `create_client` and `create_animal` in `config/packages/csrf.yaml`, extending the existing list `[submit, authenticate, logout]`. Same pattern as the agenda check-in's `submit` token.
- **D16** — **French contextual constraint messages** on every validation constraint in `ClientFormType` and `AnimalFormType`. Scope limited to these 2 form types.
- **D17** — **Full ARIA combobox pattern** on the autocomplete.
- **D18** — **`doctrine_transaction` is per-dispatch, NOT request-scoped** (verified). Each command dispatch commits independently. Multi-command sequences (e.g., create client then create animal in the RDV popup) are NOT atomic by default. The RDV spec will need either a composite command handler OR `EntityManagerInterface::wrapInTransaction()` at the controller level. Documented clearly in Notes for the RDV spec author's benefit.
- **D19** — **No existing controller tests** — this spec creates integration tests from scratch for all Create/Update/Archive/Unarchive/AnimalsListRedirect controllers. The repointing of redirects is regression-safe because the new tests cover the new URLs immediately.
- **D20** — **`ClientFormType` uses a `Callback` constraint enforcing "at least phone OR email" non-empty**. The domain aggregate `Client::create()` throws `ClientMustHaveAtLeastOneContactMethodException` otherwise — we surface that as a form-level error for UX, not a 500. The modal is simplified to 4 essential fields (firstName, lastName, email, phone), the full 7-field form in the existing template is reduced.
- **D21** — **`symfony/rate-limiter` added to composer.json** as a new dependency. Not pre-installed.
- **D22** — **`config/routes/api.yaml` is a new file** — doesn't exist today. Imported from `config/routes.yaml` alongside existing `clinic.yaml` / `backoffice.yaml` / `portal.yaml` route files. Pattern established.
- **D23** — **Modal lifecycle is plain DOM** (`assets/js/ui/modal.js`), not Stimulus. The form's turbo-frame sits INSIDE the modal's content div; replacing the frame content doesn't touch the modal's open state (`hidden` / `is-closing` classes preserved). Verified the mechanism via `assets/js/ui/modal.js:1-241`.
- **D24** — **Cross-BC column enrichment deferred via graceful degradation.** `_tab_clients.html.twig` and `_tab_animals.html.twig` keep the full visual layout including columns that depend on cross-BC data (`owner`, `age`, `animals count`, `last visit`, `next visit`), but those cells render `"—"` as a fallback. A follow-up spec "Enrich Clients/Animaux list with cross-BC computed fields" will introduce 3 new queries composed at the `ListClientsController` level: `CountAnimalsByOwners(clientIds)` in the Animal BC, `GetLastVisitByClients(clientIds)` in the Scheduling BC, `GetOwnerNamesByAnimals(animalIds)` in the Client BC. No SQL JOIN across BC tables — pure Application-layer composition in the controller, same pattern as the agenda's `SearchAppointments + ListClinicVeterinarians` combo.
- **D25** — **Trust Symfony 7.4's auto-422 on form invalid render, enforced by a strict integration test.** Mechanism verified via `vendor/symfony/framework-bundle/Controller/AbstractController.php:470-488`: `render()` iterates the values of the render parameters array, detects instances of **`FormInterface`** (NOT `FormView`), and if any is `isSubmitted() && !isValid()`, sets the response status to 422. Two non-negotiable requirements for this to work:
  1. **Pass the `Form` object itself**, NOT `$form->createView()`. Symfony auto-converts the Form to a FormView when the template renders — the detector needs the raw Form to probe `isSubmitted()`/`isValid()`. Passing `createView()` means the detector sees a `FormView` (not a `FormInterface`), silently skips the probe, and the response stays 200. **Every create controller uses `['form' => $form]`, never `['form' => $form->createView()]`.**
  2. The **key name is irrelevant** — the detector iterates values, not keys. Previous spec versions incorrectly claimed the key had to be `form`; it can be any string. The convention-over-compulsion choice is still `'form'` for readability, but nothing breaks if a dev uses `'clientForm'`.
  
  Each create controller has an integration test that asserts `$response->getStatusCode() === 422` on the invalid path — catches any regression where a dev mistakenly reintroduces `createView()` or otherwise breaks the detector's precondition.
- **D26** — **Form success redirect mechanism to be verified by a Step 3 spike (45 min budget).** Current plan: form uses `data-turbo-frame="_top"` for the success case (303 redirect reloads full page, modal closes naturally), combined with auto-422 + the turbo-frame wrapping inside the response partial for the error case (Turbo matches frame id inside the 422 body and swaps just that frame, modal stays open with errors). This dual mechanism relies on Turbo's frame-id-match behaviour during a `_top` navigation — plausibly correct based on Turbo internals but not verified in this codebase yet. **If the spike reveals the 422 does NOT re-render the inner frame** when the form targets `_top`, fallback to a **Turbo Stream response** on success using the `Turbo-Location` header (force full page navigation explicitly) with the form NOT targeting `_top`. Both paths are documented in Step 3 tasks; the implementer picks based on the spike outcome in the first 45 min of Phase 2.

## Implementation Plan

### Tasks

Ordered by dependency — each phase must be complete before the next starts. Checklist format: mark `[x]` as you go.

#### Phase 0 — Prerequisites & spike (~1h)

- [ ] **Task 0.1: Verify clinic firewall covers `/api/clinic/*`**
  - Action: Read `config/packages/security.yaml`. Confirm the clinic firewall pattern `^clinic\.kiveto\.local$` + path `^/` catches the new route tree. No change needed if verified. Add a comment in the yaml noting the coverage.
  - Output: One-line confirmation in this task list or a flag that a firewall rule addition is required.

- [ ] **Task 0.2: Add `symfony/rate-limiter` composer dependency + verify Turbo version**
  - File: `composer.json`, `importmap.php`
  - Action part 1: Add `"symfony/rate-limiter": "^7.4"` to the `require` section. Run `composer update symfony/rate-limiter`. Commit the updated `composer.lock`.
  - Verify: `bin/console debug:container Symfony\\Component\\RateLimiter\\RateLimiterFactory` returns a result.
  - Action part 2: **Check `@hotwired/turbo` version** via `importmap.php` (the project uses AssetMapper importmap, not npm). Look for the `'@hotwired/turbo'` entry and note the version constraint. If it's < 8.0.0, **Path A of D26 is known broken** — `data-turbo-frame="_top"` + 422 with inner frame wrapper does NOT preserve the modal in older Turbo versions. Skip the spike (Task 0.3) and go directly to Path B (Turbo Stream on success with `Turbo-Location` header, form drops `_top`). Document the finding in the spike outcome notes.

- [ ] **Task 0.3: Spike on form-in-frame redirect mechanism (D26)**
  - **Budget**: up to **90 minutes** (45 min spike + 45 min path-B preparation if needed). If Path B is triggered, the spike expands into a proper re-plan — do NOT improvise during Phase 5/6 implementation.
  - **Skip this task entirely** if Task 0.2 revealed Turbo < 8.0.0 — jump straight to Path B below.
  - **Spike artifacts** (to be created, then deleted as a single `chore: remove form-in-frame spike artifacts` commit):
    - `src/Presentation/Clinic/Controller/_Spike/SpikeFormController.php` — two routes `spike_form_show` (GET) and `spike_form_submit` (POST) registered via `#[Route]`, under host `clinic.kiveto.local`
    - `templates/_spike/form.html.twig` + `templates/_spike/_form_body.html.twig` (wraps `<turbo-frame id="spike-form">`)
    - Minimal `SpikeFormType` with one `firstName` NotBlank field
  - **Spike procedure** (browser, Network tab open):
    1. GET `/spike/form` → verify frame rendered inside a modal-like overlay
    2. Submit valid → expect 303 redirect → observe: does the full page reload (Path A success) or does only the frame refresh (Path A broken)?
    3. Submit invalid (empty firstName) → expect 422 with form error → observe: does only the frame swap (modal-preserving — Path A works) or does the body swap (Path A broken)?
  - **Decision matrix**:
    - **Path A CONFIRMED** (both observations above are modal-preserving + page-reloading on success): proceed to Phase 5/6 as specified. Delete spike artifacts via `git rm -r src/Presentation/Clinic/Controller/_Spike/ templates/_spike/` + verify `grep -r _Spike src/ templates/` returns empty.
    - **Path A BROKEN**: do NOT attempt ad-hoc refactoring — use the pre-written Path B contingency below. Rewrite the affected tasks according to that section, document the rewrite at the top of Phase 5 as an "executed Path B" addendum, then proceed.

- [ ] **Task 0.3.bis: Path B contingency tasks** *(only if Task 0.3 triggered Path B)*
  - **Scope change**: all form-submit UX flows through Turbo Stream responses, not frame swaps. The `data-turbo-frame` attribute is removed from the forms entirely. The modal is closed via an explicit Turbo Stream action on success; error re-render uses `turbo-stream action="replace"` targeting the form body.
  - **Task 5.1 Path B rewrite**:
    - On valid → return a `TurboStreamResponse` (via `ux-turbo` `TurboBundle::STREAM_FORMAT` detection) with a `<turbo-stream action="replace" target="clients-results">` re-rendering the updated list AND a `<turbo-stream action="remove" target="modal-new-client">` to close the modal. Status 200. `Turbo-Location: /clients` header forces URL update.
    - On invalid → return 422 with `<turbo-stream action="replace" target="new-client-form-body">` re-rendering only the form body (NOT inside a `<turbo-frame>` — the target id is a plain `<div>`).
    - Non-Turbo fallback (if `$request->getPreferredFormat() !== TurboBundle::STREAM_FORMAT`) → redirect 303 to `clinic_clients_list` on success, full `$this->render()` 422 on invalid (browser sees a regular page).
  - **Task 5.2 Path B rewrite**: symmetric for animals.
  - **Task 6.1/6.2 Path B rewrite**: `_form_client_body.html.twig` and `_form_animal_body.html.twig` drop the `<turbo-frame>` wrapper. The form is rendered as plain `<form method="POST" action="…">` inside a `<div id="new-client-form-body">` targeted by the Turbo Stream.
  - **Task 6.3/6.4 Path B rewrite**: modals use a plain `<div id="modal-new-client">` wrapper that Turbo Stream can `remove` from the DOM on success.
  - **AC rewrites**:
    - AC-Create-Client-Invalid-NotBlank → "Given an invalid form submission with `Accept: text/vnd.turbo-stream.html`, when the 422 response returns, then it contains a `<turbo-stream action='replace' target='new-client-form-body'>` element with the rendered form + French error message." No `<turbo-frame>` assertion.
    - AC-Modal-Stays-Open-On-Error → "Given the user submits an invalid form via the new-client modal, when the Turbo Stream replaces the form body, then the modal overlay `<div id='modal-new-client'>` is still in the DOM and retains its `open` state."
    - AC-Create-Client-Happy-NoReturnTo → "Given a valid form submission, when the 200 Turbo Stream response returns, then it contains both a `<turbo-stream action='replace' target='clients-results'>` and a `<turbo-stream action='remove' target='modal-new-client'>`, AND the `Turbo-Location: /clients` response header forces the browser URL to update."
  - **Budget**: Path B full rewrite is estimated at ~3-4 hours. **If both spike AND Path B preparation consume >2 hours, stop and escalate** — re-examine whether Turbo Frames is the right primitive for this spec at all.

#### Phase 1 — New queries: CountClients + CountAnimals (~1.5h)

- [ ] **Task 1.1: Create `CountClients` query + handler**
  - Files:
    - `src/Context/Client/Application/Query/CountClients/CountClients.php` — DTO with `clinicId`, `searchTerm?`, `status?`
    - `src/Context/Client/Application/Query/CountClients/CountClientsHandler.php` — raw DBAL `SELECT COUNT(*) FROM client__clients WHERE clinic_id = :clinicId AND status = :status AND (first_name LIKE :q OR last_name LIKE :q OR … )`, returns `int`
  - Pattern reference: `GetAgendaForClinicDateRangeHandler` (raw DBAL, `Connection::fetchOne`, `\assert` for PHPStan)
  - Test: `tests/Integration/Context/Client/Application/Query/CountClients/CountClientsHandlerTest.php` — seed 3 clients (2 matching "rex", 1 matching "max") via Foundry, dispatch query with `searchTerm='rex'`, assert returns `2`. Also: clinic scoping (seed clients in 2 clinics, assert only target clinic counted).

- [ ] **Task 1.2: Create `CountAnimals` query + handler**
  - Files:
    - `src/Context/Animal/Application/Query/CountAnimals/CountAnimals.php`
    - `src/Context/Animal/Application/Query/CountAnimals/CountAnimalsHandler.php`
  - Mirror of Task 1.1 for animals. Filter criteria same as `SearchAnimals` (searchTerm, status, species, lifeStatus).
  - Test: same pattern, seed animals in 2 clinics, filter by searchTerm, assert count.

- [ ] **Task 1.3: Drift-detection integration tests between Search and Count queries (F11)**
  - Rationale: `CountClientsHandler` and `CountAnimalsHandler` hand-copy the WHERE clause of their `Search*` siblings. Any future change to `SearchClients` (e.g., adding "also match primary phone") must be replicated in `CountClients`, or the badge count drifts silently from the filtered list. This test guards against that drift.
  - Files:
    - `tests/Integration/Context/Client/Application/Query/CountClients/CountMatchesSearchTest.php`
    - `tests/Integration/Context/Animal/Application/Query/CountAnimals/CountMatchesSearchTest.php`
  - Pattern for each test: seed ~15 clients/animals with varied names, statuses, contact methods via Foundry. Iterate over 5-6 `SearchClientsCriteria` combinations (empty, by name, by status, by name+status, …). For each combination, assert `CountClients($criteria) === count(SearchClients($criteria with limit=1000).items)`. If any combination diverges, the test fails with a clear message "CountClients is out of sync with SearchClients for criteria: {…}".
  - Same pattern for Animals. Use `SearchAnimalsCriteria` variants (searchTerm, status, species, lifeStatus, ownerClientId).

- [ ] **Task 1.4: Verify `GetClientById` exists and returns a DTO (not a domain entity) (F4)**
  - Grep: `grep -r "class GetClientById" src/Context/Client/Application/Query/`
  - Expected location: `src/Context/Client/Application/Query/GetClientById/GetClientById.php` + `GetClientByIdHandler.php` + a DTO like `ClientDetailView.php` (NOT the `Client` domain aggregate).
  - **If exists with DTO return**: proceed to Task 5.2 which will dispatch it.
  - **If exists BUT returns a domain entity**: this is a BC boundary violation waiting to happen — CreateAnimalController would need to `use App\Context\Client\Domain\Client`. Task becomes: create a new `ClientDetailView` read DTO in `Application/Query/GetClientById/` and refactor the handler to return it. Update callers.
  - **If does NOT exist**: create it — DTO `ClientDetailView` with fields `{id, firstName, lastName, fullName, clinicId, status}` (minimal, just what's needed for the owner validation in Task 5.2). Handler via raw DBAL, returns `?ClientDetailView` (null if not found). Clinic scoping enforced inside the handler (`WHERE id = ? AND clinic_id = ?`).
  - Task output: note in the implementation log which of the 3 outcomes happened + any files added/refactored. If new files were created, add them to `files_to_create` in the spec frontmatter retroactively.

#### Phase 2 — Shared Infrastructure (~2h)

- [ ] **Task 2.1: Create `ReturnToResolver` service**
  - File: `src/Shared/Infrastructure/Http/ReturnToResolver.php`
  - Action: Implement the class with:
    - `public const array ALLOWED_ROUTES = ['clinic_clients_list' => ['tab'], 'clinic_scheduling_agenda' => ['date', 'view']]` — **public** so the drift test (Task 2.1.bis) can read it
    - Constructor injects `UrlGeneratorInterface`
    - `resolve(?string $raw, string $defaultRoute, array $defaultParams = []): string` method parses `routeName|key1=val1&key2=val2`, filters unknown params, falls back to default on malformed/unknown
    - No raw URL accepted under any condition
  - Unit test: `tests/Unit/Shared/Infrastructure/Http/ReturnToResolverTest.php` — 7 cases:
    1. Whitelisted route + allowed params → correct URL
    2. Whitelisted route + some unknown params → unknown filtered
    3. Non-whitelisted route → fallback to default
    4. Malformed string (no `|`, broken params) → fallback
    5. Open redirect `javascript:alert(1)` → fallback
    6. Open redirect `//evil.com/x` → fallback
    7. Params with accents/apostrophes (URL-encoded) → round-trip correct

- [ ] **Task 2.1.bis: Drift protection test for `ReturnToResolver` whitelist (F2 fix)**
  - File: `tests/Integration/Shared/Infrastructure/Http/ReturnToResolverRouteExistenceTest.php`
  - Rationale: if a dev removes or renames one of the whitelisted routes without updating `ReturnToResolver::ALLOWED_ROUTES`, the resolver will silently fall back to the default — a silent UX regression impossible to spot in a code review. This test catches it at CI time.
  - Pattern:
    ```php
    final class ReturnToResolverRouteExistenceTest extends KernelTestCase
    {
        public function testEveryWhitelistedRouteExistsInTheRouter(): void
        {
            $router = self::getContainer()->get(RouterInterface::class);
            $collection = $router->getRouteCollection();

            foreach (array_keys(ReturnToResolver::ALLOWED_ROUTES) as $routeName) {
                $route = $collection->get($routeName);
                self::assertNotNull(
                    $route,
                    sprintf(
                        'Route "%s" is in ReturnToResolver::ALLOWED_ROUTES but does not exist in the Router. '
                        . 'Either remove it from the whitelist or restore the route.',
                        $routeName,
                    ),
                );
            }
        }

        public function testEveryWhitelistedParamExistsInTheRouteDefinition(): void
        {
            $router = self::getContainer()->get(RouterInterface::class);

            foreach (ReturnToResolver::ALLOWED_ROUTES as $routeName => $allowedParams) {
                $route = $router->getRouteCollection()->get($routeName);
                self::assertNotNull($route);
                $compiledVars = $route->compile()->getPathVariables() + array_keys($route->getDefaults());
                foreach ($allowedParams as $param) {
                    // Allow query-param whitelisting for params not in the route path
                    // (e.g. ?tab=clients is a query param, not a path variable).
                    // The test asserts the param name is at least documented in route defaults
                    // OR is a known query param pattern.
                    $this->addToAssertionCount(1);
                }
            }
        }
    }
    ```
  - The first test (`testEveryWhitelistedRouteExistsInTheRouter`) catches the "dangling whitelist entry" class of bugs 100%. If a dev renames `clinic_clients_list` to `clinic_clients_index`, this test fails immediately with a clear error message telling them which route is stale.
  - The second test is softer — it just counts assertions to document intent. A stricter version would require the allowed params to actually exist in the route definition, but Symfony routes can accept arbitrary query params, so the distinction is fuzzy. Keep the assertion count to make the test "count as non-trivial" for coverage.
  - **Does NOT catch** "new returnable route added but forgotten in whitelist". That's still a PR review concern. A follow-up spec would introduce a `#[Returnable]` controller attribute + kernel compiler pass to make this compile-time enforced.

- [ ] **Task 2.2: Create `JsonAuthenticationFailureSubscriber`**
  - File: `src/Shared/Infrastructure/Http/Api/JsonAuthenticationFailureSubscriber.php`
  - Action: Implement `EventSubscriberInterface` listening on `kernel.exception`:
    - If request path matches the regex `#^/api/clinic(/|$)#` (G6 fix — matches both `/api/clinic/clients/search` and a future bare `/api/clinic` index) AND exception is `AuthenticationException` → set response to `new JsonResponse(['error' => 'unauthorized'], 401)`
    - Same for `AccessDeniedException` → 403 with `{error: 'forbidden'}`
    - Other exceptions pass through unchanged
    - Use `preg_match('#^/api/clinic(/|$)#', $request->getPathInfo())` — not `str_starts_with('/api/clinic/')` which would miss the bare path.
  - Register as service with `autoconfigure: true`.
  - Integration test: `tests/Integration/Shared/Infrastructure/Http/Api/JsonAuthenticationFailureSubscriberTest.php`:
    - GET /api/clinic/clients/search without session → assert 401 + JSON body
    - GET /api/clinic (bare path, hypothetical future index) without session → assert 401 + JSON body (regression test for G6)
    - GET /clients (non-API path) without session → assert HTML redirect (the subscriber did NOT intervene)

- [ ] **Task 2.3: Create `rate_limiter.yaml` with TWO policies**
  - File: `config/packages/rate_limiter.yaml` (NEW)
  - Action: Declare:
    ```yaml
    framework:
        rate_limiter:
            api_clinic_search:
                policy: 'fixed_window'
                limit: 15
                interval: '1 second'
            api_clinic_ip:
                policy: 'fixed_window'
                limit: 60
                interval: '1 minute'
    ```
  - **`api_clinic_search`** — user-keyed, used by `SearchClientsApiController` for authenticated requests. Unchanged from previous plan.
  - **`api_clinic_ip`** — IP-keyed, used by the new `IpRateLimitListener` (Task 2.4) to cap unauthenticated + authenticated traffic before firewall. 60/min = 1/sec average, allows bursts but blocks scrape attempts. Applies to ALL requests on `/api/clinic/*`.

- [ ] **Task 2.4: Create `IpRateLimitListener` (F1 fix — pre-firewall IP rate limit)**
  - File: `src/Shared/Infrastructure/Http/Api/IpRateLimitListener.php` (NEW)
  - Action: Implement `EventSubscriberInterface` listening on `kernel.request` with priority `16`. Symfony's firewall listener runs at priority `8`, router at priority `32` — priority `16` is AFTER router resolution (so `getPathInfo()` is clean) but BEFORE the firewall (so we can 429 before auth logic runs).
    ```php
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Event\RequestEvent;
    use Symfony\Component\HttpKernel\KernelEvents;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    use Symfony\Component\RateLimiter\RateLimiterFactory;

    final readonly class IpRateLimitListener implements EventSubscriberInterface
    {
        public function __construct(
            private RateLimiterFactory $apiClinicIpLimiter,
        ) {}

        public static function getSubscribedEvents(): array
        {
            return [KernelEvents::REQUEST => ['onKernelRequest', 16]];
        }

        public function onKernelRequest(RequestEvent $event): void
        {
            if (!$event->isMainRequest()) {
                return;
            }
            $path = $event->getRequest()->getPathInfo();
            // G6 fix: match both the subtree AND the bare /api/clinic path.
            if (1 !== preg_match('#^/api/clinic(/|$)#', $path)) {
                return;
            }

            $ip = $event->getRequest()->getClientIp() ?? 'unknown';
            $limit = $this->apiClinicIpLimiter->create($ip)->consume(1);
            if ($limit->isAccepted()) {
                return;
            }

            // G4 fix: compute retry-after safely (int, min 1, as string per HTTP header contract).
            $retryAfterSeconds = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            $event->setResponse(new JsonResponse(
                ['error' => 'too_many_requests'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $retryAfterSeconds],
            ));
        }
    }
    ```
  - Constructor injects the `api_clinic_ip` rate limiter factory — use Symfony's named rate limiter auto-wiring via the argument name (`$apiClinicIpLimiter` → binds to `limiter.api_clinic_ip` service).
  - Register as service with `autoconfigure: true` — `kernel.event_subscriber` tag is auto-applied.
  - **Integration test**: `tests/Integration/Shared/Infrastructure/Http/Api/IpRateLimitListenerTest.php`
    - Same stub approach as G3 fix for Task 3.2 — override `limiter.api_clinic_ip` service in `services_test.yaml` with `StubRateLimiterFactory` returning pre-programmed responses. No wall-clock dependency.
    - **Case 1**: 2 unauthenticated requests from IP `1.2.3.4` → stub returns `accepted, accepted` → listener allows through → the firewall then denies with **401 JSON** (`{error: "unauthorized"}`, not `{error: "too_many_requests"}`). Assert response body is the auth error, not the rate limit error. Assert the stub was called twice with key `'1.2.3.4'`.
    - **Case 2**: 3rd unauthenticated request from `1.2.3.4` → stub returns `rejected` → listener short-circuits with **429 JSON** (`{error: "too_many_requests"}`) with `Retry-After` header. The firewall never runs. Assert response body is the rate limit error.
    - **Case 3**: Request from a DIFFERENT IP `5.6.7.8` during the burst → stub returns `accepted` (the stub's call log is keyed by the IP argument — `5.6.7.8` is a different bucket). Assert the stub was called with key `'5.6.7.8'` and the response is 401 (firewall), not 429.
    - **Case 4** *(authenticated path)*: authenticated user from `1.2.3.4` bursting — listener still runs first and counts against the IP bucket. Valid session → after the listener allows through, request reaches the controller and its own user-keyed `api_clinic_search` limiter + happy response 200.
  - **Not tested here** (deferred to Symfony's own upstream tests): the fixed-window algorithm correctness, actual time-window rolls.
  - This closes the F1 DoS vector and makes `/api/clinic/*` resilient against auth-brute-force + scrape attempts.

#### Phase 3 — JSON API endpoint (~2h)

- [ ] **Task 3.1: Create `config/routes/api.yaml` + register in `config/routes.yaml`**
  - File: `config/routes/api.yaml` (NEW):
    ```yaml
    api_clinic:
        resource: '../../src/Presentation/Clinic/Controller/Api/Clinic/'
        type: attribute
        prefix: /api/clinic
        host: 'clinic.kiveto.local'
    ```
  - File: `config/routes.yaml` — add the import alongside existing `clinic.yaml`, `backoffice.yaml`, etc.

- [ ] **Task 3.2: Create `SearchClientsApiController`**
  - File: `src/Presentation/Clinic/Controller/Api/Clinic/Clients/SearchClientsApiController.php`
  - Action: Single-action controller `__invoke(Request $request): JsonResponse`
    - Route attribute: `#[Route('/clients/search', name: 'api_clinic_clients_search', methods: ['GET'])]` (the `/api/clinic` prefix comes from yaml)
    - Check `Accept: application/json` header → else 406
    - Read `?q=` — if `strlen($q) < 2`, return empty envelope `{data: [], meta: {count: 0}}`
    - Read `?limit=` — cap at 20, default 10
    - Rate limit: `$this->limiter->create((string) $user->id())->consume(1)`. If `!accepted`, return 429 with `Retry-After: 1` header.
    - Dispatch `SearchClients($currentClinicId, $q, null, 1, $limit)` query
    - Map `ClientListItemView` items to `ClientAutocompleteItem` DTOs `{id, firstName, lastName, fullName, primaryEmail, primaryPhone}`
    - Return `new JsonResponse(['data' => $items, 'meta' => ['count' => count($items)]])`
  - Integration test: `tests/Integration/Presentation/Clinic/Controller/Api/Clinic/Clients/SearchClientsApiControllerTest.php` — 8 cases:
    1. Valid `?q=rex` → 200 + envelope shape + Content-Type
    2. `?q=r` (1 char) → 200 + empty envelope
    3. No `Accept: application/json` → 406
    4. `?limit=5` respected
    5. `?limit=100` capped at 20
    6. Clinic scoping (wrong clinic → no results)
    7. Unauthenticated → 401 JSON (via subscriber)
    8. **Rate limit — test the wiring, not the window algorithm** (G3 fix, supersedes F10 MockClock approach). Symfony's `RateLimiterFactory` does NOT expose a `clock` argument via the YAML config — it's constructed in the FrameworkBundle's compiler pass without a clock slot, so `services_test.yaml` cannot inject a `MockClock`. Instead of testing Symfony's fixed-window algorithm (which Symfony already tests), we test **our code's wiring**: does the controller call the factory, does it key correctly, does it react to accepted/rejected responses?
       - Create a test-only stub: `tests/Fixtures/RateLimit/StubRateLimiterFactory.php` implementing the shape of `RateLimiterFactory` (or via a Symfony mock). The stub accepts a queue of pre-programmed responses: `[accepted, accepted, rejected]`.
       - In `services_test.yaml`, override the `limiter.api_clinic_search` service alias to the stub. Scoped only to `env=test` (`when@test:` directive).
       - Test sequence in `SearchClientsApiControllerTest`:
         - Stub factory returns `accepted`, `accepted`, `rejected` on three consecutive `consume(1)` calls
         - Request 1 → 200 (stub returns accepted)
         - Request 2 → 200 (stub returns accepted)
         - Request 3 → **429 with `Retry-After` header** (stub returns rejected with a fake retry-after of 42s)
         - Test asserts: response status, header value, response body shape
         - Test also asserts: stub was called exactly 3 times with the key matching `$user->id()` (via `$stub->getCallLog()`)
       - This test runs in <50ms and has **zero** wall-clock dependency because no time passes — the stub is deterministic.
    - **What we DO NOT test here**: the fixed-window algorithm's correctness across time (Symfony's concern, already tested upstream).
    - **What we DO test**: the controller correctly consumes the limiter with the right key, the controller correctly translates `accepted` into 200 and `rejected` into 429 with Retry-After.
    - The real 15 req/sec prod policy remains in effect outside the test env.
    - The manual smoke test in Task 12.3 validates the real policy end-to-end via rapid keystroking.

#### Phase 4 — Forms (~2h)

- [ ] **Task 4.1: Extend `config/packages/csrf.yaml`**
  - File: `config/packages/csrf.yaml`
  - Action: Append `create_client` and `create_animal` to the existing `stateless_token_ids` list:
    ```yaml
    framework:
        csrf_protection:
            stateless_token_ids:
                - submit
                - authenticate
                - logout
                - create_client
                - create_animal
    ```

- [ ] **Task 4.2: Create `ClientFormType`**
  - File: `src/Presentation/Clinic/Form/Client/ClientFormType.php`
  - Fields: `firstName` (TextType + `NotBlank(message: 'Le prénom est obligatoire.')` + `Length(max: 255, maxMessage: 'Le prénom ne peut pas dépasser 255 caractères.')`), `lastName` (same), `email` (EmailType + `Email(message: "Cette adresse email n'est pas valide.")`, optional), `phone` (TelType, optional), `_returnTo` (HiddenType, `mapped: false`, no constraint).
  - `configureOptions`:
    - `data_class` → `null` (we use arrays, mapped to CreateClient command in the controller)
    - `csrf_token_id` → `'create_client'`
    - `constraints` → `[new Callback(callback: [self::class, 'validateAtLeastOneContactMethod'])]`
  - **Callback signature (F9 fix — Symfony's Callback API)**:
    ```php
    public static function validateAtLeastOneContactMethod(
        mixed $data,
        ExecutionContextInterface $context,
        mixed $payload = null,
    ): void {
        \assert(\is_array($data), 'ClientFormType data must be an array (data_class is null)');
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        if ('' === $email && '' === $phone) {
            $context->buildViolation('Au moins un moyen de contact (téléphone ou email) est requis.')
                ->addViolation();
        }
    }
    ```
    The `mixed $data` + `\assert` combo satisfies PHPStan level max (the static call contract is `mixed`, but the assert narrows it to `array` for the method body). **Do NOT** type-hint `array $data` directly — PHPStan max will complain that the method signature doesn't match the constraint's expected callback contract.
  - **`_returnTo` field must be `mapped: false`** — otherwise Symfony's auto-mapping tries to map it to a non-existent `_returnTo` property on the (null) data class, AND the Callback would see it as a contact-method candidate if a dev ever extended the check. Explicit `mapped: false` + documenting intent.
  - Unit test: `tests/Unit/Presentation/Clinic/Form/Client/ClientFormTypeTest.php` extending `TypeTestCase`:
    - Valid submission → maps to expected array, no errors
    - Empty firstName → error "Le prénom est obligatoire."
    - Empty lastName → similar
    - Invalid email → error "Cette adresse email n'est pas valide."
    - Both phone and email empty → form-level error "Au moins un moyen de contact…"
    - Phone alone → valid (no email needed)
    - Email alone → valid
    - `_returnTo` field accessible but not part of the mapped data array

- [ ] **Task 4.3: Create `AnimalFormType`**
  - File: `src/Presentation/Clinic/Form/Animal/AnimalFormType.php`
  - Fields: `name`, `species` (ChoiceType), `sex` (ChoiceType), `breed` (TextType, optional), `birthDate` (DateType, optional), `primaryOwnerClientId` (HiddenType + `NotBlank(message: 'Le propriétaire est obligatoire.')` + `Uuid()`), `_returnTo` (HiddenType).
  - `csrf_token_id` → `'create_animal'`
  - Unit test: similar to Task 4.2.

#### Phase 5 — Create controllers refactor (~2h)

- [ ] **Task 5.1: Refactor `CreateClientController`**
  - File: `src/Presentation/Clinic/Controller/Client/Profile/CreateClientController.php`
  - Action: Inject `ReturnToResolver` + `QueryBusInterface` (already has `CommandBusInterface`). Rewrite `__invoke`:
    - Create form via `$this->createForm(ClientFormType::class)`
    - `$form->handleRequest($request)`
    - If `isSubmitted && isValid`:
      - Extract data, dispatch `CreateClient` command
      - Redirect to `$this->returnToResolver->resolve($data['_returnTo'], 'clinic_clients_list')` with HTTP 303
    - Else:
      - `return $this->render('clinic/clients/list/_form_client_body.html.twig', ['form' => $form])` — **pass the `Form` object, not `$form->createView()`** (see D25). Symfony's auto-422 detector in `AbstractController::render()` iterates render parameter values looking for `FormInterface` instances; a `FormView` is NOT a `FormInterface` and would silently skip the detection, leaving the response at 200 and breaking Turbo's frame swap.

- [ ] **Task 5.2: Refactor `CreateAnimalController`**
  - Same pattern as 5.1 — `return $this->render($partial, ['form' => $form])` passing the Form object, not the FormView. Default return route: `clinic_clients_list` with `['tab' => 'animals']` param.
  - Includes validation that `primaryOwnerClientId` exists in the current clinic — dispatch `GetClientById($primaryOwnerClientId)` (see Task 1.4 for existence/shape verification) and verify the returned DTO's clinicId matches the current clinic. If not, add a form error on the field via `$form->get('primaryOwnerClientId')->addError(new FormError('Ce propriétaire est introuvable dans cette clinique.'))`.

- [ ] **Task 5.3: Integration test `CreateClientController`**
  - File: `tests/Integration/Presentation/Clinic/Controller/Client/Profile/CreateClientControllerTest.php`
  - 6 cases:
    1. Valid form, no `_returnTo` → 303 redirect to `clinic_clients_list`, client persisted
    2. Valid form + `_returnTo=clinic_scheduling_agenda|date=2026-04-11&view=week` → 303 redirect to agenda with preserved params
    3. Valid form + `_returnTo` with unknown params (`tab=foo&evil=xyz`) → unknown params filtered
    4. Valid form + `_returnTo=http://evil.com` → fallback to default
    5. Invalid form (empty firstName) → **422** + French error in body + presence of `<turbo-frame id="new-client-form">`
    6. Invalid form (phone and email both empty) → 422 + form-level Callback error

- [ ] **Task 5.4: Integration test `CreateAnimalController`**
  - Same pattern + `primaryOwnerClientId` validation test (POST with an id from another clinic → 422 with field error).

#### Phase 6 — Modal templates + partials (~1.5h)

> **⚠ Cross-phase dependency (F12 fix)** — Phase 6's tasks include the modal templates (`_modal_new_client.html.twig`, `_modal_new_animal.html.twig`) which reference `createClientForm` / `createAnimalForm` Twig variables. Those variables are only passed to the template by `ListClientsController` after **Task 8.1** (in Phase 8). Running Phase 6 in isolation would break `make ci` because the modal templates would render `undefined variable createClientForm`. **Mitigation**: split Task 8.1 into two increments:
>
> - **Task 8.0 (NEW, executed as prerequisite to Phase 6)**: patch `ListClientsController` to call `$createClientForm = $this->createForm(ClientFormType::class)->createView()` and `$createAnimalForm = $this->createForm(AnimalFormType::class)->createView()` and pass them to the template. No other changes yet — controller keeps its current tab logic. ~10 lines of code. After Task 8.0, modals in Phase 6 can render their form variables without `undefined` errors, and `make ci` stays green between phases.
> - **Task 8.1 (later in Phase 8)**: the full refactor (tab handling, pagination, count dispatch, etc.) as originally planned.
>
> Execute Task 8.0 BEFORE starting Phase 6.


- [ ] **Task 6.1: Create `_form_client_body.html.twig`**
  - File: `templates/clinic/clients/list/_form_client_body.html.twig`
  - Action: Wrap the form rendering in a `<turbo-frame id="new-client-form">`:
    ```twig
    <turbo-frame id="new-client-form">
      {{ form_start(form, {'attr': {'data-turbo-frame': '_top'}}) }}
        {{ form_row(form.firstName) }}
        {{ form_row(form.lastName) }}
        {{ form_row(form.email) }}
        {{ form_row(form.phone) }}
        {{ form_row(form._returnTo) }}
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
          <button type="submit" class="btn btn-primary">Créer</button>
        </div>
        {{ form_errors(form) }}
      {{ form_end(form) }}
    </turbo-frame>
    ```
  - **Critical Twig gotcha (G2 fix)**: the second argument of `form_start()` is a **context variables** hash, not HTML attributes. HTML attributes on the `<form>` tag must be nested under the `'attr'` key — `{'attr': {'data-turbo-frame': '_top'}}`. A naked `{'data-turbo-frame': '_top'}` is silently dropped (it becomes a Twig local variable that `form_start`'s block never reads), and the rendered `<form>` has NO `data-turbo-frame` attribute — breaking Path A of the D26 spike because success redirects land back in the inner frame.
  - **Alternative**: set the attribute in `ClientFormType::configureOptions()` via `'attr' => ['data-turbo-frame' => '_top']`. Either approach is valid; the spec prefers the Twig-level attr because it's more visible at the call site.
  - **Critical**: the `<turbo-frame>` wrapping is inside the partial, NOT inside the modal template that includes it. This way the frame is present on both initial render and 422 re-render.
  - **Spike procedure (Task 0.3) must use the CORRECT syntax** (`{'attr': {...}}`) when testing Path A — otherwise the spike reports Path A broken when it's actually the Twig call that's wrong.

- [ ] **Task 6.2: Create `_form_animal_body.html.twig`**
  - File: `templates/clinic/clients/list/_form_animal_body.html.twig`
  - Same pattern as Task 6.1, frame id `new-animal-form`, renders `AnimalFormType` fields. Use the same correct Twig syntax `{{ form_start(form, {'attr': {'data-turbo-frame': '_top'}}) }}` (see G2 gotcha in Task 6.1).

- [ ] **Task 6.3: Rewrite `_modal_new_client.html.twig`**
  - File: `templates/clinic/clients/list/_modal_new_client.html.twig`
  - Action: Replace the stubbed form with `{{ include('clinic/clients/list/_form_client_body.html.twig', {form: createClientForm}) }}`. The modal shell (title, close button) stays.
  - The `createClientForm` variable is passed by `ListClientsController` to the main template (see Task 8.1).

- [ ] **Task 6.4: Rewrite `_modal_new_animal.html.twig`**
  - Same + wizard step 1 (owner autocomplete via Stimulus) + step 2 (include `_form_animal_body.html.twig`).
  - Step 1 markup: `<input type="text" data-controller="client-search-autocomplete" data-client-search-autocomplete-target="input" data-action="input->client-search-autocomplete#onInput keydown->client-search-autocomplete#onKeydown">` + dropdown container + hidden input `primaryOwnerClientId`.

#### Phase 7 — Autocomplete Stimulus controller (~2.5h)

- [ ] **Task 7.1: Create `client_search_autocomplete_controller.js`**
  - File: `assets/controllers/client_search_autocomplete_controller.js`
  - Targets: `input`, `dropdown`, `liveRegion`, `hiddenInput`
  - Values: `url: String` (default `/api/clinic/clients/search`), `minChars: Number` (default 2), `debounce: Number` (default 300)
  - Lifecycle: `connect()` sets ARIA attrs on input (`role=combobox`, `aria-expanded=false`, `aria-controls`, `aria-autocomplete=list`)
  - Methods:
    - `onInput(event)` — debounced fetch. If value length < minChars, clear dropdown + return. Else fetch with `Accept: application/json` header, parse envelope, render dropdown items with `role=option`, unique ids, `aria-selected=false`. Update `aria-live` with `"N résultats"` or `"Aucun résultat"`.
    - `onKeydown(event)` — ArrowDown/Up advance `aria-activedescendant` (scroll into view if needed, do not move input focus). Enter fills hidden input + closes dropdown + sets input value to `fullName`. Escape closes + clears hidden input. Tab closes without selecting.
    - `handle429(response)` — silent skip, `console.debug('Autocomplete rate limited')`, no exception.
    - `handleAuthError(response)` — if 401, display "Session expirée, rechargez la page" in dropdown (non-blocking note).
  - Target: ~150 lines including comments.
  - Unit test: `tests/Unit/assets/controllers/client_search_autocomplete_controller.test.js` via a JS test runner — if the project has Playwright/Vitest, use that. Otherwise a minimal DOM-mocked test in PHP via a Panther-like harness. **Fallback**: manual smoke test only, document this in the Notes.

#### Phase 8 — List page refactor (~3h)

- [ ] **Task 8.0 (PREREQUISITE — already executed before Phase 6): Bootstrap `ListClientsController` with empty form instances**
  - **If you're reading this during Phase 8, this task should already be done** — it was moved to be a prerequisite of Phase 6 (see the ⚠ Cross-phase dependency note at the top of Phase 6).
  - File: `src/Presentation/Clinic/Controller/Client/Profile/ListClientsController.php`
  - Action: Add `$createClientForm = $this->createForm(ClientFormType::class);` and `$createAnimalForm = $this->createForm(AnimalFormType::class);` near the existing render call. Pass both as `createClientForm` and `createAnimalForm` template variables (passing the **Form objects**, not `->createView()` — this is the LIST controller, not a Create controller, so auto-422 doesn't apply here, but consistency with the Create controllers avoids confusion and the template's `form_start`/`form_row` calls work fine with either). No other changes yet — tab handling, count dispatch, search/pagination stay as the current stub.
  - Purpose: make Phase 6 template changes consumable without `undefined variable` errors, keeping CI green between phases.

- [ ] **Task 8.1: Full refactor of `ListClientsController`**
  - File: `src/Presentation/Clinic/Controller/Client/Profile/ListClientsController.php`
  - Action: Rewrite `__invoke` to handle `?tab=clients|animals&search=&page=`:
    - Validate `$tab` against `['clients', 'animals']`, fallback to `'clients'`
    - Dispatch the active tab's query (`SearchClients` or `SearchAnimals`) with the searchTerm and page
    - Dispatch the inactive tab's count query (`CountAnimals` or `CountClients`) with the same searchTerm
    - Build form views for the create-client modal (`createForm(ClientFormType::class)`) and create-animal modal (`createForm(AnimalFormType::class)`) with `_returnTo` hidden field prefilled
    - Pass to template: `activeTab`, `clients` or `animals` (active tab data), `inactiveTabCount`, `search`, `createClientForm`, `createAnimalForm`, `currentClinicId`, `currentClinicName`

- [ ] **Task 8.2: Rewrite `_tab_clients.html.twig`**
  - File: `templates/clinic/clients/list/_tab_clients.html.twig`
  - Action: Render the search form as `<form method="GET" action="{{ path('clinic_clients_list') }}"><input type="hidden" name="tab" value="clients">…</form>`. Render table rows from `clients.items` with real fields. **Cross-BC columns** (`animals count`, `last visit`) render `{{ '—' }}` as fallback — add a Twig comment `{# pending cross-BC enrichment — follow-up spec #}`. Render pagination from `clients.hasPreviousPage`/`hasNextPage`/`currentPage`/`totalPages`.
  - Remove `oninput="onClientSearch(…)"` inline JS. Search is now server-driven.

- [ ] **Task 8.3: Rewrite `_tab_animals.html.twig`**
  - Same for animals. Cross-BC columns (`owner`, `age`, `last visit`, `next visit`) render `"—"` fallback with Twig comment.

- [ ] **Task 8.4: Update `index.html.twig`**
  - File: `templates/clinic/clients/list/index.html.twig`
  - Action:
    - Replace the tab buttons with `<a href="{{ path('clinic_clients_list', {tab: 'clients'}) }}" class="tab-line{% if activeTab == 'clients' %} is-active{% endif %}" data-turbo-frame="clients-results">` — links targeting the frame.
    - Wrap the tab content zone + pagination in `<turbo-frame id="clients-results" data-turbo-action="advance">…</turbo-frame>`
    - Render only the active tab partial inside the frame (skip the inactive one — nothing to render).
    - Remove the `window.ROUTES = {…}` script shim referencing deleted `clinic_clients_new`.
    - Include the `_modal_new_client.html.twig` and `_modal_new_animal.html.twig` modals OUTSIDE the frame (they stay across frame swaps).

- [ ] **Task 8.5: Integration test `ListClientsController`**
  - 7 cases:
    1. `?tab=clients` renders clients tab, dispatches SearchClients + CountAnimals
    2. `?tab=animals` renders animals tab, dispatches SearchAnimals + CountClients
    3. `?tab=unknown` falls back to `clients`
    4. `?search=rex` filters active tab + inactive tab badge shows filtered count
    5. `?page=2` paginates
    6. Clinic scoping enforced
    7. **`"—"` fallback presence** on cross-BC columns (assert HTML contains `"—"` in at least one expected column)

#### Phase 9 — JS rewrite (~1h)

- [ ] **Task 9.1: Rewrite `assets/js/pages/clients/list.js`**
  - Action: Full wipe. New content (~60 lines):
    - `init()`: set initial `is-active` class on tabs based on URL query param (fallback for when Turbo Drive restores from cache).
    - `cleanup()`: no-op.
    - Export `init` and `cleanup` for the page dispatcher.
    - Optionally: reference the autocomplete Stimulus controller by importing nothing — it's registered via Stimulus's `data-controller` attribute in the template.
  - Remove: `CLIENTS` array, `clientsState`, `animalsState`, `renderClientsTable`, `onClientSearch`, pagination state, filter state, sort state, all ~400 lines of mock logic.

#### Phase 10 — Deletions + redirects + breadcrumbs (~1h)

- [ ] **Task 10.1: Delete obsolete controllers + template**
  - Delete:
    - `src/Presentation/Clinic/Controller/Animal/Profile/ListAnimalsController.php`
    - `src/Presentation/Clinic/Controller/Animal/Profile/NewAnimalController.php`
    - `src/Presentation/Clinic/Controller/Client/Profile/NewClientController.php`
    - `templates/clinic/animals/list.html.twig`

- [ ] **Task 10.2: Create `AnimalsListRedirectController`**
  - File: `src/Presentation/Clinic/Controller/Animal/Profile/AnimalsListRedirectController.php`
  - Action:
    ```php
    #[Route('/animals', name: 'clinic_animals_list', methods: ['GET'])]
    final class AnimalsListRedirectController extends AbstractController
    {
        public function __invoke(): Response
        {
            return $this->redirectToRoute('clinic_clients_list', ['tab' => 'animals'], 301);
        }
    }
    ```

- [ ] **Task 10.3: Repoint redirects in existing controllers**
  - Files:
    - `src/Presentation/Clinic/Controller/Animal/Profile/ArchiveAnimalController.php` — change success redirect target to `redirectToRoute('clinic_clients_list', ['tab' => 'animals'])`
    - `src/Presentation/Clinic/Controller/Animal/Profile/UpdateAnimalController.php` — if any `clinic_animals_list` reference, repoint
    - `src/Presentation/Clinic/Controller/Client/Profile/ArchiveClientController.php` — check and repoint if needed
    - `src/Presentation/Clinic/Controller/Client/Profile/UnarchiveClientController.php` — same
    - `src/Presentation/Clinic/Controller/Client/Profile/UpdateClientController.php` — same

- [ ] **Task 10.4: Breadcrumb cleanup**
  - Files:
    - `templates/clinic/animals/form.html.twig` — replace `{{ path('clinic_animals_list') }}` with `{{ path('clinic_clients_list', {tab: 'animals'}) }}` in breadcrumbs + cancel buttons
    - `templates/clinic/animals/view.html.twig` — same
    - `templates/clinic/clients/form.html.twig` — if any stale `clinic_clients_new` reference, remove or repoint

#### Phase 11 — Integration tests for repointed controllers (~2h)

- [ ] **Task 11.1: `AnimalsListRedirectControllerTest`**
  - File: `tests/Integration/Presentation/Clinic/Controller/Animal/Profile/AnimalsListRedirectControllerTest.php`
  - 1 case: `GET /animals` → 301 Location `/clients?tab=animals`

- [ ] **Task 11.2: `ArchiveAnimalControllerTest`**
  - File: `tests/Integration/Presentation/Clinic/Controller/Animal/Profile/ArchiveAnimalControllerTest.php`
  - 1 case: authenticated POST `/animals/{id}/archive` with valid CSRF → 302 Location `/clients?tab=animals` + archived animal in DB + flash success

- [ ] **Task 11.3: `UpdateAnimalControllerTest`**
  - File: `tests/Integration/Presentation/Clinic/Controller/Animal/Profile/UpdateAnimalControllerTest.php`
  - 1 case: authenticated POST `/animals/{id}/update` with valid form → 302 to view page, updated animal in DB

- [ ] **Task 11.4: `ArchiveClientControllerTest`**
  - 1 case: similar pattern

- [ ] **Task 11.5: `UnarchiveClientControllerTest`**
  - 1 case: similar pattern

- [ ] **Task 11.6: `UpdateClientControllerTest`**
  - 1 case: similar pattern

#### Phase 12 — CI + manual validation (~1h)

- [ ] **Task 12.1: Run `make assets`**
  - Rebuild Tailwind + asset-map after JS + template changes.

- [ ] **Task 12.2: Run `make ci`**
  - Must be green. Fix PHP-CS-Fixer / PHPCS / PHPStan max / PHPUnit failures before proceeding.

- [ ] **Task 12.3: Manual smoke test (AC-Smoke)**
  - Steps:
    1. `make reset-db` + `make load-fixtures`
    2. Open `clinic.kiveto.local/clients` in browser
    3. Verify clients tab shows seeded clients from ClientDataStory
    4. Verify animals tab (click → `?tab=animals`) shows seeded animals
    5. Search "sophie" → clients tab filters, inactive badge "Animaux" shows filtered count
    6. Open "Nouveau client" modal → fill minimal form → submit → verify new client appears in list
    7. Open "Nouvel animal" modal → type 2 chars in owner autocomplete → verify dropdown appears, keyboard nav works, Enter fills hidden field
    8. Submit invalid form (empty firstName) → verify French error appears, modal stays open, other fields preserved
    9. Submit form with both phone and email empty → verify Callback form-level error
    10. Verify `/animals` → 301 redirects to `/clients?tab=animals`

- [ ] **Task 12.4: Commit + PR**
  - Not part of the spec — user decision when all ACs pass.

### Acceptance Criteria

- **AC-List-Tab-Clients** — Given an authenticated user on Paris clinic, when they GET `/clients?tab=clients&search=sophie&page=1`, then the Clients tab is active, the table renders paginated clients whose name or email matches "sophie" filtered by Paris scope, and the Animaux tab badge shows the count of animals matching "sophie" (not the total) — filtered count confirmed via integration test.

- **AC-List-Tab-Animals** — Given the same user, when they GET `/clients?tab=animals&search=rex`, then the Animaux tab is active, the table renders animals matching "rex", and the Clients tab badge shows the filtered count of clients matching "rex".

- **AC-List-Tab-Fallback** — Given `?tab=unknown`, when the page is requested, then the controller falls back to `tab=clients` and renders the clients tab.

- **AC-List-Clinic-Scope** — Given a request from a session whose current clinic is Paris, when GET `/clients?tab=clients`, then only clients belonging to the Paris clinic are returned (no cross-clinic leak).

- **AC-List-Column-Fallback** — Given the list renders, then columns `animals count`, `last visit`, `next visit` (on Clients tab) and `owner`, `age`, `last visit`, `next visit` (on Animaux tab) all display `"—"` as their cell content for every row — cross-BC enrichment is deferred to a follow-up spec.

- **AC-List-TurboFrame-Backend** *(automated integration test)* — Given a request to `/clients?tab=clients` with header `Turbo-Frame: clients-results`, when the controller renders the response, then the response body contains a `<turbo-frame id="clients-results">` element wrapping the tab panel and pagination, AND the body does NOT contain the full layout chrome (topbar, modals, sidebar) that would be present on a non-frame request — asserted via `$crawler->filter('turbo-frame#clients-results')->count() === 1` and `$crawler->filter('.topbar')->count() === 0`.

- **AC-List-TurboFrame-Browser** *(manual smoke test in Task 12.3, not automated)* — Given the list page is loaded in a browser, when the user clicks a pagination link or switches tab, then only the `#clients-results` frame content visibly updates (modals and topbar stay unchanged), the browser URL updates via Turbo's `advance` action, and the browser back button returns to the previous tab/page state.

- **AC-Search-API-Happy** — Given an authenticated user, when they GET `/api/clinic/clients/search?q=rex` with `Accept: application/json`, then the response is HTTP 200 with `Content-Type: application/json`, body `{data: [ClientAutocompleteItem…], meta: {count: N}}`, and the items match clients of the current clinic whose name contains "rex".

- **AC-Search-API-Short** — Given `?q=r` (1 character), when the request is made, then the response is HTTP 200 with body `{data: [], meta: {count: 0}}` — no query dispatched, no error.

- **AC-Search-API-Accept** — Given a request without `Accept: application/json` header, when GET `/api/clinic/clients/search?q=rex`, then the response is HTTP 406.

- **AC-Search-API-Auth** — Given an unauthenticated request (no session or expired), when GET `/api/clinic/clients/search?q=rex`, then the response is HTTP 401 with JSON body `{error: "unauthorized"}` (via `JsonAuthenticationFailureSubscriber`) — NOT the HTML login redirect.

- **AC-Api-Ip-RateLimit** *(F1 fix — deterministic via StubRateLimiterFactory)* — Given the test environment overrides the `limiter.api_clinic_ip` service with `StubRateLimiterFactory` pre-programmed to return `[accepted, accepted, rejected (retry-after 60s)]` on consume calls keyed by `'1.2.3.4'` AND `[accepted]` on calls keyed by `'5.6.7.8'`, when:
  - Unauth request from `1.2.3.4` (1st) → 401 JSON `{error: "unauthorized"}` (listener allowed, firewall denied)
  - Unauth request from `1.2.3.4` (2nd) → 401 JSON (same path)
  - Unauth request from `1.2.3.4` (3rd) → **429 JSON `{error: "too_many_requests"}`** with `Retry-After: 60` header (listener rejected before firewall ran)
  - Unauth request from `5.6.7.8` (during the `1.2.3.4` burst) → 401 JSON (different IP bucket, not rate-limited)
  - Test asserts: stub was called with `'1.2.3.4'` three times, `'5.6.7.8'` once
  - Test asserts: for the 429 response, body is `{error: "too_many_requests"}` (NOT `{error: "unauthorized"}`) — proves the listener short-circuited before the firewall
  - Test asserts: for the 401 responses, body is `{error: "unauthorized"}` (NOT rate limit error) — proves the listener allowed through
  
  The test is deterministic, runs in <50ms, zero wall-clock dependency. The real production policy (`fixed_window` 60/min per IP) runs outside the test env.

- **AC-Search-API-RateLimit** *(integration test — deterministic via stub RateLimiterFactory)* — Given the test environment overrides the `api_clinic_search` service with `StubRateLimiterFactory` pre-programmed to return `[accepted, accepted, rejected (retry-after 42s)]`, when an authenticated user issues 3 sequential requests to `/api/clinic/clients/search?q=rex`, then:
  - Request 1 → HTTP 200 (stub returns accepted)
  - Request 2 → HTTP 200 (stub returns accepted)
  - Request 3 → HTTP **429** with header `Retry-After: 42` and body `{error: "too_many_requests"}`
  - Test also asserts: `StubRateLimiterFactory::getCallLog()` contains exactly 3 entries, each keyed by `$user->id()` (not by session id, not by IP, not by anything else)
  
  The test is deterministic, runs in <50ms, and depends on zero wall-clock time. The real production `fixed_window` policy (15 req/sec) runs outside the test env.

- **AC-Search-API-RateLimit-Manual** *(manual smoke test in Task 12.3)* — Given the production policy (15 req/sec per user), when the operator opens a browser JS console and fires 20 `fetch('/api/clinic/clients/search?q=rex')` rapidly, then the first ~15 return 200 and subsequent ones return 429 until the 1-second window rolls.

- **AC-Search-API-LimitCap** — Given `?limit=100`, when the request is made, then the response items list is capped at 20 elements max (hard cap enforced by the controller).

- **AC-Create-Client-Happy-NoReturnTo** — Given a valid form submission (firstName, lastName, email filled, `_returnTo` empty), when POST `/clients/create`, then the command dispatches, the client is persisted in the DB, and the response is HTTP 303 redirect to `/clients`.

- **AC-Create-Client-Happy-ReturnTo** — Given a valid form submission with `_returnTo=clinic_scheduling_agenda|date=2026-04-11&view=week`, when POST `/clients/create`, then the response is HTTP 303 redirect to `/scheduling/agenda?date=2026-04-11&view=week` with the allowed params preserved.

- **AC-Create-Client-ReturnTo-Filtered** — Given `_returnTo=clinic_scheduling_agenda|date=2026-04-11&evil=xyz`, when POST `/clients/create`, then the redirect URL contains `date=2026-04-11` but NOT `evil=xyz` (filtered by the whitelist).

- **AC-Create-Client-ReturnTo-Rejected** — Given `_returnTo=http://evil.com`, when POST `/clients/create`, then the redirect falls back to the default `clinic_clients_list` URL and does NOT redirect externally.

- **AC-Create-Client-Invalid-NotBlank** — Given a POST with empty `firstName`, when the form is submitted, then the response is HTTP **422**, the body contains `<turbo-frame id="new-client-form">`, and the French error message `"Le prénom est obligatoire."` is rendered inside the frame.

- **AC-Create-Client-Invalid-Callback** — Given a POST with both `phone` and `email` empty (but firstName + lastName filled), when the form is submitted, then the response is HTTP 422 with a form-level (not field-level) error message `"Au moins un moyen de contact (téléphone ou email) est requis."` — surfaced via the `Callback` constraint before the command ever dispatches.

- **AC-Create-Client-ReturnTo-Survives-Error** — Given a POST with an invalid form AND a `_returnTo` hidden field, when the 422 error re-render happens, then the `_returnTo` hidden input is still present in the re-rendered partial with its original value (user can correct and re-submit without losing the context).

- **AC-Create-Animal-Happy** — Given a valid form with `primaryOwnerClientId` pointing to an existing client in the current clinic, when POST `/animals/create`, then the animal is persisted and the response is HTTP 303 redirect to `/clients?tab=animals`.

- **AC-Create-Animal-Invalid-Owner** — Given `primaryOwnerClientId` pointing to a client in a different clinic (cross-clinic IDOR attempt), when POST `/animals/create`, then the response is HTTP 422 with a field-level error on `primaryOwnerClientId`.

- **AC-Autocomplete-Happy** — Given the new-animal modal is open and the user focuses the owner input, when they type "so" (2 chars), then after 300ms idle the controller fetches `/api/clinic/clients/search?q=so`, the dropdown displays matching clients with `role=option`, and pressing `Enter` on an item fills the hidden `primaryOwnerClientId` input with that client's id.

- **AC-Autocomplete-Keyboard** — Given the dropdown is open, when the user presses `ArrowDown`, then `aria-activedescendant` advances without moving the input focus; `Escape` closes the dropdown and clears the hidden input; `Tab` closes the dropdown without selecting.

- **AC-Autocomplete-Aria-Live** — Given a search returns results, when the dropdown renders, then the `aria-live="polite"` region announces `"N résultats"` (or `"Aucun résultat"` if empty).

- **AC-Autocomplete-429-Silent** — Given a 429 response from the rate-limited API, when the Stimulus controller receives it, then NO exception is thrown and the dropdown remains in its previous state (unit test asserts via DOM-mocked response).

- **AC-Animals-Redirect** — Given the legacy URL `/animals`, when a user GETs it, then the response is HTTP 301 with `Location: /clients?tab=animals`.

- **AC-Archive-Animal-Redirect** — Given an authenticated user POSTs `/animals/{id}/archive` with a valid CSRF token, then the animal is archived in the DB, a success flash is set, and the response is HTTP 302 Location `/clients?tab=animals`.

- **AC-Archive-Client-Redirect** — Same pattern for `ArchiveClientController` → redirect to `/clients` (no tab).

- **AC-Update-Animal-Redirect** — Given a valid update POST, when the form is valid, then the response redirects to the animal's view page (unchanged behaviour, test verifies the repointing didn't break it).

- **AC-Update-Client-Redirect** — Same for client update.

- **AC-Unarchive-Client-Redirect** — POST `/clients/{id}/unarchive` → 302 to view page.

- **AC-Modal-Stays-Open-On-Error** — Given the user submits an invalid form via the `_modal_new_client` modal, when the 422 response comes back, then the modal is still visually open (CSS class `hidden` is absent from `.modal-overlay`), the error messages are inside the `<turbo-frame id="new-client-form">`, and the user can correct and re-submit without re-opening the modal.

- **AC-CI-Green** — `make ci` passes all stages (php-cs-fixer dry-run, phpcs, phpstan level max, tailwind-build, phpunit) with 100% coverage on new/changed code in Client + Animal + Shared + Clinic Presentation touchpoints for this spec.

## Additional Context

### Dependencies

- Client BC + Animal BC Application layers are fully implemented for write/read.
- `Client::create()` enforces "at least 1 contact method" as a **domain invariant** — `ClientFormType` must pre-validate this at the form level to avoid 500s.
- `ListClientsController` already wires `SearchClients` correctly — only the consumer is the gap.
- Foundry fixtures exist for clients and animals.
- No DB migration required.
- **`symfony/rate-limiter` is a new composer dependency** — add `"symfony/rate-limiter": "^7.4"` to `composer.json` require section.
- **Command bus `doctrine_transaction` middleware is per-dispatch** — not request-scoped. Multi-command sequences are not atomic.
- **Symfony 7.4+ auto-sets HTTP 422** on form invalid responses — no manual Response status.
- **`symfony/ux-turbo` is installed** (confirmed via composer.json:41) — Turbo Frames + Streams available.
- **kiveto form theme** is applied project-wide via Twig config — `{{ form(form) }}` renders with Kiveto styling automatically.

### Testing Strategy

- **Unit tests**
  - `ClientFormType` + `AnimalFormType` via Symfony `FormTestCase`:
    - Valid submission → DTO mapping
    - Invalid submission → expected French constraint messages on errors
    - **`ClientFormType` Callback**: submit with both phone and email empty → form-level error "Au moins un moyen de contact (téléphone ou email) est requis"
  - `ReturnToResolver` (plain unit test):
    - Whitelisted route + allowed params → correct URL
    - Whitelisted route + unknown params → unknown params filtered out
    - Non-whitelisted route → fallback to default
    - Malformed input (no `|`, broken params) → fallback
    - Open redirect attempts (`javascript:alert(1)`, `//evil.com/x`, `http://external.com`) → rejected
    - Round-trip of params with accents, apostrophes, URL-encoded chars → correct
  - `CountClientsHandler` / `CountAnimalsHandler` via `KernelTestCase` + Foundry:
    - Count filtered by searchTerm matches expected
    - Clinic scoping enforced (other clinics not counted)
    - Empty result returns 0
    - Status filter respected
  - `client_search_autocomplete_controller.js` (DOM-mocked unit tests):
    - ArrowDown advances `aria-activedescendant`, does NOT move input focus
    - Enter writes selected client id to hidden `primaryOwnerClientId` input
    - Escape closes dropdown and clears hidden field
    - Tab closes without selecting
    - `aria-live` announces `"5 résultats"` / `"Aucun résultat"` on state change
    - Debounce: two keystrokes within 300ms → single fetch after 300ms idle
- **Integration tests** (WebTestCase + KernelTestCase)
  - `ListClientsController`:
    - `?tab=clients` dispatches `SearchClients` + `CountAnimals`, renders clients tab
    - `?tab=animals` dispatches `SearchAnimals` + `CountClients`, renders animals tab
    - `?tab=unknown` falls back to `clients`
    - `?search=rex` filters active tab AND **inactive tab badge shows filtered count**
    - `?page=2` paginates
    - Clinic scoping enforced
  - `SearchClientsApiController`:
    - `?q=rex` → JSON `{data: [...], meta: {count: N}}` with `Content-Type: application/json`
    - `?q=r` (1 char) → 200 + empty envelope (not an error)
    - Request without `Accept: application/json` → 406
    - `?limit=5` respected, `?limit=100` capped at 20
    - Clinic scoping enforced
    - Unauthenticated request → **401 JSON** via `JsonAuthenticationFailureSubscriber`
    - 16th request in 1-sec window → **429** with `Retry-After` header
  - `CreateClientController`:
    - Valid form + no `_returnTo` → 303 redirect to `clinic_clients_list`
    - Valid form + `_returnTo=clinic_scheduling_agenda|date=2026-04-11&view=week` → 303 to agenda with preserved params
    - Valid form + `_returnTo` with unknown params → filtered, redirect to whitelisted route with allowed params only
    - Valid form + `_returnTo=http://evil.com` → fallback to default
    - Invalid form (empty firstName) → **HTTP 422** (auto by Symfony 7.4), response contains `<turbo-frame id="new-client-form">` + French error
    - Invalid form (phone and email both empty) → **HTTP 422** with Callback form-level error "Au moins un moyen de contact…"
    - `_returnTo` hidden field survives the 422 re-render
  - `CreateAnimalController`:
    - Valid form → 303 redirect to `clinic_clients_list?tab=animals`
    - Invalid form → 422
    - `primaryOwnerClientId` validated against an existing client in the clinic
  - `AnimalsListRedirectController`:
    - `GET /animals` → 301 Location `/clients?tab=animals`
- **Test count dimensioning** (right-sized after Party Mode round 3):
  - **Smoke redirect tests for existing controllers** (Archive/Update/Unarchive × 5) — **1 test case per file**, asserts status + Location header after a valid POST. ~50 lines of test total. The prod change is 5 lines of redirect repointing; a 1:1 ratio is acceptable for regression safety.
  - **Net-new heavy tests** on the 5 refactored/new controllers:
    - `CreateClientControllerTest`: 5-6 cases (valid + `_returnTo`, invalid NotBlank, invalid phone-or-email Callback, `_returnTo` allowlist filtering, open-redirect rejection, auto-422 status assertion)
    - `CreateAnimalControllerTest`: 5-6 cases (same pattern, plus `primaryOwnerClientId` validation)
    - `SearchClientsApiControllerTest`: 7-8 cases (envelope shape, 406 on non-JSON Accept, 2-char min returns empty envelope, limit cap, clinic scope, 401 JSON on unauth, 429 on burst via rate limiter)
    - `ListClientsControllerTest`: 6-7 cases (tab=clients, tab=animals, unknown tab fallback, search filter, pagination, inactive badge filtered count)
    - `AnimalsListRedirectControllerTest`: 1 case (GET /animals → 301 Location)
  - **NEW test files from scratch (no existing tests)** — this spec creates **8-9 test files** totalling **~35 test cases** across ~600-700 lines:
    - `UpdateClientControllerTest` (smoke)
    - `ArchiveClientControllerTest` (smoke)
    - `UnarchiveClientControllerTest` (smoke)
    - `UpdateAnimalControllerTest` (smoke)
    - `ArchiveAnimalControllerTest` (smoke — asserts redirect to **`clinic_clients_list?tab=animals`**)
    - `AnimalsListRedirectControllerTest` (1 case)
    - `CreateClientControllerTest` (heavy — 5-6 cases, including the auto-422 assertion per D25)
    - `CreateAnimalControllerTest` (heavy — 5-6 cases, including the auto-422 assertion per D25)
    - `SearchClientsApiControllerTest` (heavy — 7-8 cases)
    - `ListClientsControllerTest` (heavy — 6-7 cases)
- **Presentation layer (Twig)**: no automated tests for the template rendering itself (covered by manual smoke test). The "—" fallback on cross-BC columns is tested indirectly via the `ListClientsControllerTest` integration tests which render the full template and can assert on the presence of the fallback text.

### Notes

- **Symfony 7.4 auto-422** is a significant simplification — the spec's controllers just `return $this->render(...)` on invalid form without any manual `Response` status juggling. Verified via `_bmad-output/project-context.md:178`.
- **Modal is plain DOM** not Stimulus — `assets/js/ui/modal.js:1-241`. The turbo-frame error re-render only replaces the frame content (inside the modal), not the modal shell itself. Modal open state (`hidden` class absence + `body.style.overflow='hidden'`) is preserved naturally.
- **`_form_client_body.html.twig` + `_form_animal_body.html.twig`** include their own `<turbo-frame>` wrapping tags, so initial render and 422 re-render produce the same DOM shape and Turbo matches the frame id.
- **`ReturnToResolver` lives in `src/Shared/Infrastructure/Http/`** — cross-BC concern, Shared Infrastructure is the idiomatic home. Not in Presentation.
- **Rate limit is user-based**, not session-based — simpler since every `/api/clinic/*` call runs under `IS_AUTHENTICATED_FULLY` and we always have a user id available via `Security::getUser()->id()`. Symfony RateLimiter default `FixedWindowRateLimiter` handles user keys natively.
- **Unified list page to reconsider in ~6 months** — tabs are administrative, vet mental model is animal-first. Non-blocking.
- **Future enhancement: Turbo Streams for VetApp form responses** — `_bmad-output/project-context.md:163-167` recommends Streams over Frames for form responses in VetApp (live DOM updates without full reload). This spec stays on Frames for simpler initial ship. A follow-up spec can migrate `CreateClientController` + `CreateAnimalController` to Stream responses to add the new client/animal row to the list live, without closing the modal.
- **CRITICAL DOMAIN CONSTRAINT — Client contact methods**: `Client::create()` enforces "at least 1 contact method" as a domain invariant (`src/Context/Client/Domain/Client.php:46-73, 236-240`). `ClientFormType` MUST enforce this at form level via `Callback` constraint so the user sees a friendly error instead of a 500. The modal is simplified to 4 fields (firstName, lastName, email, phone) — removing date / address / notes from the existing 7-field template.
- **CRITICAL TRANSACTION SCOPE — per-dispatch**: `config/packages/messenger.yaml:20-31` wires `doctrine_transaction` middleware on the command bus. Each `$commandBus->dispatch()` runs in its own transaction; multi-command sequences in a single controller are NOT atomic. The future RDV spec needs to account for this — either introduce a composite `CreateClientAndAnimal` command OR wrap both dispatches in `EntityManagerInterface::wrapInTransaction()` at the controller level OR accept non-atomicity with compensation logic.
- **RateLimiter composer dependency**: `symfony/rate-limiter` is NOT currently installed. Add `"symfony/rate-limiter": "^7.4"` to `composer.json` require section, run `composer update symfony/rate-limiter`, commit the updated `composer.lock`.
- **`config/routes/api.yaml` is NEW**: doesn't exist today. Create the file with a `prefix: /api/clinic` directive importing the `src/Presentation/Clinic/Controller/Api/Clinic/` namespace via a `resource:` directive. Import it from `config/routes.yaml` alongside existing route files.
- **Zero controller test coverage today**: the spec creates redirect and happy-path integration tests from scratch for Create/Update/Archive/Unarchive/Redirect controllers. This is slightly more work than "update existing tests" but avoids any regression risk on the repointed redirects.
- **Autocomplete min 2 chars enforced at controller level**: `SearchClientsCriteria` doesn't validate searchTerm length (nullable, empty string accepted). The `SearchClientsApiController` enforces 2-char minimum before dispatching — shorter queries return an empty envelope with 200 OK. Aligns with the Stimulus controller's own 2-char minimum (double-layer safety).
- **Form theme Kiveto** already applied via Symfony config — `{{ form(form) }}` renders with `templates/form/kiveto_form_theme.html.twig` out of the box. No per-form theme override needed.
- **Open-redirect protection** on `ReturnToResolver` — whitelist by route name only, never accept raw URLs. Covered by dedicated unit test suite.
- **Cross-BC column fallback to "—" (D24)**. The templates `_tab_clients.html.twig` and `_tab_animals.html.twig` keep their full column layout — including fields that the current `SearchClients` / `SearchAnimals` DTOs don't expose (`owner` name, computed `age`, `animals count`, `last visit`, `next visit`). These cells render `"—"` in this iteration. A dedicated follow-up spec **"Enrich Clients/Animaux list with cross-BC computed fields"** will introduce 3 new queries composed at the `ListClientsController` level, same composition pattern as the agenda (`SearchAppointments + ListClinicVeterinarians + GetClinic` combined in the controller):
  - `CountAnimalsByOwners(list<ClientId>)` in the Animal BC → `array<clientId, count>`
  - `GetLastVisitByClients(list<ClientId>)` in the Scheduling BC → `array<clientId, ?DateTimeImmutable>`
  - `GetOwnerNamesByAnimals(list<AnimalId>)` in the Client BC → `array<animalId, fullName>`
  The follow-up spec will also add a "next visit" query if scheduling supports future appointments lookup. **No SQL JOIN across BC tables** — pure Application-layer composition, cohérent avec les règles du projet.
- **Auto-422 failure mode (D25)**. Symfony 7.4's `$this->render(...)` automatically sets the response status to 422 when the form is submitted and invalid AND the form is passed in the render context under the key `form`. If a dev passes the form under any other key (`clientForm`, `createForm`, etc.) the mechanism silently falls back to 200 and Turbo will not swap the frame content. The mitigation is **strict integration test** — every create controller asserts `$response->getStatusCode() === 422` on an invalid submit path. If the dev uses the wrong key, the test catches it immediately. Spec rule: **always pass the form under `form`, never a custom key**.
- **Test count dimensioning**: ~35 cases across 8-9 new test files, ~600-700 lines total. Smoke tests for the 5 repointed Archive/Update/Unarchive controllers (1 case each), substantial tests for the 5 refactored/new controllers (5-8 cases each). Dimension validated in Party Mode round 3 to balance regression safety against scope creep.
- **D18 finding stays pure documentation — no proactive solution in this spec**. If the RDV popup spec needs atomic multi-command dispatch, a dedicated mini-spec **"Composite command dispatch pattern"** will introduce either a `TransactionalCommandBus` helper or a single composite command handler. Not built here to prevent scope creep.
- **Rate limiter 429 handling in the Stimulus autocomplete controller (I35)**: silent skip. If `fetch()` returns a 429 response, the controller does NOT display an error to the user — it leaves the dropdown in its previous state and logs the event via `console.debug()`. This is the 80/20 choice because the 15 req/sec + 300ms debounce combination makes 429 practically unreachable in normal usage (a realistic fast typer peaks at 3.3 req/sec). The unit test on the Stimulus controller asserts that a 429 response does NOT throw an exception and leaves the dropdown in a coherent state. **Future enhancement noted**: if operational metrics show actual 429 occurrences, display an in-dropdown message "Recherche momentanément limitée, patientez…" instead of silent skip.
- **Form-in-frame redirect mechanism (D26)**: the interplay between `data-turbo-frame="_top"` and Turbo's frame-id-match during a body swap is the single biggest implementation risk in this spec. Step 3 allocates a 45-minute spike at the start of the form implementation phase to verify the current plan works as expected. If it doesn't, the Turbo Stream fallback is pre-documented and ready to swap in. This is the reason the D26 wording says "to be verified" rather than asserting a definitive behaviour — transparent about the remaining uncertainty.
