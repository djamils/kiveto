---
title: 'Slice 0.5 — Client company verification (registry + VIES) and external HTTP foundations'
slug: 'client-company-verification'
created: '2026-10-19'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['PHP 8.5', 'Symfony 7.4 (Messenger, Scheduler, HttpClient 7.4, Lock, Cache)', 'Doctrine ORM + DBAL (MySQL)', 'Monolog 3 / MonologBundle 4', 'Twig + AssetMapper plain ES modules', 'PHPUnit + DAMA + Zenstruck Foundry']
files_to_modify: ['config/packages/framework.yaml', 'config/packages/monolog.yaml', 'config/packages/messenger.yaml', 'config/services.yaml', 'config/services_test.yaml', '.env', '.env.test', 'compose.yaml', 'src/Shared/Application/Bus/*', 'src/Shared/Infrastructure/Bus/Messenger/*', 'src/Shared/Application/ExternalApi/*', 'src/Shared/Infrastructure/ExternalApi/*', 'src/Shared/README.md', 'src/Context/Client/Domain/Client.php', 'src/Context/Client/Domain/TaxProfile/*', 'src/Context/Client/Domain/Event/ClientTaxProfileVerified.php', 'src/Context/Client/Application/Port/*', 'src/Context/Client/Application/Query/SearchCompanyRegistry/*', 'src/Context/Client/Application/Command/VerifyClientTaxProfile/*', 'src/Context/Client/Application/Command/ReverifyStaleTaxProfiles/*', 'src/Context/Client/Application/Command/RecordTaxProfileVerification/*', 'src/Context/Client/Domain/TaxProfile/LegalNameNormalizer.php', 'tests/Integration/Shared/MessengerHandlersRegistrationTest.php', 'tests/Integration/System/Messenger/MessengerRoutingGuardTest.php', 'src/Context/Client/Application/Verification/OnClientTaxIdentifiersChangedVerify.php', 'src/Context/Client/Domain/Event/ClientTaxIdentifiersChanged.php', 'src/Context/Client/Infrastructure/Scheduler/ClientScheduleProvider.php', 'src/Context/Client/Application/Command/RequestTaxProfileVerification/*', 'src/Presentation/Clinic/Controller/Shared/AbstractSheetAction.php', 'Makefile', 'src/Context/Client/Application/Query/GetClientById/ClientView.php', 'src/Context/Client/Infrastructure/Registry/*', 'src/Context/Client/Infrastructure/Vat/*', 'src/Context/Client/Infrastructure/Persistence/Doctrine/{Entity/ClientEntity,Mapper/ClientMapper,Repository/DoctrineClientRepository,Repository/DoctrineClientReadRepository,Repository/DbalTaxProfileVerificationSchedule}.php', 'migrations/Client/Version20261020090000.php', 'src/Context/Client/README.md', 'src/Presentation/Clinic/Controller/Client/Profile/CompanyRegistrySearchController.php', 'src/Presentation/Clinic/Controller/Client/Sheet/VerifyClientTaxProfileController.php', 'src/Presentation/Clinic/ViewModel/Client/CompanyHitPresenter.php', 'src/Presentation/Clinic/ViewModel/Client/ClientSheetBuilder.php', 'src/Presentation/Clinic/ViewModel/Directory/ClientLookupReader.php', 'src/Presentation/Clinic/Controller/Client/ListClientsController.php', 'src/Presentation/Clinic/ViewModel/Billing/BillingPageBuilder.php', 'templates/clinic/clients/_tax_profile_block.html.twig', 'templates/clinic/clients/view/index.html.twig', 'assets/js/shared/client-tax-profile.js', 'assets/js/pages/clients/sheet.js', 'assets/js/pages/clients/create.js', 'assets/js/pages/billing/register.js', 'assets/styles/components/tax-profile.css', 'assets/styles/pages/dossier.css', 'fixtures/Context/Client/Story/ClientDataStory.php', 'tests/Unit/Shared/Infrastructure/Scheduler/SchedulerWorkerTimeLimitTest.php']
code_patterns: ['DDD BC: Domain / Application (Port, Command, Query, listeners) / Infrastructure', 'Ports named *Interface in Application/Port, adapters in Infrastructure', 'final readonly handlers with bare #[AsMessageHandler] (default = command bus)', 'Domain-event listeners On<Event>, #[AsMessageHandler(bus: messenger.bus.event)], run sync inside the command transaction', 'Aggregate mutators take \\DateTimeImmutable $now; handlers pass ClockInterface::now()', 'Repository save() copies every field onto the managed entity; #[ORM\\Version] on ClientEntity', 'Hand-written migrations per BC folder (migrations/Client, VersionYYYYMMDDHHMMSS)', 'Single-action controllers; sheet POST actions extend AbstractClientSheetAction (CSRF guard, attempt, backToSheet ?saved=cFisc)', 'JSON endpoints: AbstractController + CurrentClinicContextInterface + QueryBusInterface → JsonResponse', 'Front: plain ES modules, shared block assets/js/shared/client-tax-profile.js mounted by register.js / create.js / sheet.js']
test_patterns: ['Unit: PHPUnit TestCase, #[CoversClass], MockHttpClient + MockResponse for HTTP adapters, ArrayAdapter for cache, createStub(ClockInterface)', 'Integration: KernelTestCase + DAMA rollback + Foundry factories in fixtures/Context/*/Factory', 'Async effects asserted through shared__messenger_messages rows or transport services; RecordingCommandBus decorates CommandBusInterface in services_test.yaml', 'Guard tests: MessengerRoutingGuardTest, DoctrineTransportsConfigurationTest (receivers: async, async_external, async_external_batch, failed after this slice), MessengerHandlersRegistrationTest (literal #[AsMessageHandler]), TransactionalMessagingConventionTest, SchedulerWorkerTimeLimitTest, DefaultScheduleProviderTest', 'No WebTestCase; Presentation tested through ViewModel units and KernelTestCase entry-point tests']
---

# Tech-Spec: Slice 0.5 — Client company verification (registry + VIES) and external HTTP foundations

**Created:** 2026-10-19

## Overview

### Problem Statement

The professional tax profile delivered in slice 0 (`ClientTaxProfile`: status, legal name, SIREN, French VAT
number) is typed by hand and only checked locally (SIREN Luhn key, VAT key). Nothing tells the clinic whether the
company exists, is still active, or whether its VAT number is known to VIES. The client sheet's « Profil fiscal »
card (busy_6 mock-up, already on master) renders those states but receives empty data from
`ClientSheetBuilder::taxProfileView()` and shows them as « bientôt ».

External HTTP integrations exist already (`GeoplateformeAddressDirectory` in Client, `GotenbergPdfRenderer` in
Shared) but each wires `HttpClientInterface` its own way: no shared timeout/retry policy, no logging, no guard
against tests reaching the network. Slice 4 (Super PDP) will add more; the foundations are laid now.

### Solution

- **Shared external-API foundations** (`Shared/*/ExternalApi`, `Shared/*/Bus`): one scoped client per external
  service (base URL, idle 2 s / total 5 s, User-Agent with a contact checked at first use, no Symfony auto-retry),
  one `RetryAfterAwareClient` per service turning 429 (throttled), 503/5xx and timeouts (down) into
  `ExternalServiceUnavailable`, an `external_api` Monolog channel (service, duration, status, outcome — never the
  payload) with Symfony's `http_client` channel kept out of the logs, a test-env guard failing any unmocked HTTP
  call, and a dedicated `async_external` transport + `messenger.bus.external_api` (no transaction) +
  `external-worker` for background external calls. Rule: **never a network call inside a database transaction**.
- **Client company verification**: `CompanyRegistryInterface` (API Recherche d'entreprises) and
  `VatNumberVerifierInterface` (VIES REST), each with an HTTP adapter and a fixture adapter selected by env var;
  per-container registry budgets (web 4/s, worker 1/s). A dated `TaxProfileVerification` is held by the `Client`
  aggregate; the business fact `ClientTaxIdentifiersChanged` resets and requests it. `VerifyClientTaxProfile`
  (no transaction, on the external worker) calls the services, then the short transactional
  `RecordTaxProfileVerification` stores the result (late answers ignored, optimistic lock). Requests are
  de-duplicated by `tax_verification_requested_at`; a nightly run (02:17) re-requests stale profiles (never
  checked, unavailable, older than 30 days).
- **UI**: autocomplete by name / SIREN / SIRET (counter, directory window, client sheet card), the busy_6 card fed
  with real data plus « Revérifier », « Utiliser le nom officiel » and warning badges; warnings at the counter,
  never blocking; manual entry always reachable.

### Scope

**In Scope:**

1. Shared foundations: scoped clients, `RetryAfterAwareClient`, `ExternalServiceUnavailable` (throttled vs down),
   `ExternalApiUserAgent`, `external_api` channel + `http_client` exclusion, test network guard,
   `async_external` transport / `messenger.bus.external_api` / `ExternalApiCommandBusInterface` /
   `external-worker` with their guards, README convention; Shared brought to 100 % coverage as a whole.
   Géoplateforme and Gotenberg keep their wiring (documented as next adopters).
2. Domain (Client): verification VOs and enums, `LegalNameNormalizer`, `ClientTaxIdentifiersChanged`,
   `recordTaxProfileVerification()` (late answer ignored), `requestTaxProfileVerification()` (24 h de-duplication),
   `ClientTaxProfileVerified`.
3. Application (Client): ports + DTOs, `SearchCompanyRegistry` (`{available, reason}`), commands
   `VerifyClientTaxProfile` (external, no transaction), `RecordTaxProfileVerification`,
   `RequestTaxProfileVerification` (« Revérifier »), `ReverifyStaleTaxProfiles` (nightly), listener
   `OnClientTaxIdentifiersChangedVerify`, schedule port.
4. Infrastructure (Client): registry adapter + mapper + cache + throttle, VIES adapter + error mapper, fixture
   adapters, env selection, 6 nullable columns + index (migration), DBAL schedule finder,
   `ClientScheduleProvider` (`scheduler_client`, consumed by the existing scheduler worker), INSEE legal-form table.
5. Presentation: search and « Revérifier » endpoints (404 for another clinic, nothing queued), sheet payload,
   busy_6 card wiring, shared-block autocomplete in the directory window, counter SIRENE group and warnings,
   optimistic-lock conflicts on sheet saves turned into a flash.
6. Tests without network (hand-written then recorded JSON fixtures, contract test), Client and Shared at 100 %
   as whole BCs, one manual dev run with real adapters ([manuel] AC) that records the fixtures.

**Out of Scope:**

- INSEE Sirene API (key-based).
- PPF / e-invoicing directory (electronic addresses) — System/ElectronicInvoicing, via the approved platform.
- Chorus Pro / public entities; EU and non-EU businesses (slice 2).
- Storing SIRET / establishments (the card keeps « Établissement facturé » and « Adresse de facturation
  électronique » as « bientôt »).
- Any blocking rule beyond slice 0's local checks (SIREN Luhn, VAT key).

## Context for Development

### Codebase Patterns

**Client aggregate and persistence** (`src/Context/Client/Domain/Client.php`, 446 lines)

- `final class Client extends AggregateRoot`; private ctor; `create(ClientId, ClinicId, ClientIdentity, array $contactMethods, \DateTimeImmutable $createdAt, ?ClientTaxProfile $taxProfile = null)` (L57-89) records `ClientCreated`; `reconstitute(...)` (L94-122) takes every field positionally, optional ones last (`postalAddress`, `authorizedPerson`, `communicationPreferences`, `taxProfile`), records nothing.
- `updateTaxProfile(ClientTaxProfile $taxProfile, \DateTimeImmutable $now): void` (L141-162): `ensureNotArchived()` → no-op when `equals()` → set profile + `updatedAt` → record `ClientTaxProfileUpdated`.
- The aggregate holds no clock: mutators take `$now`. Events: `recordDomainEvent()` / `pullDomainEvents()` in `src/Shared/Domain/Aggregate/AggregateRoot.php`; `DomainEventPublisher::publish(AggregateRoot)` sends them to the sync event bus.
- `ClientTaxProfile` (`src/Context/Client/Domain/ValueObject/ClientTaxProfile.php`): `default()`, `individual(?string)`, `frenchBusiness(string, Siren, ?FrenchVatNumber)`; getters `status()`, `legalName()`, `siren()`, `vatNumber()`, `hasLegalName()`; `equals()`. Shared VOs: `Siren::fromInput()/value()/formatted()/equals()`, `FrenchVatNumber::fromInput(string, Siren)/forSiren(Siren)/value()/formatted()/equals()` (`src/Shared/Domain/ValueObject/`).
- Enum style: `ClientTaxStatus` string-backed with French `label()`. Exception style: `InvalidClientTaxProfileException extends \InvalidArgumentException` with reason constants.
- `ClientEntity` (`src/Context/Client/Infrastructure/Persistence/Doctrine/Entity/ClientEntity.php`): attributes, table `client__clients`, index `idx_client_clinic_siren`, tax columns `tax_status`, `legal_name`, `siren` (char 9), `vat_number` (13), `#[ORM\Version] int $version` (L78-80).
- `ClientMapper::toDomain()` (L37) / `taxProfileToDomain()` (L101-115) / `toEntity()` (L68). `DoctrineClientRepository::save()` (L26-65) copies each field onto the `find()`-ed managed entity (tax fields L40-43) — every new column must be added there too; `get()`/`find()` are clinic-scoped. The version column makes a concurrent commit fail the flush (`OptimisticLockException`).
- Migrations: hand-written, `migrations/Client/VersionYYYYMMDDHHMMSS.php`, namespace `DoctrineMigrations\Client`; last tax migration `Version20261017110000` (ALTER TABLE … ADD tax_status/legal_name/siren/vat_number + `idx_client_clinic_siren`).
- Read model: `ClientView` (`src/Context/Client/Application/Query/GetClientById/ClientView.php`, tax fields last and defaulted L27-30) built in `DoctrineClientReadRepository::findById()` (~L60-85). Every read port method takes a `ClinicId`; the only cross-clinic SQL on `client__clients` is `ReindexSearchEntriesCommand` (pattern to copy, not reuse).
- Handlers: `CreateClientHandler` / `UpdateClientTaxProfileHandler` — `#[AsMessageHandler]`, `final readonly`, `get(ClinicId, ClientId)` → mutate with `clock->now()` → `save` → `domainEventPublisher->publish`.
- Events: `ClientTaxProfileUpdated` payload `clientId, clinicId, status, legalName, siren, vatNumber, firstName, lastName`; `ClientCreated` payload `clientId, clinicId, firstName, lastName, contactMethods, legalName` — **no status/SIREN/VAT**. Listener convention: `On<Event>`, `final readonly`, `#[AsMessageHandler(bus: 'messenger.bus.event')]`, `\assert`s on `payload()` (e.g. `src/Context/Client/Application/Search/OnClientTaxProfileUpdated.php`).

**Messaging** (`config/packages/messenger.yaml`)

- Transports: `async` (doctrine, `shared__messenger_messages`, retry 3 × multiplier 2), `failed`, `sync`. Routing: `DomainEventInterface` → sync, `IntegrationEventInterface` → async, mailer messages → async. **No async command exists.**
- Buses: command (Metadata, Context, Logging, validation, `doctrine_transaction`), query (no tx), event / integration_event (no tx). Scheduler messages go to the default (command) bus.
- `CommandBus` (`src/Shared/Infrastructure/Bus/Messenger/CommandBus.php` L42-62) uses `HandleTrait::handle()` → an envelope only *sent* throws "handled zero times". `CommandBusInterface::dispatch(object $command, object ...$stamps): mixed` cannot send async.
- Doctrine transport on MySQL polls `available_at <= now` on each worker loop: `DelayStamp` spacing of 1 s works (`check_delayed_interval` is PostgreSQL-only).
- Guards to respect: `MessengerRoutingGuardTest` (domain events sync only, integration events async only), `DoctrineTransportsConfigurationTest` (today: Doctrine receivers exactly `async` + `failed` — this slice changes it to `async`, `async_external`, `async_external_batch`, `failed`, Technical Decision 8), `MessengerHandlersRegistrationTest` (every `*Handler` under `Application/(Command|Query)/` carries the literal `#[AsMessageHandler]`), `TransactionalMessagingConventionTest` (no `DispatchAfterCurrentBusStamp`). A message sent to `async` from a sync listener is INSERTed inside the business transaction (implicit outbox) — same guarantee as integration events.
- Tests: no `in-memory://`; `async` is the real table under DAMA rollback; async effects asserted by rows in `shared__messenger_messages`; `RecordingCommandBus` (`tests/Support/`) decorates `CommandBusInterface` (`config/services_test.yaml` L17-20).

**Scheduler**

- `src/Shared/Infrastructure/Scheduler/DefaultScheduleProvider.php`: `#[AsSchedule('default')]`, `->lock($lockFactory->createLock('scheduler.default'))`, not stateful, `RecurringMessage::every('1 hour', new SchedulerHeartbeat())`. `LockFactory` autowires to `shared.lock.factory` (DoctrineDbalStore on `shared__lock_keys`, `config/services.yaml` L131-145).
- `compose.yaml` L80-99 `scheduler-worker` (profile `workers`, `restart: unless-stopped`): `messenger:consume scheduler_default --time-limit=3540 -vv`.
- `SchedulerWorkerTimeLimitTest` regex `/messenger:consume scheduler_default\b.*--time-limit=(\d+)/` still matches with `scheduler_client` appended; it instantiates only `DefaultScheduleProvider` → must also collect `ClientScheduleProvider`. `DefaultScheduleProviderTest` is the model for `ClientScheduleProviderTest`.
- `dragonmantank/cron-expression` is **not installed** → no `RecurringMessage::cron()`.

**External HTTP today**

- `GeoplateformeAddressDirectory` (Client): default `HttpClientInterface`, hard-coded URLs, per-request `timeout` 3 s, no User-Agent, swallows `ExceptionInterface` → `[]` / `null` (port contract: empty / null when unreachable), caches communes 24 h (failure → `expiresAfter(1)`).
- `GotenbergPdfRenderer` (Shared): default client, `%env(GOTENBERG_URL)%`, no timeout, maps transport errors to `PdfRenderingFailedException::unreachable()`, non-200 to `::rejected()`.
- No `http_client` config, no scoped clients, no env-based adapter selection anywhere, Monolog channels only `deprecation` + `messenger`, no `#[WithMonologChannel]` in src. `src/Shared/Infrastructure/Http/` already holds unrelated code (ReturnToResolver, API rate-limit / JSON-auth listeners).
- No kernel test hits Géoplateforme or Gotenberg today; their unit tests inject their own `MockHttpClient`.

**Presentation / front**

- Routes by attribute (`config/routes/clinic.yaml`, host clinic). JSON model: `ClientsBySirenController` (`/clients/by-siren`, `clinic_clients_by_siren`) and `CounterClientsController` (`/billing/counter-clients`). POST model: `UpdateClientTaxProfileController` (`/clients/{id}/tax-profile`, extends `AbstractClientSheetAction`: `guard()` CSRF `client_sheet`, `attempt()`, `addFlash`, `backToSheet($id, 'cFisc')`).
- Shared block `assets/js/shared/client-tax-profile.js` (`mount(root, state, onChange, opts)`; opts `sirenUrl, onCheck, self, lockFisc, hideStatus, structureOnly`), template `templates/clinic/clients/_tax_profile_block.html.twig`, CSS `assets/styles/components/tax-profile.css` (`.fisc-dbl` amber box). Mounted by `register.js:606` (counter, `structureOnly`), `create.js:391` (directory), `sheet.js` (card, manual mode only).
- Sheet card `assets/js/pages/clients/sheet.js` (L413-640) reads `S.fisc` from `ClientSheetBuilder::taxProfileView()` (L441): today `annuaireUrl`, `ent`, `vies`, `verif`, `meta` are null, `clientsSiret` empty, `trace`/`efacture` false. Search: `fetch(annuaireUrl?q=&type=)` expects a **bare array** of ent records `{siren, n, forme, naf, ass, cesse, ets[{siret,l,a}], ann}`; no keyboard navigation. `lireFisc` with an `ent` and `ann === null` prints « Pas encore inscrite à l'annuaire » (PPF) — wrong when the PPF directory was simply not consulted. `#fiscForm` has hidden inputs `fisc, rs, siren, tva` only.
- Counter (`assets/js/pages/billing/register.js`): `rendreCl()` (L626-640) shows a disabled « Annuaire des entreprises · SIRENE · bientôt » group (L634); selected pro rendered by `carteCliPro(c)` (L580-586) — a `<div class="vt-msg warn">` after `.vt-card` is the established warning slot (see `majPart` L663). Mock-up `facturation-v-topbar_5.html` `rendrePl()` (L3066-3078): group « Annuaire des entreprises · SIRENE », `button.vt-o[data-sir]` + `<code>` SIREN, excluding SIRENs already clients; short-query hint « Les entreprises viennent de la base SIRENE : raison sociale, SIREN, adresse et TVA sont repris tels quels. »
- `ClientCard` (`src/Presentation/Clinic/ViewModel/Directory/ClientLookupReader.php` L26) `{id, n, ini, tel, ville, adr, ani, url, rs, siren, tva, fisc, badge}` built from `ClientView` per client.
- Directory « Nouveau client » (`create.js`): BAN address autocomplete pattern (`debounce`, `getJson`, `.nw-sug`, `button.hl`); `repertoire-v-topbar_14.html` has no company autocomplete mock-up.
- Keyboard-navigable listbox exists only in `assets/controllers/client_search_autocomplete_controller.js` (Stimulus, `role="option"`, `aria-selected`) and catalog/stock pickers — pattern to copy, no shared module.

**Fixtures**: `fixtures/Context/Client/Story/ClientDataStory.php` L420-440 structures: Élevage des Calanques `523456788` / `FR31523456788` (active), Centre équestre du Lac `812345676` / no VAT, Calanques — site d'Aubagne `523456788` / no VAT, GAEC du Mont `441569738` / `FR14441569738` (archived).

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Client/Domain/Client.php` | Aggregate: `updateTaxProfile` (L141-162), `create`, `reconstitute`, events |
| `src/Context/Client/Domain/ValueObject/ClientTaxProfile.php` | Tax profile VO (slice 0) |
| `src/Shared/Domain/ValueObject/{Siren,FrenchVatNumber}.php` | Local checks, key computation |
| `src/Context/Client/Domain/Event/{ClientCreated,ClientTaxProfileUpdated}.php` | Payloads |
| `src/Context/Client/Infrastructure/Persistence/Doctrine/{Entity/ClientEntity,Mapper/ClientMapper,Repository/DoctrineClientRepository,Repository/DoctrineClientReadRepository}.php` | Persistence to extend |
| `migrations/Client/Version20261017110000.php` | Migration style |
| `src/Context/Client/Application/Command/UpdateClientTaxProfile/UpdateClientTaxProfileHandler.php` | Handler style |
| `src/Context/Client/Application/Search/OnClientTaxProfileUpdated.php` | Domain-event listener style |
| `src/Context/Client/Application/Port/AddressDirectoryInterface.php` + `Infrastructure/Adapter/AddressDirectory/GeoplateformeAddressDirectory.php` + its test | Existing external port/adapter/test |
| `src/Shared/Infrastructure/Pdf/GotenbergPdfRenderer.php` + `tests/Unit/Shared/Infrastructure/Pdf/GotenbergPdfRendererTest.php` | Existing HTTP adapter + MockHttpClient test style |
| `src/Shared/Infrastructure/Bus/Messenger/CommandBus.php`, `src/Shared/Application/Bus/CommandBusInterface.php` | Why a separate async port is needed |
| `src/Shared/Infrastructure/Scheduler/DefaultScheduleProvider.php` + `tests/Unit/Shared/Infrastructure/Scheduler/{DefaultScheduleProviderTest,SchedulerWorkerTimeLimitTest}.php` | Schedule + guards |
| `src/Shared/Infrastructure/Console/ReindexSearchEntriesCommand.php` | Cross-clinic DBAL query pattern |
| `config/packages/{messenger,monolog,framework,cache}.yaml`, `config/services.yaml`, `config/services_test.yaml`, `compose.yaml`, `.env`, `.env.test` | Wiring |
| `tests/Integration/System/Messenger/*GuardTest.php`, `tests/Integration/Shared/{MessengerHandlersRegistrationTest,TransactionalMessagingConventionTest}.php` | Guards new code must pass |
| `src/Presentation/Clinic/Controller/Client/Profile/ClientsBySirenController.php`, `.../Client/Sheet/{UpdateClientTaxProfileController,AbstractClientSheetAction}.php` | Controller models |
| `src/Presentation/Clinic/ViewModel/Client/ClientSheetBuilder.php` (`taxProfileView` L441), `.../Directory/ClientLookupReader.php` | View models to feed |
| `assets/js/shared/client-tax-profile.js`, `templates/clinic/clients/_tax_profile_block.html.twig`, `assets/js/pages/clients/{sheet,create}.js`, `assets/js/pages/billing/register.js` | Front call sites |
| `docs/frontend-theme/kiveto-theme/client-v-topbar-busy_6.html` (SIRENE fixture L2976-2992), `facturation-v-topbar_5.html` (`rendrePl` L3066-3078) | Mock-ups |
| `fixtures/Context/Client/Story/ClientDataStory.php` | Demo SIRENs for the fixture adapters |
| `src/Shared/README.md` (sections L5-74 English, then French), `src/Context/Client/README.md` (Changelog) | Docs to update |

### Technical Decisions

User decisions (Step 1, party-mode rounds, adversarial review):

- Nightly schedule owned by Client, consumed by the existing `scheduler-worker` (`messenger:consume scheduler_default scheduler_client --time-limit=3540`).
- Sheet card: busy_6 wired to real data + reference extras (« Revérifier », « Utiliser le nom officiel », warning badges); establishments and e-invoicing addresses stay « bientôt ».
- Autocomplete in the counter, the directory window and the sheet card; plain JS modules (no Stimulus).
- 30-day re-verification; name mismatch after normalisation; registry name wins, VIES name ignored; 429 rate monitored through `external_api` logs.
- Legal forms: static INSEE table committed with source and version; unknown code shown raw.
- **Never a network call inside a database transaction.** Background calls to external services (verifications, later the Super PDP sync) run as `ExternalApiCommandInterface` messages on the `async_external*` transports and their own worker; the only synchronous external call allowed is a read-only lookup answering a user in a web request (the registry search), outside any transaction.
- Coverage: CLAUDE.md §3 — every touched BC (Client **and Shared**) at 100 % line coverage **as a whole**, Presentation excluded.

**External HTTP foundations (Shared)**

1. **Location**: `src/Shared/Application/ExternalApi/` (contracts) and `src/Shared/Infrastructure/ExternalApi/` (implementations) — not `Shared/Infrastructure/Http`, which already holds unrelated code.
2. **`ExternalServiceUnavailable`** (`Shared/Application/ExternalApi`): `\RuntimeException` with `service`, `?int retryAfter`, named constructors `rateLimited(service, ?retryAfter)` (remote **429** only), `unreachable(service, previous)` (transport error / timeout), `failed(service, status)` (**503** and any other 5xx), `refused(service, reason)` (API-level outage code, e.g. VIES `MS_UNAVAILABLE`), `throttled(service)` (local limiter); `isThrottled(): bool` is true **only** for `throttled` and `rateLimited` (local limiter or remote 429) — a 503 is an outage (`down`), never « throttled ».
3. **`RetryAfterAwareClient`** (`Shared/Infrastructure/ExternalApi`): wraps one scoped `HttpClientInterface` + a service name + the `external_api` logger; `request(method, path, options): ExternalApiResponse(status, ?array data)`; 429 → `rateLimited` with `Retry-After` parsed (delta-seconds or HTTP-date, else null); 503 and other 5xx → `failed`; transport error/timeout → `unreachable`; otherwise the response is returned (the adapter interprets 2xx/4xx). Never retries. One log record per call: `info` (ok) / `warning` (unavailable), context `service`, `method`, `path` without query string, `status`, `duration_ms`, `outcome` (`ok|rate_limited|failed|unreachable`); never bodies, headers or query parameters.
4. **Scoped clients**: `framework.http_client.scoped_clients` — `registry.client` (base_uri `https://recherche-entreprises.api.gouv.fr`) and `vies.client` (base_uri `https://ec.europa.eu/taxation_customs/vies/rest-api/`), headers `User-Agent: %env(EXTERNAL_API_USER_AGENT)%` + `Accept: application/json`, `timeout: 2` (idle; also bounds a stalled connect — Symfony has no portable connect timeout), `max_duration: 5`, no `retry_failed`.
5. **User-Agent contact checked at use, not at start**: `ExternalApiUserAgent` (`Shared/Infrastructure/ExternalApi`, injected service built from `%env(EXTERNAL_API_USER_AGENT)%`, no static state) with `withContact(): string` throwing `\LogicException('EXTERNAL_API_USER_AGENT must contain a contact (e-mail or URL) to call <service>')` when the value holds neither an e-mail address nor an http(s) URL. `RechercheEntreprisesCompanyRegistry` and `ViesVatNumberVerifier` call it in their constructors: since they are only built on demand through the selection locator, in `api` / `vies` mode, the first real use without a contact fails clearly; `cache:clear`, migrations and fixture mode are never affected.
6. **Logs**: Monolog channel `external_api` (prod: dedicated `stream` handler on `php://stderr`, level `info`, json, `channels: [external_api]`; dev: through `main`). Symfony's own `http_client` channel logs full URLs (query strings with names / SIRENs, `CurlHttpClient` « Request: "GET <url>" »): it is **excluded** from every handler: dev `main` (`["!event", "!http_client"]`) and dev `console` (`["!event", "!doctrine", "!console", "!http_client"]`), prod `fingers_crossed` (`["!deprecation", "!http_client"]`) and prod `console` (`["!event", "!doctrine", "!http_client"]`) — workers run with `-vv`, so the console handlers matter — and it gets no handler of its own.
7. **Test network guard**: `when@test: framework.http_client.mock_response_factory: App\Tests\Support\UnmockedHttpCallGuard` (decorates `http_client.transport`, so scoped clients are covered); adapter unit tests inject their own `MockHttpClient`. Géoplateforme and Gotenberg keep their wiring (no kernel test calls them; listed in the README as next adopters).
8. **External-call commands — dedicated transports, bus and worker**:
   - Transports `async_external` (`doctrine://default?queue_name=external`) and `async_external_batch` (`doctrine://default?queue_name=external_batch`): same table `shared__messenger_messages`, `auto_setup: false`, business connection (implicit outbox kept), retry `max_retries: 3`, multiplier 2, failure transport `failed`. `DoctrineTransportsConfigurationTest` now expects receivers exactly `async`, `async_external`, `async_external_batch`, `failed`.
   - Routing: `ExternalApiCommandInterface → async_external` (interactive: creation, identifier change, « Revérifier »). Batch sends (nightly run) override it with `new TransportNamesStamp(['async_external_batch'])`.
   - Bus `messenger.bus.external_api`: Metadata, Context, Logging, `validation` — **no `doctrine_transaction`**. Messages carry the `BusNameStamp` added by Messenger's default `add_bus_name_stamp_middleware`, so the worker hands them back to this bus. `CommandBus::dispatch()` refuses an `ExternalApiCommandInterface` (`\LogicException`): it would be stamped for the command bus and find no handler.
   - Marker `ExternalApiCommandInterface` (`Shared/Application/Bus`); port `ExternalApiCommandBusInterface::send(ExternalApiCommandInterface $command, object ...$stamps): void` implemented by `Shared/Infrastructure/Bus/Messenger/ExternalApiCommandBus` (dispatch on `messenger.bus.external_api`, no `HandleTrait`); handlers carry exactly `#[AsMessageHandler(bus: 'messenger.bus.external_api')]`.
   - Worker: new compose service `external-worker` (profile `workers`, `restart: unless-stopped`, same env as `messenger-worker`) running `messenger:consume async_external async_external_batch --time-limit=3600 --memory-limit=256M -vv` — Messenger always drains the first receiver before the second, so interactive verifications pass ahead of the nightly batch. `Makefile` `WORKER_SERVICES` gains it. E-mails and integration events (`async`) never queue behind external calls, and vice versa. Slice 1 / 4 (Super PDP) reuse these bricks as they are.
   - Guards: every concrete `ExternalApiCommandInterface` routes to exactly `['async_external']`; every handler bound to `messenger.bus.external_api` handles an `ExternalApiCommandInterface` and each such command has one; `MessengerHandlersRegistrationTest` accepts that literal attribute besides the bare one. The guards scan `src/` **and** the test probes (`tests/Integration/System/Messenger/Probe/`, probe command + probe handler registered in `services_test.yaml`), so they are meaningful on B1 already.
   - Failures: an `OptimisticLockException` from the record step retries the whole verify message (both HTTP calls again — acceptable, rare); a missing User-Agent contact (`\LogicException` at adapter construction) is retried 3× then lands in `failed`, visible in the logs — a deploy misconfiguration, not a runtime path.

**Client verification — domain and flows**

9. **Value objects** (`src/Context/Client/Domain/TaxProfile/`): `CompanyCheckOutcome` (`unchecked|active|ceased|confidential|not_found|unavailable` — `confidential` = non-diffusible company, SIREN known but data masked), `VatCheckOutcome` (`unchecked|valid|invalid|not_applicable|unavailable`), `CompanyCheck(outcome, ?registryName, ?checkedAt)`, `VatCheck(outcome, ?checkedAt)`, `TaxProfileVerification(company, vat, ?requestedAt)` (`unchecked()`, `isStale()`, `warnings()`, `isPending(now): bool` = requested less than 24 h ago), `TaxProfileWarning` (`unverified|company_ceased|company_not_found|company_confidential|vat_invalid|name_mismatch`, French `label()`: « Vérification en attente », « Entreprise radiée selon le registre », « SIREN introuvable dans le registre », « Entreprise non diffusible au registre » (neutral badge), « N° de TVA non reconnu par VIES », « Nom différent du registre »; `shownAtCounter()` true for ceased, not_found, vat_invalid only). `confidential` follows the normal 30-day cycle like `active` (diffusion status can change); no name comparison (no registry name). The official name is shown separately as « Nom officiel : <registryName> » next to the name-mismatch badge.
10. **`LegalNameNormalizer`** (`Domain/TaxProfile`, static `normalize(string): string`, case-table tested): lower case; accents removed; « & » → « et »; punctuation → space; whitespace collapsed; leading article removed (« le », « la », « les », « l' »); one legal form removed at the start or the end (SAS, SASU, SARL, EURL, EARL, GAEC, SCEA, SA, SCI, SNC, EI, « association »). Name mismatch ⇔ normalised entered name ≠ normalised registry name.
11. **`ClientTaxIdentifiersChanged`** (domain event, payload `clientId, clinicId, taxStatus, previousSiren, siren, previousVatNumber, vatNumber`): recorded by `Client::create()` when the profile has a SIREN and by `updateTaxProfile()` when SIREN or VAT change. In the same step the aggregate resets the verification to unchecked and sets `requestedAt = $now` when the new profile is french_business with a SIREN. `ClientCreated` / `ClientTaxProfileUpdated` unchanged; a legal-name-only change (« Utiliser le nom officiel ») neither resets nor re-verifies.
12. **Request de-duplication — `tax_verification_requested_at`**: set when a verification is queued (identifier change, nightly run, « Revérifier »), cleared when a result is stored or when the client is no longer verifiable (archived, individual). « Revérifier » on a profile requested less than 24 h ago queues nothing and answers « Vérification déjà en cours ». The stale finder ignores profiles requested less than 24 h ago (a lost message is retried the next night after that).
13. **Verify / Record split** (no transaction during HTTP calls):
    - `VerifyClientTaxProfile(clinicId, clientId)` implements `ExternalApiCommandInterface` — handled on `messenger.bus.external_api` by the `external-worker`, **no transaction**: reads the profile through `ClientReadRepositoryInterface::findById()` (`ClientView`), stops when not found / archived / not french_business / no SIREN, calls `findBySiren` and `verify` (each `ExternalServiceUnavailable` → `Unavailable` outcome, never rethrown; each check independent), then dispatches `RecordTaxProfileVerification` through `CommandBusInterface`.
    - `RecordTaxProfileVerification(clinicId, clientId, checkedSiren, ?checkedVatNumber, companyOutcome, ?registryName, companyCheckedAt, vatOutcome, vatCheckedAt)` — plain command, default bus, **transactional, short, no network**: `get()` → `Client::recordTaxProfileVerification(...)` → `save` → `publish`. On a late answer (checked SIREN/VAT no longer current) **nothing changes** — the newer request's marker stays; on an archived or individual client nothing is stored and the marker is cleared. A concurrent commit fails the flush on `#[ORM\Version]` → `OptimisticLockException` propagates → Messenger retries the verify message against fresh data.
    - `recordTaxProfileVerification` never touches `updatedAt`; records `ClientTaxProfileVerified` when it stores a result.
14. **« Revérifier » — `RequestTaxProfileVerification(clinicId, clientId): VerificationRequestOutcome`** (plain command, transactional): `get()` (unknown or other clinic → `ClientNotFoundException`, mapped to **404** by the controller, nothing queued); archived → `archived` (flash « Client archivé », redirect, nothing queued); not french_business / no SIREN → `not_applicable`; pending (< 24 h) → `already_pending` (flash « Vérification déjà en cours »); otherwise `Client::requestTaxProfileVerification($now)` sets `requestedAt`, save, `ExternalApiCommandBusInterface::send(new VerifyClientTaxProfile(...))` → `queued` (flash « Vérification lancée : le résultat s'affichera sur la fiche d'ici quelques minutes. »). The controller dispatches **outside** `attempt()` (which catches `\DomainException`, the parent of `ClientNotFoundException`, and returns a bool) and maps the outcome itself.
15. **Nightly run**: `ReverifyStaleTaxProfiles` (scheduled message, default command bus, transactional, no network) uses `TaxProfileVerificationScheduleInterface`:
    - `findStale(checkedBefore: now − 30 days, requestedBefore: now − 24 h): list<array{clinicId, clientId}>` — DBAL, all clinics: `tax_status = 'french_business' AND siren IS NOT NULL AND status = 'active' AND (tax_verification_requested_at IS NULL OR tax_verification_requested_at < :requestedBefore) AND (tax_company_checked_at IS NULL OR tax_company_check_outcome IS NULL OR tax_company_check_outcome = 'unavailable' OR tax_vat_check_outcome IS NULL OR tax_vat_check_outcome = 'unavailable' OR tax_company_checked_at < :checkedBefore)` (null outcome ⇔ unchecked), `ORDER BY tax_company_checked_at` NULLs first.
    - `markRequested(list<string> $clientIds, \DateTimeImmutable $now, \DateTimeImmutable $requestedBefore): list<string>` — in chunks of 500 ids: `UPDATE … SET tax_verification_requested_at = :now, version = version + 1 WHERE id IN (:chunk) AND (tax_verification_requested_at IS NULL OR tax_verification_requested_at < :requestedBefore)` (the de-dup predicate repeated, so a request committed meanwhile is not doubled), then returns the ids whose marker equals `:now` (bumping `version` makes a concurrent sheet save conflict instead of erasing the marker).
    - One `send(new VerifyClientTaxProfile(...), new TransportNamesStamp(['async_external_batch']))` per returned id — no `DelayStamp`, no cap: the pace comes from the sequential `external-worker` and the worker budgets (Decision 18), and the batch queue has the lowest priority. A second run right after finds nothing (all requested < 24 h).
16. **Schedule**: `ClientScheduleProvider` in **`src/Context/Client/Infrastructure/Scheduler/`** (mirrors `Shared/Infrastructure/Scheduler/DefaultScheduleProvider`): `#[AsSchedule('client')]`, lock `scheduler.client` (`shared.lock.factory`), not stateful, one `RecurringMessage::every('1 day', new ReverifyStaleTaxProfiles(), from: new \DateTimeImmutable('today 02:17', new \DateTimeZone('Europe/Paris')))` (no cron library installed). Known gaps, both caught up the next night by the stale finder: a DST change shifts the run by one hour; a worker restart (every 3540 s) that lands on 02:17 skips that day's run (non-stateful schedule).
17. **Folders**: the feature folder `src/Context/Client/Application/Verification/` is built like the existing `Application/Search/` and holds the listener and the internal schedule port (`TaxProfileVerificationScheduleInterface`); the external-service ports and DTOs go to `Application/Port/` next to `AddressDirectoryInterface`; commands in `Application/Command/<Name>/`, queries in `Application/Query/<Name>/`; adapters in `Infrastructure/{Registry,Vat,Scheduler,Persistence}`.

**Registry, VIES, cache, limiter**

18. **Budgets — per container, local lock, built by hand**: no `framework.rate_limiter` entry (with `framework.lock: false` any non-null `lock_factory` throws at compile time, `FrameworkExtension.php` L3447-3453) and `framework.lock` stays disabled. In `config/services.yaml`: `client.external_limiter.lock_factory` = `Symfony\Component\Lock\LockFactory` on a `Symfony\Component\Lock\Store\FlockStore` (local to the container), `client.external_limiter.storage` = `Symfony\Component\RateLimiter\Storage\CacheStorage` on `cache.app` (filesystem: shared by the processes of one container), and three `Symfony\Component\RateLimiter\RateLimiterFactory` services (config array, storage, lock factory), all token bucket with a **global constant key** (`create('global')`): `client.limiter.registry_web` (`limit: 4`, rate 4 per second), `client.limiter.registry_worker` (`limit: 1`, rate 1 per second), `client.limiter.vies_worker` (`limit: 1`, rate 1 per second). The autowired `Symfony\Component\Lock\LockFactory` alias stays `shared.lock.factory` (scheduler lock) — guarded by a container test.
    - `ThrottledCompanyRegistry` (chain `Cached → Throttled → RechercheEntreprises`): `search()` uses the **web** limiter, non-blocking `consume()` → `ExternalServiceUnavailable::throttled()` when empty; `findBySiren()` uses the **worker** limiter, blocking `reserve()->wait()`.
    - `ViesVatNumberVerifier` waits on `client.limiter.vies_worker` (`reserve()->wait()`) before each call.
    - 4 + 1 = 5 req/s for the registry, under the API's 7; the remote 429 stays the last safeguard. **Known limit**: with several web containers, divide the web budget or move to shared storage (same work as for the cache).
19. **Adapter selection**: `CLIENT_COMPANY_REGISTRY` (fixture|api) and `CLIENT_VAT_VERIFIER` (fixture|vies), default `fixture` in `.env`, forced in `.env.test`; each port built by a service locator used as factory (`client.company_registry.candidates` `{fixture, api}` → `CompanyRegistryInterface: { factory: ['@client.company_registry.candidates', 'get'], arguments: ['%env(CLIENT_COMPANY_REGISTRY)%'] }`; same for VIES `{fixture, vies}`). Unknown mode fails on first use.
20. **Registry API**: `GET /search?q=…&per_page=N&page=1&minimal=true&include=siege,complements`; `findBySiren` = same with the SIREN as `q`, exact `siren` match. Fields: `siren`, `nom_raison_sociale` (fallback `nom_complet`), `nature_juridique`, `activite_principale`, `etat_administratif` (A|C), `date_fermeture`, `siege.{siret, adresse, code_postal, libelle_commune}`, `complements.est_association`. Masked / non-diffusible → `findBySiren` returns a `CompanyRecord` with `isConfidential = true` (outcome `confidential`), still listable in search when a SIREN is present; unknown SIREN → null (`not_found`).
21. **VIES REST**: `POST check-vat-number` `{"countryCode":"FR","vatNumber":"<key><SIREN>"}`; `valid` → valid/invalid; `userError` / `errorWrappers[].error` in `MS_UNAVAILABLE, SERVICE_UNAVAILABLE, TIMEOUT, GLOBAL_MAX_CONCURRENT_REQ(_TIME), MS_MAX_CONCURRENT_REQ(_TIME)` → `refused`; `INVALID_INPUT` → invalid.
22. **Cache**: `CachedCompanyRegistry` on `cache.app` caches **`search()` only** (1 h); `findBySiren()` passes through uncached, so a verification (« Revérifier », nightly, after a retry) always reads fresh data; exceptions are never cached.
23. **Legal forms**: `src/Context/Client/Infrastructure/Registry/LegalForms.php` — full INSEE « catégories juridiques » niveau III list (source URL + version in the header), short labels for the common clientele (EARL, GAEC, SCEA, SARL, EURL, SAS, SASU, SA, SCI, EI, Association…), INSEE label otherwise, raw code when unknown.
24. **Recorded fixtures**: adapter tests first use hand-written JSON (`"_source": "hand-written, replace by recorded"`); the manual run replaces them; `RecordedApiFixturesTest` (fails on any remaining `"_source": "hand-written`) is added in the **same commit** as the recording, so earlier commits stay green; without the manual run the stack is not mergeable.

**Presentation and front**

25. **Search endpoint** `GET /clients/company-registry/search?q=` (`clinic_clients_company_registry_search`) → **`{available: bool, reason: 'throttled'|'down'|null, items: CompanyHit[]}`** everywhere; `CompanyHit = {siren, n, forme, naf, ass, cesse, ets: [{siret, l: 'Siège', a: "<addressLine>, <postalCode> <city>"}], tva}` (`ann` omitted). < 3 letters and not 9/14 digits → `{available: true, reason: null, items: []}` without a call. Front: `throttled` keeps the last hits and opens nothing; `down` shows « Recherche indisponible, saisie manuelle » and opens manual entry (card).
26. **Card contract** (`ClientSheetBuilder::taxProfileView`, L441): `annuaireUrl`, `verifyUrl`, `vies` (valid → true, invalid → false, else null), `verif` (« 12/11 à 08:12 », null when never), **`verification = {company, vat, registryName, pending: bool, warnings: list<{code, label}>}`**. Front: `ann` absent → « Annuaire de la facturation électronique · pas encore consulté » (`ann === null` keeps « Pas encore inscrite » for slice 4); `cesse` without a date → « Entreprise radiée selon le registre ».
27. **Card behaviour**: « Saisir à la main » always visible under the search, opened automatically on `reason: down`; on edit of an existing professional, one search by SIREN (exact hit → company mode, else manual mode prefilled); on pick, duplicate-SIREN check through `dblUrl`; badges from `verification.warnings` (name mismatch shows « Nom officiel : X » + « Utiliser le nom officiel », which posts the four fields `fisc`, `rs` = registry name, `siren`, `tva`); last-check line « Vérifié le 12/11 · actif · TVA valide » or « Vérification en cours » when `pending`; « Revérifier » (hidden when archived).
28. **Counter**: live « Annuaire des entreprises · SIRENE » group (mock-up `rendrePl`), pick → « Nouveau professionnel » prefilled; `ClientCard.warn` = first warning with `shownAtCounter()`, rendered under `.vt-card`, never blocking.
29. **Directory window**: shared block option `registryUrl` (+ `onCompany(hit)`), listbox under the legal name when `fisc === PRO || structureOnly`, VAT suggestion « à confirmer — certaines entreprises n'ont pas de n° de TVA », association hint « Structure non assujettie ? ».
30. **Concurrent sheet saves**: `AbstractSheetAction::attempt()` also catches `Doctrine\ORM\OptimisticLockException` → flash « La fiche vient d'être mise à jour, réessayez. » (a verification recorded meanwhile no longer turns a user's save into a 500).

## Implementation Plan

Three stacked branches (as in slice 0): **B1 `chore/external-api-foundations`** from `master` (Tasks 1–7) → **B2 `feature/client-company-verification`** rebased on B1 (backend, Tasks 8–19; the branch exists from `master` and holds the committed draft of this spec — rebase it on B1 before Task 8) → **B3 `feature/client-company-verification-ui`** from B2 (Tasks 20–25, then Task 26 closing for the whole stack). One commit per task (or tight group), `make ci` green before each commit, English code/comments, French UI strings. No merge, no push.

### Tasks

**B1 — `chore/external-api-foundations`**

- [ ] Task 1: `ExternalServiceUnavailable`, `ExternalApiResponse`, `RetryAfterAwareClient`, `ExternalApiUserAgent`
  - Files (new): `src/Shared/Application/ExternalApi/ExternalServiceUnavailable.php`, `src/Shared/Infrastructure/ExternalApi/{ExternalApiResponse,RetryAfterAwareClient,ExternalApiUserAgent}.php`
  - Action: per Technical Decisions 2, 3 and 5.
  - Test: `tests/Unit/Shared/Application/ExternalApi/ExternalServiceUnavailableTest.php` (`isThrottled()` true only for `throttled` and `rateLimited`); `tests/Unit/Shared/Infrastructure/ExternalApi/RetryAfterAwareClientTest.php` (`MockHttpClient`: 200 JSON, 200 non-JSON, 400 JSON, 429 + `Retry-After: 30`, 429 + HTTP-date, 503 → `failed` and **not** throttled, 500, `TransportException`, timeout; Monolog `TestHandler`: one record per call, correct context, no body / header / query string); `ExternalApiUserAgentTest` (e-mail, URL, neither → `\LogicException` naming the variable and the service).

- [ ] Task 2: logging
  - Files: `config/packages/monolog.yaml`
  - Action: per Technical Decision 6 (`external_api` channel + prod handler; `http_client` excluded from every dev and prod handler, console handlers included).
  - Test: `tests/Integration/Shared/Infrastructure/ExternalApi/LoggingConfigurationTest.php` (KernelTestCase): `monolog.logger.external_api` exists (through a public test alias in `config/services_test.yaml`, since nothing consumes it in B1); the **compiled** configuration (`MonologExtension` processed config for `dev` and `prod`, e.g. via `ContainerBuilder` + `Processor` on the bundle configuration) shows every handler excluding `http_client`.

- [ ] Task 3: test-env network guard
  - Files: `tests/Support/UnmockedHttpCallGuard.php` (new), `config/packages/framework.yaml`, `config/services_test.yaml`
  - Action: per Technical Decision 7.
  - Test: `tests/Integration/Shared/Infrastructure/ExternalApi/NetworkGuardTest.php`: default `http_client` → `\LogicException` « Unmocked HTTP call in tests: GET <url> ».

- [ ] Task 4: external-call transports, bus, port, worker
  - Files: `config/packages/messenger.yaml`, `src/Shared/Application/Bus/{ExternalApiCommandInterface,ExternalApiCommandBusInterface}.php`, `src/Shared/Infrastructure/Bus/Messenger/ExternalApiCommandBus.php` (new), `src/Shared/Infrastructure/Bus/Messenger/CommandBus.php` (refuse `ExternalApiCommandInterface`), `config/services.yaml`, `config/services_test.yaml`, `tests/Support/RecordingExternalApiCommandBus.php` (new), `tests/Integration/System/Messenger/Probe/{ProbeExternalApiCommand,ProbeExternalApiCommandHandler}.php` (new), `compose.yaml`, `Makefile`
  - Action: per Technical Decision 8. `RecordingExternalApiCommandBus` decorates the port in test (`recorded(class)`, `reset()`, forwards to the inner bus so the outbox row is still written). Public test aliases for the port and the bus; the probe handler registered in `services_test.yaml`.
  - Test: `tests/Unit/Shared/Infrastructure/Bus/Messenger/ExternalApiCommandBusTest.php` (stamps forwarded, right bus); extend `CommandBusTest` (refuses an `ExternalApiCommandInterface`); `tests/Integration/Shared/Infrastructure/Bus/ExternalApiCommandOutboxTest.php` using the nested-savepoint pattern of `AsyncTransportTransactionalityTest` (DAMA wraps every test in a transaction): inner commit → one row `queue_name = 'external'`, none on `default`; inner rollback → none; with `TransportNamesStamp(['async_external_batch'])` → `queue_name = 'external_batch'`; priority: with batch rows queued first and then one interactive row, a `Worker` on receivers `[async_external, async_external_batch]` handles the interactive one first.

- [ ] Task 5: messenger guards
  - Files: `tests/Integration/System/Messenger/{MessengerRoutingGuardTest,DoctrineTransportsConfigurationTest}.php`, `tests/Integration/Shared/MessengerHandlersRegistrationTest.php`, `tests/Integration/System/Messenger/ExternalApiBusGuardTest.php` (new), `tests/Integration/Shared/Infrastructure/Console/ComposeWorkersTest.php` (new, or extend an existing compose test)
  - Action: per Technical Decision 8 (routing exactly `['async_external']`; receivers exactly `async`, `async_external`, `async_external_batch`, `failed`, all Doctrine, `auto_setup` false, same table and connection; bus/handler pairing scanning `src/` and the test probes; literal attribute accepted); compose: `external-worker` consumes `async_external async_external_batch` in that order, `messenger-worker` consumes neither, both in profile `workers` with `restart: unless-stopped`.

- [ ] Task 6: bring `src/Shared` to 100 %
  - Files: tests for the pre-existing uncovered Shared classes found by clover (at the time of writing: `SearchEngine`, `IpRateLimitListener`, `JsonAuthenticationFailureSubscriber`, `SearchTermNormalizer`, `SearchQuery`, under `tests/Unit/Shared/...` / `tests/Integration/Shared/...`)
  - Action: CLAUDE.md §3 applies to the whole BC; cover every remaining line (no `@codeCoverageIgnore`). Measure with clover XML (`--coverage-clover`), not `--coverage-text`.

- [ ] Task 7: Shared README
  - File: `src/Shared/README.md`
  - Action: English section « External APIs » after « Scheduled tasks »: scoped client per service, `RetryAfterAwareClient`, `ExternalServiceUnavailable` (outage ≠ empty result; throttled vs down), fixture adapter + env var + service locator, recorded JSON fixtures under `tests/Fixtures/<service>/`, network guard, User-Agent contact checked at use, `external_api` log channel and the `http_client` exclusion, **never a network call inside a database transaction**, **every external call is an `ExternalApiCommandInterface` on `async_external` with its own worker**, then a short transactional command records the result; Géoplateforme and Gotenberg named as not yet migrated. Add the new guards to the guard table.

**B2 — `feature/client-company-verification`** (rebased on B1; backend)

- [ ] Task 8: value objects and normalizer
  - Files (new, `src/Context/Client/Domain/TaxProfile/`): `CompanyCheckOutcome.php`, `VatCheckOutcome.php`, `CompanyCheck.php`, `VatCheck.php`, `TaxProfileVerification.php`, `TaxProfileWarning.php`, `LegalNameNormalizer.php`
  - Action: per Technical Decisions 9 and 10. `isStale(now, maxAge)`: company unchecked/unavailable, VAT unchecked/unavailable, or company `checkedAt < now − maxAge`. `warnings(entered)` order: company_ceased | company_not_found | company_confidential, vat_invalid, name_mismatch (company active or ceased with a registry name), unverified (any unchecked/unavailable, once). `confidential` is a definite outcome for `isStale()` (30-day cycle).
  - Test: `tests/Unit/Context/Client/Domain/TaxProfile/{TaxProfileVerificationTest,LegalNameNormalizerTest,TaxProfileWarningTest}.php` — staleness, `isPending`, warnings **matrix** (company × VAT × same/different name), normalizer case table.

- [ ] Task 9: aggregate
  - Files: `src/Context/Client/Domain/Client.php`, `src/Context/Client/Domain/Event/{ClientTaxIdentifiersChanged,ClientTaxProfileVerified}.php` (new)
  - Action: property `TaxProfileVerification $taxProfileVerification` (create: unchecked, `requestedAt = $createdAt` when french_business with SIREN; `reconstitute`: new last optional argument); getter; `updateTaxProfile()` per Technical Decision 11; `recordTaxProfileVerification(string $checkedSiren, ?string $checkedVatNumber, TaxProfileVerification $result): bool` per Technical Decision 13; `requestTaxProfileVerification(\DateTimeImmutable $now): VerificationRequestOutcome` per Technical Decision 14 (enum `VerificationRequestOutcome` `queued|already_pending|archived|not_applicable` in `Domain/TaxProfile`). None of them touches `updatedAt`.
  - Test: extend `tests/Unit/Context/Client/Domain/ClientTaxProfileUpdateTest.php`; new `ClientTaxIdentifiersChangedTest.php` (create with/without SIREN; SIREN change, VAT change → recorded with previous values + reset + requested; name-only change and no-op → nothing), `ClientTaxProfileVerificationTest.php` (stored + event + requested cleared; late answer → nothing changes, newer marker kept; archived / individual → nothing stored, marker cleared; `updatedAt` unchanged), `ClientTaxProfileVerificationRequestTest.php` (every outcome; 24 h boundary); extend `tests/Unit/Context/Client/Domain/Event/ClientEventsTest.php`.

- [ ] Task 10: persistence and read model
  - Files: `src/Context/Client/Infrastructure/Persistence/Doctrine/{Entity/ClientEntity,Mapper/ClientMapper,Repository/DoctrineClientRepository,Repository/DoctrineClientReadRepository}.php`, `src/Context/Client/Application/Query/GetClientById/ClientView.php`, `migrations/Client/Version20261020090000.php` (new), `fixtures/Context/Client/Factory/ClientEntityFactory.php`
  - Action: nullable columns `tax_company_check_outcome VARCHAR(20)`, `tax_company_registry_name VARCHAR(255)`, `tax_company_checked_at DATETIME`, `tax_vat_check_outcome VARCHAR(20)`, `tax_vat_checked_at DATETIME`, `tax_verification_requested_at DATETIME` (all `datetime_immutable`), index `idx_client_tax_checked (tax_company_checked_at)`; null outcome ⇔ unchecked. Mapper both ways; `save()` copies the six fields. `ClientView` gains (last, defaulted) `taxCompanyCheck`, `taxRegistryName`, `taxCompanyCheckedAt` (ATOM), `taxVatCheck`, `taxVatCheckedAt`, `taxVerificationPending` (bool), `taxWarnings` (list of `TaxProfileWarning` values, computed by the read repository through the domain for french_business clients, `[]` otherwise). Factory `withTaxVerification(CompanyCheckOutcome, ?registryName, ?companyCheckedAt, VatCheckOutcome, ?vatCheckedAt, ?requestedAt = null)`. Hand-written migration with `down()`.
  - Test: extend `ClientMapperTaxProfileTest`, `DoctrineClientTaxProfileTest` (round trip, reset and requested persisted), `DoctrineClientReadRepositoryTest` (view fields, pending, warnings).

- [ ] Task 11: ports and DTOs
  - Files (new): `src/Context/Client/Application/Port/{CompanyRegistryInterface,CompanySummary,CompanyRecord,VatNumberVerifierInterface,VatVerificationResult}.php`, `src/Context/Client/Application/Verification/TaxProfileVerificationScheduleInterface.php`
  - Action: as in the reference, plus `CompanySummary::legalFormLabel`, `CompanySummary::ceasedOn` (Y-m-d), `CompanyRecord(siren, ?legalName, isActive, isConfidential, ?ceasedOn)`; `TaxProfileVerificationScheduleInterface::findStale(\DateTimeImmutable $checkedBefore, \DateTimeImmutable $requestedBefore): list<array{clinicId: string, clientId: string}>` and `markRequested(list<string> $clientIds, \DateTimeImmutable $now, \DateTimeImmutable $requestedBefore): list<string>`. PHPDoc: outage ≠ empty, throttled vs down.

- [ ] Task 12: legal forms table
  - File: `src/Context/Client/Infrastructure/Registry/LegalForms.php` (new); test `tests/Unit/Context/Client/Infrastructure/Registry/LegalFormsTest.php`
  - Action: per Technical Decision 23.

- [ ] Task 13: fixture adapters
  - Files (new): `src/Context/Client/Infrastructure/Registry/FixtureCompanyRegistry.php`, `src/Context/Client/Infrastructure/Vat/FixtureVatNumberVerifier.php`
  - Action: dataset aligned with `ClientDataStory` — `523456788` Élevage des Calanques (EARL, active), `812345676` Centre équestre du Lac (SAS, active), `441569738` GAEC du Mont (GAEC, ceased 2025-12-31), `775612344` Refuge des Collines (association), one company whose official name differs from a demo client's typed name; other valid SIREN → not found. Triggers (class constants): query `indisponible` / `UNAVAILABLE_SIREN` (Luhn-valid) → `ExternalServiceUnavailable::failed` (`down`); query `limite` → `ExternalServiceUnavailable::throttled` (`throttled`). Search: accent/case-insensitive contains, 9 digits = SIREN, 14 digits = head-office SIRET. VAT: `FR31523456788`, `FR14441569738` valid; the trigger SIREN's VAT → unavailable; others invalid.
  - Also create `tests/Unit/Context/Client/Infrastructure/Registry/CompanyRegistryContractTestCase.php` (abstract contract, Technical Decision 24 / AC 22) here.
  - Test: `FixtureCompanyRegistryContractTest` (extends the contract), `FixtureVatNumberVerifierTest`. The fixture dataset includes one confidential company (`isConfidential`).

- [ ] Task 14: HTTP adapters, cache, limiter, wiring
  - Files (new): `src/Context/Client/Infrastructure/Registry/{RechercheEntreprisesCompanyRegistry,RechercheEntreprisesMapper,CachedCompanyRegistry,ThrottledCompanyRegistry}.php`, `src/Context/Client/Infrastructure/Vat/{ViesVatNumberVerifier,ViesErrorMapper}.php`, `tests/Fixtures/recherche-entreprises/{search-calanques,search-siret,siren-active,siren-ceased,siren-not-found,siren-non-diffusible}.json`, `tests/Fixtures/vies/{valid,invalid,ms-unavailable,global-max-concurrent,invalid-input}.json`
  - Modified: `config/packages/framework.yaml` (scoped clients — Technical Decision 4; no rate-limiter entry), `config/services.yaml` (lock factory, storage and the three hand-built `RateLimiterFactory` services — Technical Decision 18; `client.registry.http` / `client.vies.http` `RetryAfterAwareClient` services; `Cached → Throttled → RechercheEntreprises` chain; locators + factory aliases — Technical Decision 19), `.env` (`CLIENT_COMPANY_REGISTRY=fixture`, `CLIENT_VAT_VERIFIER=fixture`, `EXTERNAL_API_USER_AGENT="Kiveto/0.5"`, each with an English comment: values, effect, « api/vies mode requires a contact (e-mail or URL) in the user agent »), `.env.test` (both `fixture`, and `EXTERNAL_API_USER_AGENT="Kiveto-test (+tests@kiveto.invalid)"` so the API adapters can be built in tests).
  - Action: per Technical Decisions 18–22 and 24; the two HTTP adapters call `ExternalApiUserAgent::withContact()` in their constructors (Technical Decision 5); hand-written JSON fixtures.
  - Test: `CompanyRegistryContractTestCase` (14-digit query searched as SIRET, active first, `limit` respected, unknown SIREN → null, outage → `ExternalServiceUnavailable`, never empty) run by `FixtureCompanyRegistryContractTest` and `RechercheEntreprisesCompanyRegistryContractTest` (MockHttpClient on the JSON fixtures); `RechercheEntreprisesMapperTest`; `CachedCompanyRegistryTest` (`ArrayAdapter`: search cached 1 h, `findBySiren` never cached, exceptions never cached); `ThrottledCompanyRegistryTest` (`#[Group('time-sensitive')]` + `ClockMock`, in-memory limiters: 5th `search()` in the same second with a 4/s bucket → throttled without inner call; `findBySiren()` waits for a token instead of failing; a cache hit upstream spends no token); `ViesVatNumberVerifier` waits on its limiter (same technique); `ViesVatNumberVerifierTest` + `ViesErrorMapperTest` (every code); `tests/Integration/Context/Client/Infrastructure/AdapterSelectionTest.php` (test env → fixture adapters; locators offer `api` → `CachedCompanyRegistry` and `vies` → `ViesVatNumberVerifier`, buildable with the test user agent; the autowired `LockFactory` is still `shared.lock.factory`; the three limiter factories exist); the contact-less case stays in `ExternalApiUserAgentTest` and the adapters' unit tests.

- [ ] Task 15: search query
  - Files (new): `src/Context/Client/Application/Query/SearchCompanyRegistry/{SearchCompanyRegistry,SearchCompanyRegistryHandler,CompanySearchResult}.php`
  - Action: `CompanySearchResult(bool $available, ?string $reason, list<CompanySummary> $hits)` per Technical Decision 25 (`reason` from `isThrottled()`).
  - Test: `tests/Unit/Context/Client/Application/Query/SearchCompanyRegistry/SearchCompanyRegistryHandlerTest.php` (short query no call; hits; throttled; down).

- [ ] Task 16: verify, record, request commands and the listener
  - Files (new): `src/Context/Client/Application/Command/VerifyClientTaxProfile/{VerifyClientTaxProfile,VerifyClientTaxProfileHandler}.php`, `src/Context/Client/Application/Command/RecordTaxProfileVerification/{RecordTaxProfileVerification,RecordTaxProfileVerificationHandler}.php`, `src/Context/Client/Application/Command/RequestTaxProfileVerification/{RequestTaxProfileVerification,RequestTaxProfileVerificationHandler}.php`, `src/Context/Client/Application/Verification/OnClientTaxIdentifiersChangedVerify.php`
  - Action: per Technical Decisions 11–14. `VerifyClientTaxProfile` implements `ExternalApiCommandInterface`, handler `#[AsMessageHandler(bus: 'messenger.bus.external_api')]`; record and request handlers bare `#[AsMessageHandler]`; the request handler returns `VerificationRequestOutcome` and throws `ClientNotFoundException` for unknown/other clinic. Listener `#[AsMessageHandler(bus: 'messenger.bus.event')]` sends `VerifyClientTaxProfile` through `ExternalApiCommandBusInterface` when the payload is french_business with a SIREN (the aggregate already set `requestedAt`).
  - Test: unit tests of the three handlers (both services up; registry down keeps the VIES result and vice versa; not found; ceased with name; no VAT → not_applicable; archived / individual / unknown → no record; verify handler: the port fakes assert `Connection::isTransactionActive()` is false while called (AC 18); record: stored + saved + published, late answer → nothing saved; request: every outcome) and the listener; integration `RecordTaxProfileVerificationOptimisticLockTest` (load, bump `version` through DBAL, save → `OptimisticLockException`); integration `VerificationOutboxTest` (create a professional → one `queue_name = 'external'` row in the same transaction; individual → none; legal-name-only change → none; SIREN change → one; « Revérifier » twice within 24 h → one row; interactive rows go to `queue_name = 'external'`).

- [ ] Task 17: nightly re-verification
  - Files (new): `src/Context/Client/Application/Command/ReverifyStaleTaxProfiles/{ReverifyStaleTaxProfiles,ReverifyStaleTaxProfilesHandler}.php`, `src/Context/Client/Infrastructure/Persistence/Doctrine/Repository/DbalTaxProfileVerificationSchedule.php`, `src/Context/Client/Infrastructure/Scheduler/ClientScheduleProvider.php`
  - Modified: `config/services.yaml`, `compose.yaml` (`scheduler-worker`: `messenger:consume scheduler_default scheduler_client --time-limit=3540 -vv`, comment updated), `tests/Unit/Shared/Infrastructure/Scheduler/SchedulerWorkerTimeLimitTest.php` → moved to `tests/Integration/Shared/Infrastructure/Scheduler/SchedulerWorkerTimeLimitTest.php` (KernelTestCase collecting every schedule provider by its container tag — no `use` of a Client class in a Shared test — and asserting compose consumes every `scheduler_<name>` transport and 3540 is not a multiple of any period)
  - Action: per Technical Decisions 15 and 16.
  - Test: handler unit test (rows → `markRequested` with all ids, one send per returned id with `TransportNamesStamp(['async_external_batch'])`, ids not returned are not sent; nothing stale → nothing); `tests/Integration/Context/Client/Infrastructure/Persistence/DbalTaxProfileVerificationScheduleTest.php` (never checked, unavailable company, unavailable VAT, older than 30 days → found; fresh, individual, archived, no SIREN, requested < 24 h → not found; requested > 24 h → found; NULL outcomes treated as unchecked; two clinics; NULLs first; `markRequested` works in chunks of 500, sets the column, bumps `version`, skips an id requested meanwhile and returns only the updated ids); `tests/Unit/Context/Client/Infrastructure/Scheduler/ClientScheduleProviderTest.php` (mirrors `DefaultScheduleProviderTest`: one message, lock, not stateful, 86400 s).

- [ ] Task 18: sheet save conflict
  - Files: `src/Presentation/Clinic/Controller/Shared/AbstractSheetAction.php`
  - Action: per Technical Decision 30.
  - Test: extend `tests/Integration/Presentation/ProfessionalClientEntryPointsTest.php` or add `tests/Integration/Presentation/SheetSaveConflictTest.php` (a sheet POST whose command throws `OptimisticLockException` → redirect with the flash, no 500).

- [ ] Task 19: demo fixtures and Client README
  - Files: `fixtures/Context/Client/Story/ClientDataStory.php`, `src/Context/Client/README.md`
  - Action: demo states (CLAUDE.md §7): Calanques verified (active, VAT valid, checked yesterday); Centre équestre du Lac pending (requested, unchecked); GAEC du Mont ceased (archived); one name mismatch; one invalid VAT — consistent with the fixture registry. README: architecture tree, « Règles métier » (never blocking, reset on identifiers change, late answer ignored, 30 days, 24 h de-duplication), table « Environnement → valeurs » (tests/CI fixture/fixture; dev default fixture/fixture; dev opt-in api/vies with a contact user agent; staging/prod api/vies, contact mandatory) with the manual-run command and the three workers, changelog `### 2026-10-xx - Vérification des entreprises clientes`.

**B3 — `feature/client-company-verification-ui`** (from B2; Tasks 20–25, then Task 26)

- [ ] Task 20: endpoints
  - Files (new): `src/Presentation/Clinic/Controller/Client/Profile/CompanyRegistrySearchController.php`, `src/Presentation/Clinic/Controller/Client/Sheet/VerifyClientTaxProfileController.php`, `src/Presentation/Clinic/ViewModel/Client/CompanyHitPresenter.php`
  - Action: search per Technical Decision 25 (`clinic_clients_company_registry_search`); verify per Technical Decision 14 (`POST /clients/{id}/tax-profile/verify`, `clinic_clients_tax_profile_verify`, extends `AbstractClientSheetAction`, `guard()` CSRF, dispatches `RequestTaxProfileVerification` **outside `attempt()`**, `ClientNotFoundException` → `createNotFoundException()`, flash per outcome, `backToSheet($id, 'cFisc')`).
  - Test: `tests/Unit/Presentation/Clinic/ViewModel/Client/CompanyHitPresenterTest.php`; extend `ProfessionalClientEntryPointsTest` (fixture registry: « calanques » → items; « indisponible » → `reason: down`; « limite » → `reason: throttled`; « ca » → empty, no call; verify: queued once through `RecordingExternalApiCommandBus`, second time « Vérification déjà en cours » and nothing more queued; invalid CSRF → refused, nothing queued; other clinic / unknown id → 404, nothing queued; archived → flash « Client archivé », nothing queued; search without session → login redirect).

- [ ] Task 21: sheet payload
  - Files: `src/Presentation/Clinic/ViewModel/Client/ClientSheetBuilder.php`, `templates/clinic/clients/view/index.html.twig`
  - Action: `taxProfileView()` per Technical Decision 26; hidden `#fiscVerifyForm` (POST `verifyUrl`, CSRF `client_sheet`).
  - Test: new `tests/Integration/Presentation/Clinic/ViewModel/Client/ClientSheetBuilderTaxProfileTest.php` (KernelTestCase + factories: unchecked, pending, verified active + VAT valid, ceased, name mismatch → expected `fisc` keys).

- [ ] Task 22: sheet card front
  - Files: `assets/js/pages/clients/sheet.js`, `assets/styles/pages/dossier.css`
  - Action: per Technical Decisions 25–27: search `{available, reason, items}` with ↑/↓/Entrée/Échap (`role="listbox"`, `role="option"`, `aria-selected`), AbortController + 300 ms debounce; throttled vs down; « Saisir à la main »; existing-professional lookup by SIREN; duplicate check on pick; `ann` absent wording; `cesse` without date; badges, « Nom officiel », « Utiliser le nom officiel », last-check line / « Vérification en cours », « Revérifier »; save with a picked company (`rs = e.n`, `siren`, `tva` when liable).

- [ ] Task 23: shared block autocomplete + directory window
  - Files: `assets/js/shared/client-tax-profile.js`, `templates/clinic/clients/_tax_profile_block.html.twig`, `assets/styles/components/tax-profile.css`, `assets/js/pages/clients/create.js`, `src/Presentation/Clinic/Controller/Client/ListClientsController.php`
  - Action: per Technical Decisions 25 and 29; `create.js` passes `registryUrl` (`CFG.urls.registry`) and fills the window's postal address in `onCompany` (the state used by `chercherAdresses()`, create.js L527-545).

- [ ] Task 24: counter
  - Files: `assets/js/pages/billing/register.js`, `src/Presentation/Clinic/ViewModel/Billing/BillingPageBuilder.php`, `src/Presentation/Clinic/ViewModel/Directory/ClientLookupReader.php`
  - Action: per Technical Decision 28 (replaces the disabled group at register.js L634; `P.urls.registry`; `ClientCard.warn`; `carteCliPro()` renders `.vt-msg.warn`).
  - Test: extend the `ClientLookupReader` tests under `tests/Unit/Presentation/Clinic/ViewModel/Directory/` (or add one) for `warn`.

- [ ] Task 25: assets build and browser checks
  - Action: `make assets`; Playwright checks listed in the Testing Strategy.

- [ ] Task 26: manual run, recorded fixtures, closing (whole stack)
  - Files: `tests/Fixtures/recherche-entreprises/*.json`, `tests/Fixtures/vies/*.json`, `tests/Unit/Context/Client/Infrastructure/RecordedApiFixturesTest.php` (new)
  - Action: [manuel] dev with `CLIENT_COMPANY_REGISTRY=api CLIENT_VAT_VERIFIER=vies` and a contact user agent, the three workers running: real searches (name, SIRET, active, ceased, unknown, non-diffusible SIREN) and VIES checks (valid, invalid); record the JSON responses over the hand-written ones (adapter tests unchanged and green); add `RecordedApiFixturesTest` in the **same commit**. Then `make ci` on B1, B2, B3 (stack order); clover coverage of `src/Shared` and `src/Context/Client` (Presentation excluded) at **100 % for each BC as a whole**; spec status « Completed » with review notes in a `docs` commit on B3. No merge, no push.

### Acceptance Criteria

Foundations (B1)

- [ ] AC 1: Given a 429 with `Retry-After: 30`, when `RetryAfterAwareClient::request()` is called, then `ExternalServiceUnavailable` is thrown with `retryAfter = 30`, `isThrottled()` true, and one `warning` record `outcome=rate_limited` is logged on `external_api`.
- [ ] AC 2: Given a 503, a 500, a timeout or a transport error, when a request is made, then `ExternalServiceUnavailable` is thrown with `isThrottled()` false and nothing is retried.
- [ ] AC 3: Given a 200 or a 4xx, then an `ExternalApiResponse` with the status and decoded JSON (or null) is returned and one `info` record is logged.
- [ ] AC 4: Given a call with a query string and a JSON body, then no `external_api` record contains the body, a header value or the query string, and the `http_client` channel is excluded from every dev and prod handler, console handlers included.
- [ ] AC 5: Given the test environment, when any code calls the default or a scoped HTTP client without its own mock, then `\LogicException` « Unmocked HTTP call in tests » is thrown.
- [ ] AC 6: Given an `ExternalApiCommandInterface` sent inside a command-bus transaction, when it commits, then exactly one row with `queue_name = 'external'` exists and none on `default`; when it rolls back, then none; with `TransportNamesStamp(['async_external_batch'])`, then `queue_name = 'external_batch'`; dispatching it through `CommandBusInterface` throws.
- [ ] AC 6b: Given batch messages already queued and then one interactive message, when the external worker consumes `async_external async_external_batch`, then the interactive message is handled first.
- [ ] AC 7: Given the messenger configuration and compose, when the guards run, then every `ExternalApiCommandInterface` routes only to `async_external`, the Doctrine receivers are exactly `async`, `async_external`, `async_external_batch`, `failed`, every handler on `messenger.bus.external_api` handles an `ExternalApiCommandInterface`, and `external-worker` consumes `async_external async_external_batch` in that order while `messenger-worker` consumes neither.
- [ ] AC 8: Given `EXTERNAL_API_USER_AGENT` without an e-mail or URL, when the registry adapter (`api`) or the VIES adapter (`vies`) is built, then `\LogicException` names the variable; `cache:clear`, migrations and fixture mode are unaffected.
- [ ] AC 9: Given B1, when coverage is measured with clover, then `src/Shared` is at 100 %.

Domain and application (B2)

- [ ] AC 10: Given a verified professional, when its SIREN or VAT changes, then `ClientTaxIdentifiersChanged` is recorded, the verification is unchecked and requested; when only its legal name changes, then nothing of that happens.
- [ ] AC 11: Given a result for a SIREN/VAT that is no longer current, when recorded, then nothing changes (the newer request marker is kept).
- [ ] AC 12: Given an archived client or an individual, when a result is recorded, then nothing is stored and the request marker is cleared.
- [ ] AC 13: Given a stored result, then `updatedAt` is unchanged, the request marker is cleared and `ClientTaxProfileVerified` is recorded.
- [ ] AC 14: Given the warnings matrix, when `warnings()` is called, then the expected ordered list is returned.
- [ ] AC 15: Given « Élevage des Calanques » / « EARL ELEVAGE DES CALANQUES » and « Le Refuge & Co » / « refuge et co », when normalised, then equal; two different names differ.
- [ ] AC 16: Given never checked, unavailable, or checked more than 30 days ago, then `isStale()` is true; checked yesterday with definite outcomes, false.
- [ ] AC 17: Given a new professional with a SIREN created through the command bus, when the transaction commits, then one `VerifyClientTaxProfile` row is queued on `async_external` in the same transaction; an individual → none; a legal-name-only change → none; a SIREN change → one.
- [ ] AC 18: Given the registry is unavailable and VIES answers valid, when `VerifyClientTaxProfile` is handled, then company `unavailable` and VAT `valid` are recorded (and vice versa), without any database transaction open during the HTTP calls, and the message is not retried.
- [ ] AC 19: Given a professional without VAT number, when verified, then the VAT outcome is `not_applicable`.
- [ ] AC 20: Given a concurrent commit between load and save, when `RecordTaxProfileVerification` is handled, then `OptimisticLockException` is thrown.
- [ ] AC 21: Given a ceased company, when verified, then `ceased` and the registry name are stored; given a non-diffusible company, then `confidential` is stored, no counter warning is derived and the profile is re-verified only after 30 days.
- [ ] AC 22: Given the contract test on the fixture adapter and on the API adapter with recorded responses, then a 14-digit query is searched as SIRET, active companies come first, `limit` is respected, an unknown SIREN gives null, an outage throws `ExternalServiceUnavailable`.
- [ ] AC 23: Given each VIES outage code, when mapped, then `ExternalServiceUnavailable`; `INVALID_INPUT` → invalid.
- [ ] AC 24: Given a cached search repeated within 1 h, then no HTTP call; `findBySiren` always calls the registry; an exception is never cached.
- [ ] AC 25: Given the web budget (4/s) is spent, when a fifth search arrives in the same second, then it is throttled without an HTTP call; given the worker budget (1/s), when two `findBySiren` follow each other, then the second waits for a token instead of failing; VIES calls are paced at 1/s the same way (tested with `ClockMock`).
- [ ] AC 26: Given stale professionals in two clinics, when `ReverifyStaleTaxProfiles` runs, then each is marked requested (in chunks of 500) and one `VerifyClientTaxProfile` per client is queued on `async_external_batch`; fresh, individual, archived, SIREN-less and requested-within-24-h clients are skipped; a second run right after queues nothing.
- [ ] AC 27: Given « Revérifier » twice within 24 h on the same client, then one message is queued and the second answer is « Vérification déjà en cours ».
- [ ] AC 28: Given both schedules and compose, when the scheduler guards run, then the scheduler worker consumes `scheduler_default` and `scheduler_client`, and 3540 is not a multiple of any task period.
- [ ] AC 29: Given the test environment, then the ports resolve to the fixture adapters; the locators also offer `api` and `vies`; the autowired `LockFactory` is still `shared.lock.factory`.
- [ ] AC 30: Given a sheet save racing a recorded verification, when the user's command fails with `OptimisticLockException`, then the user is redirected with « La fiche vient d'être mise à jour, réessayez. » (no 500).
- [ ] AC 31: Given B2, when coverage is measured with clover, then `src/Context/Client` and `src/Shared` are at 100 %.

Presentation (B3)

- [ ] AC 32: Given « calanques », when `GET /clients/company-registry/search?q=calanques`, then `{available: true, reason: null, items: [{siren: '523456788', n: 'Élevage des Calanques', forme: 'EARL', ets: [{l: 'Siège', …}], tva: 'FR31523456788', …}]}`; « ca » → `{available: true, reason: null, items: []}` without calling the registry; « indisponible » → `{available: false, reason: 'down', items: []}`; « limite » → `reason: 'throttled'`.
- [ ] AC 33: Given « Revérifier » on a professional, then one `VerifyClientTaxProfile` is queued and the card shows the flash « Vérification lancée : le résultat s'affichera sur la fiche d'ici quelques minutes. » and « Vérification en cours »; an archived client gets « Client archivé » and nothing is queued; an unknown id or a client of another clinic gets a 404 and nothing is queued; an invalid CSRF token is refused and nothing is queued.
- [ ] AC 34: Given no session, when the search endpoint is called, then the user is redirected to the login page.
- [ ] AC 35: Given a verified professional (active, VAT valid), then the card shows « ✓ VIES » and « Vérifié le … · actif · TVA valide »; given a name mismatch, then « Nom différent du registre », « Nom officiel : … » and « Utiliser le nom officiel », which saves the registry name, keeps the verification and queues nothing.
- [ ] AC 36: Given the card in edit mode, when the search answers `down`, returns nothing, or returns a non-diffusible company, then « Saisir à la main » is visible and the manual block works as in slice 0 (opened automatically on `down`); when it answers `throttled` while typing, then the last hits stay and the card does not switch to manual entry.
- [ ] AC 37: Given a company picked in the card whose SIREN another client already has, then the duplicate warning with « Ouvrir sa fiche » is shown.
- [ ] AC 38: Given the directory window with the professional status, when « calanques » is typed in the legal name, then hits appear after 300 ms, ↑/↓/Entrée pick one, and legal name, SIREN, VAT suggestion (« à confirmer … ») and postal address are filled; an association hit shows « Structure non assujettie ? ».
- [ ] AC 39: Given the counter « Professionnel » tab, when « calanques » is typed, then the « Annuaire des entreprises · SIRENE » group lists registry hits not already clients, and picking one opens « Nouveau professionnel » prefilled.
- [ ] AC 40: Given a selected professional whose company is ceased, not found or whose VAT is invalid, then the counter shows the warning under its card and the sale can still be recorded (browser check + the existing counter-sale tests unchanged); a confidential company shows nothing at the counter; « Vérification en attente » and « Nom différent du registre » are never shown at the counter.

Closing

- [ ] AC 41 [manuel]: Given dev with `CLIENT_COMPANY_REGISTRY=api`, `CLIENT_VAT_VERIFIER=vies`, a contact user agent and the three workers, when searching a real company and verifying a real professional, then real data is shown and `external_api` records appear; the recorded JSON replaces every hand-written fixture and `RecordedApiFixturesTest` passes.
- [ ] AC 42: Given B1, B2 then B3, when `make ci` runs on each in stack order, then it is green, and clover coverage of `src/Shared` and `src/Context/Client` (Presentation excluded) is 100 % for each BC as a whole.

## Additional Context

### Dependencies

- No new Composer package (`symfony/http-client`, `symfony/rate-limiter`, `symfony/lock`, `symfony/monolog-bundle` already installed — check `symfony/rate-limiter` and `symfony/lock` in `composer.json` at Task 14; add them only if absent; no cron library: `every()`).
- External services (staging/prod; dev opt-in): API Recherche d'entreprises (free, no key, 7 req/s per IP, 30 req/s per ASN) and VIES REST.
- INSEE « Catégories juridiques » nomenclature (niveau III), downloaded once for `LegalForms`.
- Slice 0 on master (tax profile, `Siren`/`FrenchVatNumber`, implicit outbox and guards, scheduler + DB lock, busy_6 card).
- The `workers` compose profile now runs three workers: `messenger-worker` (`async`), `external-worker` (`async_external`), `scheduler-worker` (`scheduler_default`, `scheduler_client`).

### Testing Strategy

- **Unit**: VOs, enums, normalizer table, warnings matrix, aggregate rules, handlers with port fakes, mapper, cache and throttle decorators, VIES error mapper, `RetryAfterAwareClient` + Monolog `TestHandler`, `ExternalApiUserAgent`, schedule provider, presenters.
- **Contract**: `CompanyRegistryContractTestCase` on the fixture adapter and on the API adapter fed with recorded JSON.
- **Integration** (KernelTestCase + DAMA): persistence round trip and read model; optimistic-lock race on the record handler; sheet save conflict; outbox rows on `async_external` (create, identifier change, name-only change, « Revérifier » de-duplication, nightly run); schedule finder across clinics; adapter selection; network guard; logging configuration; messenger and compose guards; entry points through `RecordingExternalApiCommandBus` (search reasons, 404, CSRF, archived, login redirect).
- **No network** in tests/CI.
- **Browser** (Playwright from the scratchpad): sheet card (read states from demo fixtures, edit with search / keyboard / pick / duplicate / manual link, `indisponible` and `limite` triggers, « Revérifier » flash and « Vérification en cours », « Utiliser le nom officiel »), directory window autocomplete + address fill, counter SIRENE group + prefill + warning line.
- **Manual** [manuel]: AC 41.
- **Coverage**: clover XML for `src/Shared` and `src/Context/Client`, each 100 % as a whole.

### Notes

- **Highest risks**: (1) real JSON shapes differ from the hand-written fixtures — isolated mapper, contract test, mandatory recording; (2) registry quota — per-container budgets (web 4/s, worker 1/s) under the API's 7/s, remote 429 as last safeguard, `external_api` logs to measure it; (3) frequent VIES outages — `unavailable` outcomes retried the next night, never blocking; (4) messaging changes (new transport, bus, worker, guards) — covered by guard tests and a second adversarial review before development.
- **Known limitations**: NAF code without label; head office only (no SIRET stored); ceased date not stored (read-mode badge without date); `meta`/`trace` stay empty; no auto-refresh after « Revérifier »; the nightly run can be skipped by a DST change or a worker restart at 02:17 (caught up the next night); rate-limiter budgets are per container (several web containers ⇒ divide the web budget or move to shared storage, same work as for the cache); `EXTERNAL_API_USER_AGENT` must carry a real contact in api/vies mode (checked at first use).
- **Scope growth accepted**: covering the pre-existing Shared gaps (Task 6) to meet CLAUDE.md §3 for the whole BC; the slice also lays two external transports, three limiters and a priority queue — justified because they are the foundation of **every** external integration.
- **Reuse rule for the next slices**: slice 1 and the Super PDP integration reuse these bricks as they are (scoped client + `RetryAfterAwareClient` + `ExternalApiUserAgent`, `ExternalApiCommandInterface` on `async_external` / `async_external_batch` with the `external-worker`, hand-built per-container limiters, verify/record split, `external_api` logs, fixture adapter + env selection). No new transport, bus or limiter mechanism without a spec that justifies it.
- **Future**: Géoplateforme and Gotenberg to migrate onto the convention; Super PDP (slice 4) reuses scoped clients, `RetryAfterAwareClient`, `async_external` + `external-worker` and the verify/record split; PPF directory (`ann`) and Chorus Pro keep their « bientôt » slots.
