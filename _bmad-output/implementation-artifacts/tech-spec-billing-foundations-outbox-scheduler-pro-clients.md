---
title: 'Billing foundations: transactional messaging, scheduler, professional clients'
slug: 'billing-foundations-outbox-scheduler-pro-clients'
created: '2026-10-17'
status: 'Completed'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['PHP 8.5', 'Symfony 7.4 (Messenger, Scheduler, Lock)', 'Doctrine ORM/DBAL (MySQL 8.4)', 'Twig', 'Stimulus/vanilla JS', 'Tailwind v4', 'PHPUnit + DAMA + Foundry', 'Docker compose']
files_to_modify: ['config/packages/messenger.yaml', 'config/packages/lock.yaml', 'config/services.yaml', 'composer.json', 'Makefile', 'compose.yaml', 'migrations/Shared/', 'migrations/Client/', 'migrations/Billing/', 'src/Shared/README.md', 'src/Shared/Domain/ValueObject/', 'src/Shared/Infrastructure/Scheduler/', 'src/Context/Client/Domain/', 'src/Context/Client/Application/Command/CreateClient/', 'src/Context/Client/Infrastructure/Persistence/Doctrine/', 'src/Context/Billing/Application/Command/RecordCounterSale/', 'src/Context/Billing/Domain/ValueObject/Payer.php', 'src/Context/Billing/Infrastructure/Adapter/Client/', 'src/Presentation/Clinic/Controller/Client/', 'src/Presentation/Clinic/Controller/Billing/', 'templates/clinic/billing/index.html.twig', 'templates/clinic/clients/', 'templates/clinic/document/', 'assets/js/pages/billing/register.js', 'assets/js/pages/clients/', 'fixtures/Context/Client/', 'fixtures/Context/Billing/', 'tests/Integration/System/Messenger/']
code_patterns: ['DDD BCs under src/Context|System', 'CQRS buses with doctrine_transaction on command bus', 'sync domain events + bridges to async integration events', 'ports/adapters for cross-BC', 'attribute Doctrine mapping + manual field copy in save()', 'single-action controllers', 'rail cards via kit.js carte()']
test_patterns: ['tests/Unit mirrors src (TestCase, mocks)', 'tests/Integration KernelTestCase + Foundry Factories', 'DAMA static connection + savepoints', 'Finder-based architecture tests', 'no WebTestCase']
---

# Tech-Spec: Billing foundations: transactional messaging, scheduler, professional clients

**Created:** 2026-10-17

## Overview

### Problem Statement

French electronic invoicing (target architecture: new `System/ElectronicInvoicing` BC, AFNOR XP Z12-013
integration, SUPER PDP as default platform) is delivered in slices. This spec is **slice 0 — foundations**.
Three prerequisites are missing today:

1. **No delivery guarantee for cross-BC events is proven or protected.** An issued invoice must never be
   left un-transmitted because its integration event was lost between the business commit and the queue.
2. **No scheduled tasks.** Status synchronisation by polling (`SyncPlatformFlows`) and reporting-period
   closing (`CloseReportingPeriod`) need a scheduler; `symfony/scheduler` is not installed.
3. **No professional clients.** The Client BC has no tax status, SIREN or VAT number, so B2B invoicing is
   impossible and the counter-sale "Professionnel" tab is disabled ("bientôt").

Slice 0 is useful on its own, independently of electronic invoicing.

### Solution

- **Messaging:** prove and lock the implicit outbox. The async transport is `doctrine://default`
  (same MySQL connection as business data); the command bus wraps handlers in `doctrine_transaction`;
  domain events are dispatched synchronously to bridges, which publish integration events to the async
  transport — so the `shared__messenger_messages` INSERT happens inside the business transaction.
  No new table. Add tests and architecture guards so the guarantee cannot silently break.
- **Scheduler:** install `symfony/scheduler`, add a locked, stateful example schedule, a worker make
  target and a Docker compose service; schedule locked through a shared `DoctrineDbalStore`.
- **Professional clients:** add a tax profile to the Client aggregate (`CustomerTaxStatus`
  `Individual | FrenchBusiness`, optional legal name — mandatory for `FrenchBusiness` —, mandatory SIREN
  with Luhn check and optional French VAT number with key check for `FrenchBusiness` only); the existing
  person identity becomes the contact person. Expose it on the client sheet, and enable the counter-sale "Professionnel" tab
  (search + create a professional, sale paid at the counter) and the directory creation windows if the
  same form component can be reused.

### Scope

**In Scope:**

1. **Transactional messaging (outbox via Doctrine transport)**
   - Integration test: when a command handler fails after publishing an integration event, the
     `shared__messenger_messages` row is rolled back with the business data; on success both are committed.
   - Architecture guard: no usage of `DispatchAfterCurrentBusStamp` in `src/`.
   - Architecture guard: domain events (`DomainEventInterface`) and the domain → integration bridges stay
     routed **synchronously**; only `IntegrationEventInterface` goes to `async`. The test asserts against
     the **compiled `SendersLocator`** from the container (not the YAML), so a route added via
     `#[AsMessage(transport: 'async')]` on a domain event is caught too.
   - The atomicity test also covers a failure **after `flush` but before commit** (e.g. exception thrown
     from a Doctrine listener / later in the handler), not only a failure before flush.
   - Convention documented in `src/Shared/README.md` (existing file).
   - Requirement applies to Billing → ElectronicInvoicing events (bridge itself is slice 1); migrating
     other events/conventions elsewhere is a separate tech-debt PR.
2. **Scheduler**
   - `symfony/scheduler` installed; scheduler transport; `make` target for the worker; worker service in
     the Docker compose file.
   - `symfony/lock` installed with a **`DoctrineDbalStore`** on the default connection, table
     `shared__lock_keys` created by migration (`LOCK_DSN`), so the lock is shared across containers
     (rolling deploys run two workers at once; a `flock` default would be per-container).
   - Example schedule with `->lock()` (no double run with several workers), covered by a test.
   - **No `->stateful()`, no DB cache pool.** Catching up missed runs is a domain concern: scheduled tasks
     must be idempotent and self-catching-up (e.g. `CloseReportingPeriod` closes *every* ended, unclosed
     period; polling relies on its cursor). Lock + idempotent tasks is sufficient.
3. **Client BC — tax profile**
   - `CustomerTaxStatus` enum limited to `Individual` and `FrenchBusiness`.
     `FrenchBusiness` means **subject to VAT in France** ("assujetti"), not "has a SIREN": a non-VAT-liable
     association (e.g. a shelter) has a SIREN but stays `Individual`/B2C.
   - Model **(a)**: the existing person identity (civility, last name, first name — current invariants
     unchanged) becomes the **contact person**; a **legal name (raison sociale)** is added:
     - optional for `Individual` (so "Refuge des Collines" is never typed as a first name);
     - mandatory for `FrenchBusiness`.
     When a legal name exists, the invoice payer name is the legal name, with the contact as
     "À l'attention de M./Mme X Y".
   - SIREN and VAT number exist **only** for `FrenchBusiness`:
     - **SIREN mandatory**: input cleaned of spaces and dots; a pasted 14-digit **SIRET is accepted** and
       reduced to its first 9 digits; the 9-digit SIREN is Luhn-checked. No SIRET is stored in slice 0
       (hence no La Poste SIRET exception needed — La Poste SIREN 356000000 passes Luhn).
     - **SIREN not unique** (several establishments share one): duplicate shows a **warning** only,
       like the duplicate phone.
     - **VAT number optional** (franchise en base companies may not have one); normalised (upper case, no
       spaces); if provided, format `FR` + 2-digit key + SIREN, key = `(12 + 3 × (SIREN mod 97)) mod 97`;
       distinct error messages for bad format, wrong key, and SIREN mismatch.
   - UI: a two-option selector shown **before** the fields (not a tooltip):
     « Particulier ou structure non assujettie à la TVA (association, refuge…) » /
     « Professionnel assujetti à la TVA »; SIREN and TVA fields appear only with the second.
   - Create/update commands, client sheet display and edition, demo fixtures (CLAUDE.md §7).
4. **Counter sale — "Professionnel" tab enabled**
   - Search among professional clients and create a professional (same form component); legal name
     first, contact person below.
   - The invoice payer carries the legal name. While the invoice is a draft, Billing re-reads the client
     record (no frozen copy yet).
   - Sale stays **paid at the counter**: "Virement" and "À régler sous 30 jours" stay disabled ("bientôt").
5. **Directory creation windows** — creating a professional from the répertoire "Nouveau client" window,
   **only if** it is the same partial and the same command (no duplicated logic). If reuse requires real
   effort, keep it disabled ("bientôt") and note it.

**Out of Scope:**

- Billing snapshot frozen at `Issuing`, SIREN/VAT fields in the Billing payer snapshot,
  `ElectronicInvoiceReadiness` (checked **before** `InvoiceNumberGenerator::next()`) — slice 1.
- Credit sales: payment terms, due date, B2B legal mentions (late-payment penalty rate, €40 fixed
  indemnity, discount conditions — BR-FR rules), S1/M1 vs S2/M2 — slice 1.
- `EuBusiness`, `NonEuBusiness` (slice 2), `PublicEntity` (B2G, later).
- VIES check, Sirene auto-completion (stay "bientôt").
- Hash-chained invoice numbering (separate spec; "never delete an issued invoice" is already a Billing
  rule with gap-free sequential numbering).
- Clinic tax parameters (`vatRegime`, `vatExigibility`) — belong to Clinic, slice 2.
- Migration of other events to the documented convention — tech-debt PR.
- Anything in `System/ElectronicInvoicing` (slice 1+).

## Context for Development

### Codebase Patterns

**Messaging (verified)**
- `config/packages/messenger.yaml`: transports `async` (`%env(MESSENGER_TRANSPORT_DSN)%`, `table_name: shared__messenger_messages`,
  retry 3 × multiplier 2; `use_notify` is a no-op on MySQL), `failed` (`doctrine://default?queue_name=failed` — **no `table_name`**,
  so it targets a non-existent `messenger_messages` table with auto_setup: latent bug, and auto-created DDL would implicitly commit an
  open MySQL transaction), `sync`. `failure_transport: failed`.
- Buses: `messenger.bus.command` (Metadata → Context → Logging → `validation` → `doctrine_transaction`), `messenger.bus.query`
  (no transaction), `messenger.bus.event` and `messenger.bus.integration_event` (`allow_no_handlers`, `allow_no_senders`, **no
  transaction**). Custom middlewares in `src/Shared/Infrastructure/Bus/Messenger/Middleware/`. Bus wiring `config/services.yaml:104-128`.
- Routing: `App\Shared\Domain\Event\DomainEventInterface: sync`, `App\Shared\Domain\Event\IntegrationEventInterface: async`, mailer/notifier async.
- `.env:43` `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0`; not overridden in `.env.test`/`.env.dev`/compose → **tests use the
  real Doctrine transport on the ORM connection**; rows in `shared__messenger_messages` can be asserted directly.
  Table created by `migrations/Shared/Version20251226020142.php`.
- Flow: handler → `repo->save()` (flush) → `DomainEventPublisher::publish()` (`src/Shared/Application/Event/DomainEventPublisher.php:21-34`)
  or `$eventBus->publish([], ...pullDomainEvents())` → sync handlers on `messenger.bus.event`, including the two bridges
  (`src/Context/Animal/Infrastructure/Messaging/Consumer/AnimalNameChangedIntegrationBridge.php`,
  `src/Context/Admission/Infrastructure/Messaging/Consumer/AdmissionClosedIntegrationBridge.php`, `#[AsMessageHandler(bus: 'messenger.bus.event')]`)
  → `IntegrationEventPublisher` → async transport INSERT, all inside the command bus transaction. Some handlers publish integration events
  directly (`ArchiveClientHandler`, `ValidateSupplierReceiptHandler`, `PatientIdentityResolutionService`, `EmergencyAdmissionService`,
  two PharmaceuticalRegistry handlers) — same guarantee.
- None in `src/`/`config/`: `DispatchAfterCurrentBusStamp`, `#[AsMessage]`, Doctrine `postFlush`/`postCommit`/`onFlush` listeners.
- Known gap (out of scope, tech debt): handlers consumed from `messenger.bus.integration_event` run without a transaction
  (e.g. `src/Context/Animal/Infrastructure/Messaging/Consumer/ClientArchivedIntegrationEventConsumer.php:62-66`: each flush auto-commits).
- **No worker exists**: no `messenger:consume` make target, no worker service in `compose.yaml` → async integration events are never
  consumed in dev unless run by hand.
- Existing guard: `tests/Integration/System/Messenger/AsyncTransportTransactionalityTest.php` (+ `TransactionalityProbeIntegrationEvent.php`)
  covers only "publish inside a manual transaction then rollBack". Positive row-count assertion pattern:
  `tests/Integration/Context/Procurement/Command/ValidateSupplierReceiptHandlerTest.php:114-115`.
- DAMA doctrine test bundle active (`phpunit.xml:48`, static connection) + `use_savepoints: true` (`config/packages/doctrine.yaml:10,142`):
  nested transactions become SAVEPOINTs, so rollback tests work under DAMA. **No DDL inside tests** (MySQL implicit commit).
- Architecture-style test precedent: `tests/Integration/Shared/MessengerHandlersRegistrationTest.php` (plain TestCase + Finder). No
  PHPat/deptrac/custom PHPStan rules.

**Scheduler / lock (verified)**
- Not installed: `symfony/lock`, `symfony/scheduler` (Symfony 7.4.*, PHP ≥ 8.5, `allow-contrib: false`). Flex recipe for lock creates
  `config/packages/lock.yaml` + `LOCK_DSN=flock` — must be overridden.
- A plain `framework.lock: 'doctrine.dbal.default_connection'` would use table `lock_keys` (outside the `/^shared__/` migration filter,
  created lazily via DDL) → declare an explicit `Symfony\Component\Lock\Store\DoctrineDbalStore` service with
  `db_table: shared__lock_keys` and create the table in an explicit `migrations/Shared/` migration.
- Makefile: `EXEC_PHP = docker compose exec -T php-fpm` (l.7-8), `ci` (l.414), `test*` (l.441-459), `reset-db` (l.225),
  `shared-migrations` (l.323, filter `/^shared__/`), `.PHONY` l.98-105. Coverage threshold is not enforced by `make ci`.
- `compose.yaml`: services `db` (mysql:8.4.7), `nginx`, `php-fpm` (l.30-48: build `./docker/php-fpm/Dockerfile`, `user: ${HOST_UID}:${HOST_GID}`,
  volume `./:/var/www/html`, env `DATABASE_URL`, `GOTENBERG_URL`, depends on db+gotenberg, network `kiveto-network`), `gotenberg`, `phpmyadmin`.
  A worker service mirrors `php-fpm` with a `command:` override. Dockerfile has no supervisor/cron.
- Deferred scheduler need already recorded: `WeeklyAnmvImportScheduleProvider` in
  `_bmad-output/implementation-artifacts/tech-spec-system-pharmaceutical-registry-bc.md:91,1185` (not in scope; mention only).

**Client BC (verified)** — root `src/Context/Client/`
- Aggregate `Domain/Client.php`: `create(id, clinicId, ClientIdentity, list<ContactMethod>, createdAt)` :54, `reconstitute(...)` :88,
  mutators call `ensureNotArchived()` + set `updatedAt` (`updateIdentity` :116, `updatePostalAddress` :180, `updateCommunicationPreferences` :233…).
- `Domain/ValueObject/ClientIdentity.php`: first/last name non-empty (`\InvalidArgumentException`), `Civility` (`madam|mister|unspecified`).
- Address: `App\Shared\Domain\ValueObject\PostalAddress` (has `countryCode`, ISO alpha-2 validated) — BT-55 already covered.
- Events `Domain/Event/*` extend `AbstractDomainEvent` (BC `client`, v1). Consumers: Client search `Application/Search/OnClient*.php`,
  Animal search `OnClientIdentityUpdated`.
- Persistence: attribute mapping `Infrastructure/Persistence/Doctrine/Entity/ClientEntity.php` (table `client__clients`, `#[ORM\Version]`),
  mapper `Mapper/ClientMapper.php` (`toDomain` :33, `toEntity` :63), **write repo `Repository/DoctrineClientRepository.php::save()` copies
  fields by hand (:36-43) — every new column must be added there too** (past bug in README changelog). Migrations `migrations/Client/`
  (latest `Version20261014090000.php`).
- Commands: `Application/Command/CreateClient/` (`clinicId, firstName, lastName, contactMethods, civility, ?PostalAddressDto,
  ?preferredChannel, channelsByConsentKind, animals`), `UpdateClientIdentity`, `UpdateClientPostalAddress`, …
- Read side: `Application/Port/ClientReadRepositoryInterface.php` → `DoctrineClientReadRepository.php` (`findById` → `ClientView` :30-82,
  `search` DBAL :84-157 with name match at :334, `findIdsByPhone` :159, `findFullNamesByIds` :209). Views `GetClientById/ClientView.php`,
  `SearchClients/ClientListItemView.php`. Search index `Search/ClientSearchEntryData` (first/last name only).
- **Name collision:** `src/System/Taxation/Domain/ValueObject/CustomerTaxStatus.php` already exists (`b2c|b2b|intracom|export`, used by
  tax rate/mention conditions). The Client enum must be named differently.
- No server-side SIREN/VAT validation anywhere: `src/Context/Regulatory/Jurisdiction/France/SireNumberValidator.php` is dead code
  (`/^\d{15}$/`, unreferenced); `src/Context/Clinic/Domain/ValueObject/Letterhead.php:70-93` only checks 14 digits. JS reference
  algorithms: `assets/js/pages/settings.js:58-59,84-85`. Shared VOs live in `src/Shared/Domain/ValueObject/` (`EmailAddress`, `PhoneNumber`,
  `PostalAddress`, `CountryCode`, …).

**Client sheet (verified)**
- `templates/clinic/clients/view/index.html.twig` (rail cards :60-64 `#rdvs #cCompte #cPref #cPers #cNote`, hidden forms :141-157, CSRF id
  `client_sheet`), JS `assets/js/pages/clients/sheet.js` (data from `src/Presentation/Clinic/ViewModel/Client/ClientSheetBuilder.php`
  `build` :65, `clientView` :392-419; rail cards via `carte()` from `assets/js/pages/dossier/kit.js:147`, example `cPers` :362-410).
- Controllers `src/Presentation/Clinic/Controller/Client/Sheet/*` extend `AbstractClientSheetAction` (`guard`, `clinicId`, `attempt`,
  `backToSheet`); model `UpdateClientProfileController.php`. Mockups have no fiscal-profile UI on the sheet.

**Répertoire "Nouveau client" (verified)**
- `templates/clinic/clients/list/_modal_new_client.html.twig` (included at `templates/clinic/clients/list/index.html.twig:215`), JS
  `assets/js/pages/clients/create.js` (state `NC` :364, `rendreNcCli` :372, `ncErr` :473, submit :609-623, JSON `payload`).
- Endpoint `src/Presentation/Clinic/Controller/Client/Profile/CreateClientController.php` (`POST /clients/create`, `clinic_clients_create`,
  CSRF `directory_create`) → `src/Presentation/Clinic/ViewModel/Directory/DirectoryRegistrar.php:41-108` → `DirectoryClientInput::parse`
  → single `CreateClient` dispatch (:84).
- **Not shared with the counter sale today**: the counter "Nouveau particulier" form is a separate render in
  `assets/js/pages/billing/register.js` with different rules.
- `src/Presentation/Clinic/ViewModel/Directory/ClientLookupReader.php` (card `{id, n, ini, tel, ville, adr, ani[], url}`; `search()`,
  `byIds()`; one `GetClientById` per card :121) — shared by counter search, directory lookups, owner picker.

**Billing counter sale / payer (verified)**
- `src/Context/Billing/Application/Command/RecordCounterSale/` (`RecordCounterSale`, `CounterSaleNewClient`, `RecordCounterSaleHandler`:
  `payer()` :146-172, `register()` :178-222; newcomer `Payer::create(id, displayName())` :192 without address).
- `Domain/ValueObject/CounterSalePayer.php` (`CLIENT|NEW_CLIENT|REFUSED`). Port `Application/Port/CounterClientRegistryInterface.php`;
  adapter `Infrastructure/Adapter/Client/CommandBusCounterClientRegistry.php` dispatches `CreateClient` **then a redundant
  `UpdateClientIdentity` for civility (:64)**.
- Payer read: `Application/Port/PayerDirectoryInterface.php` (`forAdmission`, `forClient`, `clientWithPhone`) → `PayerSnapshotDto(?clientId,
  displayName, addressLines)` → `Infrastructure/Adapter/Client/DbalPayerDirectory.php` (raw SQL on `client__clients`, `snapshot()` :92).
- `Domain/ValueObject/Payer.php` (`clientId, displayName, addressLines`; `create`, `anonymous`, `contactDetailsRefused`), persisted as
  `payer_name`, `payer_address_lines` on the invoice entity; `CreditNote` carries a copy. `Invoice::changePayer` (Invoice.php:199) unused.
- Re-read vs frozen: counter sale reads once and issues immediately; visit invoice draft is rebuilt by `PrepareInvoiceForAdmissionHandler`
  from `forAdmission` until issued.
- Presentation: `src/Presentation/Clinic/Controller/Billing/CounterClientsController.php` (`GET /billing/counter-clients`),
  `RecordCounterSaleController.php` (`POST /billing/counter-sale`, `payer` = `cli|part|anon`, `newClient()` :115). Template
  `templates/clinic/billing/index.html.twig:128` (`#vtTy`, "Professionnel" button `data-t="pro" disabled`), hidden form `#factForm` (:155+).
  JS `register.js`: `VT` :552, `rendreZone` :572-594, `clientOk` :615, type switch :814, inputs :825-860, submit :882-893, comment :493.
- Mockup pro tab `docs/frontend-theme/kiveto-theme/facturation-v-topbar_5.html:3017-3180`: one search "Raison sociale ou SIREN…" with two
  groups (clinic pro clients · "Annuaire des entreprises · SIRENE"), pro card (name, badge Pro/Asso/Public, "SIREN … · TVA …", address),
  "SIREN · 9 chiffres *" input with Luhn messages, B2G "Code service"/"N° d'engagement", Virement / "À régler sous 30 jours", e-invoice
  banner, print-only delivery, **no contact-person field**. Helpers `sirenDe` :2668, `fmtSiren` :2669, `luhn` :2670, `tvaDe` :3014.
- Rendering: `templates/clinic/document/invoice.html.twig:53-58`, `templates/clinic/document/credit_note.html.twig:31-35`, data
  `InvoiceDetails`/`CreditNoteDetails` via `DbalInvoiceReadRepository.php:122-123,180-181`, preview `register.js` `paperHtml` :303 from
  `src/Presentation/Clinic/ViewModel/Billing/BillingSheetBuilder.php:77,149`.

**Fixtures (verified)**
- `fixtures/Context/Client/Factory/ClientEntityFactory.php` (Foundry on the entity), stories `fixtures/Context/Client/Story/ClientDataStory.php`,
  `DirectoryCrowdStory.php`, `ClientSheetStatesStory.php`; order in `fixtures/Dataset/ClinicDataset.php`; counter sales
  `fixtures/Context/Billing/Story/BillingRegisterStory.php:271-280`.

**Norms (docs/e-invoicing, XP Z12-012 annex A v1.4, XP Z12-014 annexes A/B v1.3)** — buyer block BG-7:
- BT-44 legal name 1..1; BT-45 trading name 0..1; BT-47 SIREN (scheme 0002) mandatory for B2B e-invoicing, exactly 9 digits
  (BR-FR-32) and active in the PPF directory (BR-FR-11); **no checksum rule in the norms** (Luhn/VAT key are our own typo guards);
  BT-46 SIRET first 9 digits = SIREN (BR-FR-09); BT-48 VAT id ≤18 chars, ISO prefix, required in practice only for intra-EU/reverse charge;
  BT-49 buyer electronic address mandatory for B2B e-invoicing, must start with the SIREN, scheme 0225 (BR-FR-12/21), **to be managed as
  its own field** (Z12-014 A p.13); BT-55 country code 1..1; BG-9 contact BT-56 = "à l'attention de" (0..1).
- Non-VAT-liable entities with a SIREN (associations) are not in the directory → B2C e-reporting (Z12-014 A p.12, case 35). Franchise en
  base buyers are B2B (case 23).

### Files to Reference

| File | Purpose |
| ---- | ------- |
| config/packages/messenger.yaml | Buses, middleware, transports, routing; `failed` transport bug |
| config/services.yaml:104-128 | Bus bindings, publishers |
| src/Shared/Application/Event/DomainEventPublisher.php | Sync domain event dispatch |
| src/Shared/Application/Event/IntegrationEventPublisher.php | Integration event publishing |
| src/Shared/Domain/Event/IntegrationEventInterface.php | Async marker |
| src/Context/Animal/Infrastructure/Messaging/Consumer/AnimalNameChangedIntegrationBridge.php | Bridge pattern |
| tests/Integration/System/Messenger/AsyncTransportTransactionalityTest.php | Existing outbox guard to extend |
| tests/Integration/Shared/MessengerHandlersRegistrationTest.php | Architecture-test style |
| migrations/Shared/Version20251226020142.php | Messenger table migration pattern |
| Makefile, compose.yaml | Targets, php-fpm service to mirror |
| src/Shared/README.md | "Event dispatch policy" (l.5) — convention section goes after l.11 |
| src/Context/Client/Domain/Client.php, Domain/ValueObject/ClientIdentity.php | Aggregate + identity invariants |
| src/Context/Client/Infrastructure/Persistence/Doctrine/{Entity/ClientEntity.php,Mapper/ClientMapper.php,Repository/DoctrineClientRepository.php} | Persistence (manual field copy) |
| src/Context/Client/Application/Command/CreateClient/ | Creation command |
| src/Context/Client/Infrastructure/Persistence/Doctrine/Repository/DoctrineClientReadRepository.php | Views, search |
| src/System/Taxation/Domain/ValueObject/CustomerTaxStatus.php | Existing enum (name collision) |
| src/Shared/Domain/ValueObject/ | Home for `Siren`, `FrenchVatNumber` VOs |
| assets/js/pages/settings.js:58-59 | Luhn + VAT key reference (JS) |
| src/Presentation/Clinic/Controller/Client/Sheet/ + templates/clinic/clients/view/index.html.twig + assets/js/pages/clients/sheet.js | Client sheet rail card |
| templates/clinic/clients/list/_modal_new_client.html.twig + assets/js/pages/clients/create.js + src/Presentation/Clinic/ViewModel/Directory/{DirectoryRegistrar,DirectoryClientInput,ClientLookupReader}.php | Directory creation |
| src/Context/Billing/Application/Command/RecordCounterSale/ | Counter sale |
| src/Context/Billing/Infrastructure/Adapter/Client/{CommandBusCounterClientRegistry,DbalPayerDirectory}.php | Billing → Client adapters |
| src/Context/Billing/Domain/ValueObject/Payer.php | Payer snapshot |
| templates/clinic/billing/index.html.twig + assets/js/pages/billing/register.js | Counter-sale UI |
| docs/frontend-theme/kiveto-theme/facturation-v-topbar_5.html:2553-2559,2668-2670,3014-3180 | Mockup pro tab |
| templates/clinic/document/{invoice,credit_note}.html.twig | "Facturé à" rendering |
| fixtures/Context/Client/Story/ClientDataStory.php, fixtures/Context/Billing/Story/BillingRegisterStory.php | Fixtures |

### Technical Decisions

- Outbox = Doctrine transport on the same connection; no outbox table, no relay. Valid only while (a) the transport is `doctrine://` on
  the business connection, (b) domain events and bridges are sync, (c) no `DispatchAfterCurrentBusStamp`. All three guarded by tests;
  routing asserted against the compiled `SendersLocator`.
- Professional = legal name + contact person (option a); first name stays mandatory on the contact.
- Scheduler lock in DB (`DoctrineDbalStore`, `shared__lock_keys`, explicit Shared migration); no stateful scheduler, no DB cache pool.
- Client enum name ≠ `CustomerTaxStatus` (Taxation already owns it) — see open decisions.
- `Siren` and `FrenchVatNumber` VOs in `src/Shared/Domain/ValueObject/` (Luhn and VAT key are our own guards, not norm rules).

### Confirmed decisions (Djamil, 2026-10-17)

1. Client enum `ClientTaxStatus` (`individual|french_business`) in the Client BC. Slice 1 note: Billing classification must align on the
   existing Taxation `CustomerTaxStatus` (`b2c|b2b|intracom|export`) rather than introduce a third enum.
2. Professional block = **one** JS module + **one** Twig partial + **one** server-side input parser, mounted in the counter "Professionnel"
   tab and the directory window, both ending in the same Client `CreateClient` command. Existing "particulier" forms untouched.
   An integration test drives both entry points and asserts the same command (with the same tax-profile payload) is dispatched.
3. `payer_attention`: nullable frozen **string** on `Payer` (invoice + credit note), built from the contact person
   (honorific + first name + last name) when the snapshot is taken. No structure. Maps to BT-56 in slice 1.
4. Fixes in scope: (a) `failed` transport gets `table_name: shared__messenger_messages` (table already created by
   `migrations/Shared/Version20251226020142.php`; the `queue_name` column separates `failed` rows); (b) **`auto_setup: false` on every
   Doctrine transport**, asserted by a test; (c) messenger async worker (make target + compose service); (d) the redundant
   `UpdateClientIdentity` in `CommandBusCounterClientRegistry` removed **in its own commit**.
5. BT-49 electronic address deferred to slice 1; the SIREN-based default is only a starting point, the real address must be confirmed by
   the directory (`DirectoryLookup`) in slice 1. No SIRET / trading name stored in slice 0. SIRENE search, Asso/Public badges beyond what
   the tax profile gives, B2G fields, e-invoice banner stay "bientôt".
6. The AFNOR documents in `docs/e-invoicing/` may be used (non-commercial use is allowed — clarified by Djamil).
7. Party-mode review (2026-10-17): `ClientCreated` carries a nullable `legalName` (no fake `ClientTaxProfileUpdated` at creation);
   `kind=pro` search bypasses the search index; professional contact = mobile, landline **or** e-mail (at least one); sort key
   `COALESCE(legal_name, last_name)` shared by list and search; Animal search owner label = legal name, else "first last" (in slice 0);
   B3 commit order fixed (see plan).

## Implementation Plan

Three branches, merged in order (one logical change each, `make ci` green before each commit, never push):
**B1** `chore/messaging-outbox-guards` → **B2** `chore/scheduler-lock` → **B3** `feature/client-tax-profile`
(B3's first commit is the standalone civility clean-up, Task 13).

B3 commit order, `make ci` green at **each** commit: (1) Task 13 civility · (2) Task 14 Shared VOs · (3) Tasks 15-17 + 17b Client domain,
persistence, application, Animal owner label · (4) Tasks 18-19 Billing payer/counter · (5) Tasks 20-24 UI (shared block, counter, directory,
sheet) · (6) Tasks 25-26 fixtures + entry-points test.

### Tasks

#### B1 — Transactional messaging guards (`chore/messaging-outbox-guards`)

- [x] Task 1: Make every Doctrine transport explicit and non-auto-setup
  - File: `config/packages/messenger.yaml`
  - Action: `async` → add `options.auto_setup: false` (keep `table_name`); `failed` → DSN `doctrine://default?queue_name=failed`,
    `options: { table_name: 'shared__messenger_messages', auto_setup: false }`.
  - Notes: no new migration — `shared__messenger_messages` already exists and has `queue_name`. Leave `.env` DSN as is (the YAML option wins
    over a missing query param; if both are set they must agree — the test in Task 2 enforces the effective value).

- [x] Task 2: Test the Doctrine transports configuration
  - File: `tests/Integration/System/Messenger/DoctrineTransportsConfigurationTest.php` (new, `KernelTestCase`)
  - Action: for every service tagged `messenger.receiver` whose class is
    `Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport` (at least `messenger.transport.async` and
    `messenger.transport.failed`): read the inner `Connection` (reflection on the private `connection` property) and assert
    `getConfiguration()['auto_setup'] === false` and `['table_name'] === 'shared__messenger_messages'`; assert the transport's DBAL connection is
    the same object as `doctrine.dbal.default_connection` (reflection on `driverConnection`).
  - Notes: fails if someone adds a Doctrine transport with auto-setup or on another connection.

- [x] Task 2b: Failure transport round-trip
  - File: `tests/Integration/System/Messenger/FailureTransportTest.php` (new, `KernelTestCase`)
  - Action: `send()` a real `Envelope(new TransactionalityProbeIntegrationEvent())` on `messenger.transport.failed`; assert a new row in
    `shared__messenger_messages` with `queue_name = 'failed'`; `get()` returns it; no `messenger_messages` table exists
    (`information_schema.tables` query — read-only, no DDL).

- [x] Task 3: Probe command for command-bus atomicity (test env only)
  - Files: `config/services_test.yaml` (new; loaded automatically by `MicroKernelTrait` for `APP_ENV=test`),
    `tests/Integration/System/Messenger/Probe/OutboxProbeCommand.php`, `tests/Integration/System/Messenger/Probe/OutboxProbeCommandHandler.php`
  - Action: register `App\Tests\Integration\System\Messenger\Probe\` with `autoconfigure: true`. The command carries `clinicId`,
    `clientId` and `failAt` (`none|after_publish`). The handler (`#[AsMessageHandler]`, command bus) creates a `Client` through
    `ClientRepositoryInterface::save()` (flush), publishes `TransactionalityProbeIntegrationEvent` through `IntegrationEventPublisher`, then
    throws `\RuntimeException` when `failAt = after_publish`.
  - Notes: reuse the existing `tests/Integration/System/Messenger/TransactionalityProbeIntegrationEvent.php`. Check the `tests/` namespace
    is autoloaded in `composer.json` `autoload-dev` (it is used by existing tests).

- [x] Task 4: Command-bus atomicity test
  - File: `tests/Integration/System/Messenger/CommandBusOutboxAtomicityTest.php` (new)
  - Action: dispatch `OutboxProbeCommand` through `CommandBusInterface`:
    - `failAt = after_publish` → expect the exception; assert **no** `client__clients` row for `clientId` and **no**
      `shared__messenger_messages` row whose body contains the probe class (count before/after);
    - `failAt = none` → both the client row and the message row exist.
  - Notes: DAMA + `use_savepoints: true` turn `doctrine_transaction` into a SAVEPOINT, so the rollback is observable. No DDL in the test.
    Keep the existing `AsyncTransportTransactionalityTest` unchanged.

- [x] Task 5: Routing guard against the compiled senders locator
  - File: `tests/Integration/System/Messenger/MessengerRoutingGuardTest.php` (new, `KernelTestCase`)
  - Action: get `messenger.senders_locator` from the test container. With Finder over `src/`, collect every concrete class implementing
    `App\Shared\Domain\Event\DomainEventInterface` and every one implementing `IntegrationEventInterface`; instantiate with
    `ReflectionClass::newInstanceWithoutConstructor()`, wrap in an `Envelope`, call `getSenders()` and collect sender aliases:
    domain events → only `sync` (or none); integration events → exactly `async`.
  - Notes: catches YAML changes **and** `#[AsMessage(transport: ...)]` attributes.

- [x] Task 6: Convention guards (bridges, stamps)
  - File: `tests/Integration/Shared/TransactionalMessagingConventionTest.php` (new, plain `TestCase`, style of
    `MessengerHandlersRegistrationTest.php`)
  - Action: (a) no occurrence of `DispatchAfterCurrentBusStamp` or `dispatch_after_current_bus` in `src/` and `config/`; (b) every
    `src/**/*IntegrationBridge.php` declares `#[AsMessageHandler(bus: 'messenger.bus.event')]` and no `fromTransport`.

- [x] Task 7: Messenger worker
  - Files: `compose.yaml`, `Makefile`
  - Action: add service `messenger-worker` mirroring `php-fpm` (same build, user, volume, env, network, `depends_on: [db]`) with
    `command: php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M -vv` and `restart: unless-stopped`.
    Make targets `worker-start`, `worker-stop`, `worker-logs` (compose up -d / stop / logs -f on the worker services) and add them to `.PHONY`
    and `make help`.

- [x] Task 8: Document the convention
  - File: `src/Shared/README.md` (after "Event dispatch policy", l.11)
  - Action: section "Transactional messaging (implicit outbox)": why it holds (Doctrine transport on the business connection, command-bus
    `doctrine_transaction`, sync domain events/bridges), the three guards and their tests, forbidden `DispatchAfterCurrentBusStamp`,
    `auto_setup: false`, the known gap (handlers consumed on `messenger.bus.integration_event` run without a transaction — tech debt), and
    how to run the worker.

#### B2 — Scheduler with a shared lock (`chore/scheduler-lock`)

- [x] Task 9: Install packages and the lock store
  - Files: `composer.json`/`composer.lock` (`symfony/lock:7.4.*`, `symfony/scheduler:7.4.*`), `config/packages/lock.yaml`, `.env`,
    `config/services.yaml`
  - Action: after the Flex recipe, do **not** use `framework.lock` with `LOCK_DSN=flock`: remove `LOCK_DSN` from `.env`, set
    `framework: { lock: false }` (or delete the recipe file) and declare `shared.lock.store`
    (`Symfony\Component\Lock\Store\DoctrineDbalStore`, args `@doctrine.dbal.default_connection`, `{ db_table: 'shared__lock_keys' }`) and
    `shared.lock.factory` (`Symfony\Component\Lock\LockFactory`, arg `@shared.lock.store`).
  - Notes: the rate limiter must keep working (it does not need `lock`; verify `make ci`).

- [x] Task 10: Lock table migration
  - File: `migrations/Shared/Version20261017100000.php` (new)
  - Action: `CREATE TABLE shared__lock_keys (key_id VARCHAR(64) NOT NULL, key_token VARCHAR(44) NOT NULL, key_expiration INT UNSIGNED NOT NULL,
    PRIMARY KEY(key_id))` (DoctrineDbalStore default columns); `down()` drops it.
  - Notes: name matches the `/^shared__/` filter of `make shared-migrations`. Ensure `doctrine/doctrine-bundle`'s lock schema listener
    does not try to create `lock_keys` (it only acts on stores it knows; our explicit table makes it a no-op).

- [x] Task 11: Example schedule
  - Files: `src/Shared/Infrastructure/Scheduler/DefaultScheduleProvider.php`, `src/Shared/Infrastructure/Scheduler/SchedulerHeartbeat.php`,
    `src/Shared/Infrastructure/Scheduler/SchedulerHeartbeatHandler.php` (new)
  - Action: `#[AsSchedule('default')]` provider returning `(new Schedule())->lock($lockFactory->createLock('scheduler.default'))
    ->add(RecurringMessage::every('1 hour', new SchedulerHeartbeat()))`; the handler only logs `scheduler heartbeat` (info) through the
    `LoggerInterface`. **No `->stateful()`.** PHPDoc on the provider states the rule: scheduled tasks must be idempotent and catch up by
    themselves (e.g. a period-closing task closes every ended, unclosed period).
  - Notes: the provider is the place where slice 1 adds `SyncPlatformFlows`, and where the PharmaceuticalRegistry weekly import can land later.

- [x] Task 12: Scheduler worker, tests, docs
  - Files: `compose.yaml`, `Makefile`, `tests/Unit/Shared/Infrastructure/Scheduler/DefaultScheduleProviderTest.php`,
    `tests/Unit/Shared/Infrastructure/Scheduler/SchedulerHeartbeatHandlerTest.php`,
    `tests/Integration/Shared/Lock/DoctrineDbalLockStoreTest.php`, `src/Shared/README.md`
  - Action: service `scheduler-worker` (same base as `messenger-worker`, `command: php bin/console messenger:consume scheduler_default
    --time-limit=3600 -vv`), included in `worker-start/stop/logs`; target `scheduler-debug` (`debug:scheduler`). Unit tests: the schedule has a
    lock and one recurring message every 3600 s; the handler logs. Integration test: two locks from `shared.lock.factory` on the same key — the
    second `acquire(false)` returns false while the first is held, and true after release (rows in `shared__lock_keys`). README section
    "Scheduled tasks": lock in DB, no stateful, idempotent/self-catching-up tasks.

#### B3 — Client tax profile & professional counter sale (`feature/client-tax-profile`)

- [x] Task 13 (own commit `refactor(billing): create counter clients in a single command`): single `CreateClient` dispatch
  - Files: `src/Context/Billing/Infrastructure/Adapter/Client/CommandBusCounterClientRegistry.php`,
    `tests/Unit/Context/Billing/Infrastructure/Adapter/CommandBusCounterClientRegistryTest.php`
  - Action: pass `civility` to `CreateClient`, remove the `UpdateClientIdentity` dispatch (:64); update the test (one dispatched command).

- [x] Task 14: Shared identifier VOs
  - Files: `src/Shared/Domain/ValueObject/Siren.php`, `src/Shared/Domain/ValueObject/FrenchVatNumber.php`,
    `src/Shared/Domain/ValueObject/InvalidSirenException.php`, `src/Shared/Domain/ValueObject/InvalidFrenchVatNumberException.php` (new; both
    exceptions extend `\InvalidArgumentException`, with a public `reason` string constant: `format|checksum` for SIREN,
    `format|siren_mismatch|key` for VAT)
  - Action: `Siren::fromInput(string)` strips spaces, NBSP, dots and dashes; a 14-digit input (SIRET) is reduced to its first 9 digits;
    must then be `^\d{9}$` (`format`) and pass Luhn (`checksum`); `value()`, `formatted()` (`356 000 000`), `equals()`.
    `FrenchVatNumber::fromInput(string, Siren)` upper-cases and strips spaces/dots; `^FR(\d{2})(\d{9})$` else `format`; embedded SIREN ≠
    given SIREN → `siren_mismatch`; key ≠ `(12 + 3 × (siren mod 97)) mod 97` → `key`; `FrenchVatNumber::forSiren(Siren)`; `value()`,
    `formatted()` (`FR 31 523456788`).
  - Notes: Luhn and the key are our own typo guards (not norm rules). Known limitation: legacy French VAT numbers with an alphabetic key are
    rejected (none expected for vet clients). Leave `src/Context/Regulatory/Jurisdiction/France/SireNumberValidator.php` untouched (out of scope).

- [x] Task 15: Client domain — tax profile
  - Files: `src/Context/Client/Domain/ValueObject/ClientTaxStatus.php` (enum `individual|french_business`, `label()`: « Particulier ou
    structure non assujettie » / « Professionnel assujetti à la TVA »), `src/Context/Client/Domain/ValueObject/ClientTaxProfile.php`,
    `src/Context/Client/Domain/Exception/InvalidClientTaxProfileException.php`, `src/Context/Client/Domain/Event/ClientTaxProfileUpdated.php`,
    `src/Context/Client/Domain/Client.php`
  - Action: `ClientTaxProfile` = `status`, `?legalName` (trimmed, null if empty, ≤ 255), `?Siren`, `?FrenchVatNumber`; factories
    `individual(?string $legalName)` and `frenchBusiness(string $legalName, Siren, ?FrenchVatNumber)` (empty legal name → exception);
    `default()` = individual without legal name; `hasLegalName()`, `equals()`. `Client`: new `taxProfile` property (default `default()` in
    `create()`, nullable arg in `reconstitute()` → default), `taxProfile()` getter, `updateTaxProfile(ClientTaxProfile, now)` (calls
    `ensureNotArchived()`, no-op if equal, records `ClientTaxProfileUpdated {clientId, clinicId, status, legalName, siren, vatNumber}`).
    `create()` gets an optional `?ClientTaxProfile $taxProfile = null` and **`ClientCreated` carries a nullable `legalName`** in its
    payload (backward compatible; existing consumers ignore it). **No `ClientTaxProfileUpdated` is recorded at creation** — that event is
    only for real updates. `ClientIdentityUpdated` payload also gains the current nullable `legalName` (so the Animal owner label stays
    right when only the contact changes).

- [x] Task 16: Client persistence
  - Files: `src/Context/Client/Infrastructure/Persistence/Doctrine/Entity/ClientEntity.php`, `.../Mapper/ClientMapper.php`,
    `.../Repository/DoctrineClientRepository.php`, `migrations/Client/Version20261017110000.php` (new)
  - Action: columns `tax_status VARCHAR(20) NOT NULL DEFAULT 'individual'`, `legal_name VARCHAR(255) NULL`, `siren CHAR(9) NULL`,
    `vat_number VARCHAR(13) NULL`, index `idx_client_clinic_siren (clinic_id, siren)`; map both ways; **copy the four fields in
    `DoctrineClientRepository::save()`**.

- [x] Task 17: Client application — commands and queries
  - Files: `src/Context/Client/Application/Command/CreateClient/{CreateClient,CreateClientHandler}.php`,
    `src/Context/Client/Application/Command/CreateClient/ClientTaxProfileDto.php` (new: `status`, `?legalName`, `?siren`, `?vatNumber` strings),
    `src/Context/Client/Application/Command/UpdateClientTaxProfile/{UpdateClientTaxProfile,UpdateClientTaxProfileHandler}.php` (new),
    `src/Context/Client/Application/Query/ListClientIdsBySiren/{ListClientIdsBySiren,ListClientIdsBySirenHandler}.php` (new),
    `src/Context/Client/Application/Port/ClientReadRepositoryInterface.php` + `DoctrineClientReadRepository.php`,
    `src/Context/Client/Application/Query/GetClientById/ClientView.php`, `.../SearchClients/{SearchClients,ClientListItemView}.php`,
    `src/Context/Client/Application/Search/` (index entry + new `OnClientTaxProfileUpdated` handler), `src/Context/Client/README.md`
  - Action: `CreateClient` gets `?ClientTaxProfileDto $taxProfile = null`, passed to `Client::create()` (no second call); a shared
    `ClientTaxProfileFactory` (Application) turns the DTO into the VO (invalid input → `InvalidClientTaxProfileException` / `InvalidSiren…` / `InvalidFrenchVat…` propagate). `UpdateClientTaxProfile`
    (`clinicId, clientId, ClientTaxProfileDto`). `findIdsBySiren(clinicId, siren)` includes archived clients (like phone).
    `ClientView` + `taxStatus, legalName, siren, vatNumber`; `ClientListItemView` + `legalName`. `SearchClients` gets `bool $withLegalNameOnly
    = false`; the name match (:334) also matches `legal_name LIKE` and an exact 9-digit `siren`. Search index entry carries `legalName`; `OnClientCreated` indexes it from the event payload, the new `OnClientTaxProfileUpdated`
    re-indexes on real updates. **Sort key `COALESCE(legal_name, last_name)`** (then first name, id) defined once in
    `DoctrineClientReadRepository::buildSearchOrderBy()` (:359) and used by both the directory list and the search.
    README: ubiquitous language (assujetti, raison sociale, contact), rules, data model, changelog.

- [x] Task 17b: Animal search — owner label uses the legal name
  - Files: `src/Context/Animal/Application/Search/OnAnimalCreated.php` (:44 SQL), `OnAnimalOwnersReplaced.php` (:33 SQL),
    `OnClientIdentityUpdated.php` (:26), new `src/Context/Animal/Application/Search/OnClientTaxProfileUpdated.php`, Animal search tests
  - Action: owner label = legal name when present, else `first last`: the two SQL reads become
    `COALESCE(NULLIF(legal_name, ''), CONCAT(first_name, ' ', last_name))`; `OnClientIdentityUpdated` uses `payload.legalName ??
    first last`; `OnClientTaxProfileUpdated` calls `updateOwnerName()` with `legalName ?? first last` (reads the current name from the
    payload; if the payload lacks first/last, read them with the same SQL). One handler per event.
  - Notes: no Animal `OnClientCreated` needed — a client's animals are indexed after the client exists (`OnAnimalCreated` reads the
    row). The raw cross-BC SQL on `client__clients` is pre-existing; not refactored here. If this grows beyond one handler per event, move
    it out with a note (Djamil's rule).

- [x] Task 18: Billing — payer attention
  - Files: `src/Context/Billing/Domain/ValueObject/Payer.php`, `src/Context/Billing/Application/Port/PayerSnapshotDto.php`,
    `src/Context/Billing/Infrastructure/Adapter/Client/DbalPayerDirectory.php`, invoice + credit-note Doctrine entities/mappers,
    `src/Context/Billing/Domain/CreditNote.php`, `migrations/Billing/Version20261017120000.php` (new), `DbalInvoiceReadRepository.php`
    (`InvoiceDetails`, `CreditNoteDetails`), `templates/clinic/document/invoice.html.twig:53-58`,
    `templates/clinic/document/credit_note.html.twig:31-35`, `src/Presentation/Clinic/ViewModel/Billing/BillingSheetBuilder.php:77,149`,
    `assets/js/pages/billing/register.js` (`paperHtml` :303)
  - Action: `Payer::create(?PayerId, string, list<string> = [], ?string $attention = null)` (trimmed, null if empty); `attention()`.
    `DbalPayerDirectory` selects `legal_name` and `civility` in **both** queries (`forClient` **and** `forAdmission`, both
    feeding `snapshot()` :92): with a legal name → `displayName = legal_name`, `attention = "<honorific>
    <first> <last>"` (honorific from civility, omitted when unspecified); without → unchanged, attention null. Columns `payer_attention
    VARCHAR(255) NULL` on invoices and credit notes (credit note copies it). Documents render « À l'attention de … » under the payer name
    when present; preview too.
  - Notes: drafts already re-read the client (`PrepareInvoiceForAdmissionHandler`), counter sales read once and issue at once — no
    freezing logic needed in slice 0.

- [x] Task 19: Billing — counter sale to a professional
  - Files: `src/Context/Billing/Application/Command/RecordCounterSale/{CounterSaleNewClient,RecordCounterSaleHandler}.php`,
    `src/Context/Billing/Application/Port/CounterClientRegistryInterface.php`, `CommandBusCounterClientRegistry.php`
  - Action: `CounterSaleNewClient` gets `?ClientTaxProfileInput` (Billing-side DTO: `status`, `?legalName`, `?siren`, `?vatNumber`); the
    adapter maps it to the Client `ClientTaxProfileDto` in the **same** `CreateClient`. For a new professional the payer is built like an
    existing client (`payerDirectory->forClient` after registration) so name/attention follow Task 18. Counter sale stays paid at once
    (`CounterSalePayer::NEW_CLIENT` / `CLIENT`, no new enum case). Duplicate phone rule unchanged; duplicate SIREN never blocks.

- [x] Task 20: Shared professional block (server side)
  - Files: `src/Presentation/Clinic/ViewModel/Client/TaxProfileInput.php` (new, single parser: `fromArray(array): ?TaxProfileInput`, keys
    `fisc` (`ind|pro`), `rs`, `siren`, `tva`; plus `errors(): array<string,string>` with French messages: « La raison sociale est
    obligatoire. », « SIREN invalide : 9 chiffres attendus. », « SIREN invalide : clé de contrôle incorrecte. », « N° de TVA invalide : format
    FR + 2 chiffres + SIREN. », « N° de TVA : il ne correspond pas au SIREN. », « N° de TVA invalide : clé incorrecte. »),
    `src/Presentation/Clinic/ViewModel/Directory/{DirectoryClientInput,DirectoryRegistrar}.php`,
    `src/Presentation/Clinic/Controller/Billing/RecordCounterSaleController.php`,
    `src/Presentation/Clinic/Controller/Billing/CounterClientsController.php`,
    `src/Presentation/Clinic/Controller/Client/Profile/ClientsBySirenController.php` (new, `GET /clients/by-siren?siren=` → cards via
    `ClientLookupReader::byIds`, for the duplicate warning), `src/Presentation/Clinic/ViewModel/Directory/ClientLookupReader.php`
  - Action: both `DirectoryClientInput::parse` and `RecordCounterSaleController::newClient()` delegate tax fields to `TaxProfileInput`, which
    yields the Client `ClientTaxProfileDto` / Billing input — no duplicated rules. `CounterClientsController` accepts `kind=pro` → `ClientLookupReader::search()` then
    queries **only** `SearchClients(withLegalNameOnly: true)` and **skips** `ListDirectoryMatches` (the index would return individuals). Lookup cards gain `rs` (legal name), `siren`, `tva`, `fisc` (`ind|pro`) and a badge (`Pro` for
    french_business, `Asso` for individual with legal name).

- [x] Task 21: Shared professional block (client side)
  - Files: `templates/clinic/clients/_tax_profile_block.html.twig` (new partial), `assets/js/shared/client-tax-profile.js` (new module:
    `mount(root, state, onChange)`, `validate(state) → {field: message}`, `toPayload(state)`; Luhn and key mirror the PHP VOs, SIRET paste
    → 9 digits, live formatting `356 000 000` / `FR 31 523456788`), `assets/styles/` (reuse existing form tokens)
  - Action: the partial renders, **before** the identity fields, the two-option selector « Particulier ou structure non assujettie à la TVA
    (association, refuge…) » / « Professionnel assujetti à la TVA »; then « Raison sociale » (facultatif for the first option, obligatoire
    for the second); SIREN « SIREN · 9 chiffres * » and « N° de TVA intracommunautaire (facultatif) » only for the second, with the hint
    « Une entreprise en franchise en base n'a pas toujours de n° de TVA. ». SIREN duplicate → warning « Un client porte déjà ce SIREN :
    <nom> » with « Ouvrir sa fiche » (never blocks).

- [x] Task 22: Counter "Professionnel" tab
  - Files: `templates/clinic/billing/index.html.twig` (:128 `#vtTy` enable `data-t="pro"`, hidden fields in `#factForm`),
    `assets/js/pages/billing/register.js` (type switch :814, `rendreZone` :572-594, `clientOk` :615, submit :882-893, remove comment :493)
  - Action: tab enabled. Search « Raison sociale ou SIREN… » (`kind=pro`), single group « Clients de la clinique »; the « Annuaire des
    entreprises · SIRENE » group shown disabled « bientôt ». Pro card (legal name, badge, « SIREN … · TVA … » or « sans SIREN », address,
    ✕). « Nouveau professionnel » mounts the shared block preset to « Professionnel assujetti », legal name first, then the contact person
    (civilité, NOM, Prénom) and contact means **mobile, fixe or e-mail — at least one** (stricter « mobile ou e-mail » rule stays for
    « Nouveau particulier »); server side `RecordCounterSaleHandler::register()` applies the same rule for professionals. Payment modes as for particulier; « Virement » and
    « À régler sous 30 jours » disabled « bientôt »; no e-invoice banner (or « bientôt »). Footer hints: « Choisissez le client » / missing
    fields. Submit posts `payer=cli` (existing) or `payer=pro` (new, with tax fields).

- [x] Task 23: Directory "Nouveau client" window
  - Files: `templates/clinic/clients/list/_modal_new_client.html.twig`, `assets/js/pages/clients/create.js` (`rendreNcCli` :372, `ncErr`
    :473, payload :609-623)
  - Action: mount the shared block at the top of the client column (default « Particulier… »); payload gains `fisc, rs, siren, tva`;
    `ncErr` merges `validate()`. Toast uses the legal name when present.

- [x] Task 24: Client sheet — « Profil fiscal » rail card, header, list
  - Files: `templates/clinic/clients/view/index.html.twig` (new `<section class="card" id="cFisc">` in the rail :60-64 + hidden `fiscForm`
    :141-157), `assets/js/pages/clients/sheet.js` (card via `carte()` from `assets/js/pages/dossier/kit.js:147`, editor mounts the shared
    block), `src/Presentation/Clinic/ViewModel/Client/ClientSheetBuilder.php` (`fisc` data; header shows the legal name above the contact
    when present), `src/Presentation/Clinic/Controller/Client/Sheet/UpdateClientTaxProfileController.php` (new, extends
    `AbstractClientSheetAction`, `POST /clients/{id}/tax-profile`, route `clinic_clients_tax_profile_update`, CSRF `client_sheet`, dispatches
    `UpdateClientTaxProfile`), directory list template/JS (row shows « Raison sociale · contact » when a legal name exists; ordering comes from the shared sort
    key of Task 17)
  - Action: read mode: status label, legal name, « SIREN 523 456 788 · TVA FR 31 523456788 » or « Pas de n° de TVA »; edit mode = shared
    block; errors shown inline; archived client → read-only (domain refuses).

- [x] Task 25: Fixtures
  - Files: `fixtures/Context/Client/Factory/ClientEntityFactory.php` (`withTaxProfile(status, ?legalName, ?siren, ?vat)`),
    `fixtures/Context/Client/Story/ClientDataStory.php`, `fixtures/Context/Billing/Story/BillingRegisterStory.php:271-280`
  - Action (Paris clinic): « Élevage des Calanques » — french_business, SIREN `523456788`, TVA `FR31523456788`, contact Mme Claire Rostand;
    « Centre équestre du Lac » — french_business, SIREN `812345676`, **no VAT** (franchise en base), contact M. Hugo Marchal;
    « Élevage des Calanques — site d'Aubagne » — second client sharing SIREN `523456788` (duplicate-SIREN warning);
    « Refuge des Collines » — individual **with** legal name, no SIREN, contact Mme Inès Perrin; one **archived** professional
    (« GAEC du Mont », SIREN `441569738`, TVA `FR14441569738`). BillingRegisterStory: one counter sale to « Élevage des Calanques »
    (attention « Mme Claire Rostand ») and one to « Refuge des Collines ».
  - Notes: the mockup shows « TVA FR 23 523456788 », which is **wrong** (key for 523456788 is 31) — use 31.

- [x] Task 26: Entry-points integration test (same command)
  - Files: `config/services_test.yaml` (decorate `App\Shared\Application\Bus\CommandBusInterface` with a recorder),
    `tests/Support/RecordingCommandBus.php` (new: delegates, records dispatched commands, `reset()`, `recorded(class)`),
    `tests/Integration/Presentation/ProfessionalClientEntryPointsTest.php` (new)
  - Action: same professional input sent (1) through `DirectoryRegistrar::createClient` and (2) through `RecordCounterSale` (command bus)
    → each records exactly one `CreateClient` whose `taxProfile` DTOs are equal (status, legal name, normalised SIREN, VAT); the two
    created clients have identical tax columns.

### Acceptance Criteria

**Messaging (B1)**
- [ ] AC 1: Given a command whose handler saves an aggregate and publishes an integration event then throws, when it is dispatched on the
  command bus, then neither the aggregate row nor the `shared__messenger_messages` row exists afterwards.
- [ ] AC 2: Given the same command without failure, when dispatched, then both rows exist.
- [ ] AC 3: Given the messenger configuration, when any Doctrine transport has `auto_setup` enabled, another table than
  `shared__messenger_messages`, or a connection other than `default`, then `DoctrineTransportsConfigurationTest` fails.
- [ ] AC 4: Given the compiled senders locator, when a domain event class is routed to a non-sync transport (YAML or `#[AsMessage]`), or an
  integration event to anything but `async`, then `MessengerRoutingGuardTest` fails.
- [ ] AC 5: Given the sources, when `DispatchAfterCurrentBusStamp` appears in `src/` or `config/`, or an `*IntegrationBridge` is not on
  `messenger.bus.event`, then `TransactionalMessagingConventionTest` fails.
- [ ] AC 6: Given a message that exhausts its retries, when it is moved to the failure transport, then it is stored in
  `shared__messenger_messages` with `queue_name = 'failed'` and no table is created at runtime.
- [ ] AC 7 [manuel]: Given `make worker-start`, when an integration event is published, then the `messenger-worker` container consumes it.

**Scheduler (B2)**
- [ ] AC 8: Given the default schedule, when it is built, then it holds a lock from `shared.lock.factory` and one recurring heartbeat
  every hour, and is not stateful.
- [ ] AC 9: Given two lock instances on the same key from `shared.lock.factory`, when the first is acquired, then the second
  non-blocking acquire fails, and succeeds after release.
- [ ] AC 10 [manuel]: Given `make worker-start`, when `make scheduler-debug` runs, then the `default` schedule is listed and the scheduler worker
  logs the heartbeat; with two scheduler workers running, a tick is handled once.

**Tax profile (B3)**
- [ ] AC 11: Given `Siren::fromInput(' 523.456.788 ')`, then the value is `523456788`; given the SIRET `35600000000048` (La Poste), then
  the value is `356000000`; given `523456789`, then `InvalidSirenException(checksum)`; given `52345678`, then `(format)`.
- [ ] AC 12: Given SIREN `523456788`, when the VAT is `fr 31 523 456 788` then it is accepted as `FR31523456788`; `FR23523456788` → `key`;
  `FR31123456789` → `siren_mismatch`; `FR3152345678` → `format`.
- [ ] AC 13: Given a professional form with status « Professionnel assujetti », when the legal name is empty, then creation is refused
  with « La raison sociale est obligatoire. » (UI and server).
- [ ] AC 14: Given status « Particulier ou structure non assujettie », when a legal name « Refuge des Collines » is entered without
  SIREN, then the client is created with that legal name and the SIREN/TVA fields are not shown nor accepted.
- [ ] AC 15: Given a professional without VAT number (franchise en base), when created, then it is accepted and the sheet shows « Pas de
  n° de TVA ».
- [ ] AC 16: Given an existing client with SIREN `523456788`, when another client is entered with the same SIREN, then a warning « Un
  client porte déjà ce SIREN » is shown with « Ouvrir sa fiche » and creation is still possible.
- [ ] AC 17: Given the counter « Professionnel » tab, when the user searches « Calanques » or « 523456788 », then only clients with a legal
  name are listed under « Clients de la clinique », the SIRENE group is disabled « bientôt ».
- [ ] AC 18: Given a counter sale to « Élevage des Calanques », when it is recorded, then the invoice payer name is « Élevage des
  Calanques », `payer_attention` is « Mme Claire Rostand », and the PDF shows « À l'attention de Mme Claire Rostand »; a credit note on it
  copies the attention.
- [ ] AC 19: Given a new professional created from the counter, when the sale is recorded, then exactly one `CreateClient` is dispatched
  (with civility and tax profile) and no `UpdateClientIdentity`.
- [ ] AC 20: Given the same professional input from the directory window and from the counter, then the same command with equal
  tax-profile payloads is dispatched (`ProfessionalClientEntryPointsTest`).
- [ ] AC 21: Given « Virement » and « À régler sous 30 jours » in the pro tab, then they are disabled with « bientôt ».
- [ ] AC 22: Given the client sheet of a professional, when the « Profil fiscal » card is edited and saved with valid data, then
  `UpdateClientTaxProfile` is dispatched, a `ClientTaxProfileUpdated` event is recorded and the card shows the new values; an archived
  client's card is read-only.
- [ ] AC 23: Given a draft visit invoice for a professional, when the legal name is corrected on the sheet and checkout is reopened,
  then the draft shows the corrected name (re-read until issued).
- [ ] AC 24: Given the same SIREN on a client of clinic A and of clinic B, when clinic A calls `findIdsBySiren`,
  `SearchClients(withLegalNameOnly)`, `GET /clients/by-siren` or the counter with clinic B's client id, then nothing from clinic B is
  returned or accepted.
- [ ] AC 25: Given a professional just created, when « Calanques » is searched in the directory, the counter pro tab and the animal
  search, then the client (and its animals, labelled « Élevage des Calanques ») are found immediately.
- [ ] AC 26: Given clients « Élevage des Calanques » (contact Rostand) and « Martin Alice », when the directory is sorted by name, then
  « Élevage des Calanques » sorts at E, and the search results use the same order.
- [ ] AC 27: Given a new professional at the counter with only a landline, when the sale is recorded, then it is accepted; a new
  particulier with only a landline is still refused.
- [ ] AC 28: Given a client is created, then `ClientCreated` carries `legalName` (null for a plain individual) and no
  `ClientTaxProfileUpdated` is recorded.

## Additional Context

### Dependencies

- New packages: `symfony/lock` 7.4.*, `symfony/scheduler` 7.4.* (Flex recipes; `allow-contrib: false`).
- No external service. No network in CI.
- B2 depends on B1 (worker targets/compose layout); B3 is independent of B2 but merged after it.
- Existing: Doctrine Messenger transport, DAMA test bundle with savepoints, Foundry.

### Testing Strategy

- **Unit:** `Siren`, `FrenchVatNumber` (all reasons, normalisation, SIRET paste, La Poste), `ClientTaxProfile`, `ClientTaxStatus`,
  `Client::updateTaxProfile` (event, no-op, archived), `ClientMapper` (tax fields), `CreateClientHandler` / `UpdateClientTaxProfileHandler`,
  `ListClientIdsBySirenHandler`, `Payer` attention, `RecordCounterSaleHandler` pro path, `CommandBusCounterClientRegistry` (single command),
  `TaxProfileInput` (French messages), `DefaultScheduleProvider`, `SchedulerHeartbeatHandler`.
- **Integration:** `CommandBusOutboxAtomicityTest`, `DoctrineTransportsConfigurationTest`, `MessengerRoutingGuardTest`,
  `TransactionalMessagingConventionTest`, `DoctrineDbalLockStoreTest`, `DoctrineClientRepositoryTest` + `DoctrineClientReadRepositoryTest`
  (tax columns, search by legal name/SIREN, `withLegalNameOnly`, `findIdsBySiren`, clinic scoping), `DbalPayerDirectoryTest` (legal name +
  attention, both `forClient` and `forAdmission`), invoice/credit-note persistence of `payer_attention`, `ProfessionalClientEntryPointsTest`,
  `FailureTransportTest`, `PrepareInvoiceForAdmissionHandler` re-read after a legal-name correction (AC 23), clinic-isolation tests with the
  same SIREN in two clinics (AC 24), Animal search owner label (`OnAnimalCreated`, `OnAnimalOwnersReplaced`, `OnClientIdentityUpdated`,
  `OnClientTaxProfileUpdated`), sort key `COALESCE(legal_name, last_name)` (AC 26).
- **Coverage:** 100 % line coverage on new/changed code in Client, Billing and Shared (check with `make test-coverage`; `make ci` does not
  enforce it).
- **Manual:** AC 7 and AC 10 (workers) are verified by hand, not automated.
- **Manual / Playwright** (scripts in the session scratchpad `pw/`): counter pro tab (search existing pro, create pro with/without VAT,
  every validation message, SIREN duplicate warning, SIRET paste), sale → invoice PDF « À l'attention de », directory window with both
  statuses, client sheet card edit, desktop + 390 px, no JS errors, all clinic pages 200. `make worker-start` + `docker compose logs` for
  both workers.

### Notes

- **High risk:** (1) `DoctrineClientRepository::save()` copies fields by hand — forgetting the tax columns silently drops data (covered by
  repository integration test). (2) The Flex lock recipe re-adding `LOCK_DSN=flock` — verify `.env` and `lock.yaml` after install. (3)
  Instantiating domain events without constructor in the routing test — only `getSenders()` is called, no handler runs. (4) The messenger
  worker starts consuming integration events that were never consumed in dev before — first start may process a backlog; reset the dev DB
  after merge as usual.
- **Known limitations:** legacy alphabetic-key French VAT numbers rejected; no VIES/Sirene; no SIRET/trading name/electronic address
  stored; professional sales are paid at the counter only; handlers on `messenger.bus.integration_event` still run without a transaction
  (tech-debt PR).
- **Slice 1 reminders:** freeze the payer snapshot (incl. SIREN/VAT/attention) at `Issuing`; `ElectronicInvoiceReadiness` before
  `InvoiceNumberGenerator::next()`; align Billing classification on Taxation `CustomerTaxStatus`; BT-49 electronic address confirmed via
  `DirectoryLookup`; credit sales (payment terms, PMT/PMD/AAB notes, S1/M1 vs S2/M2); mixed Acts+Articles → M as a cautious default, but
  Z12-014 case 31 says a mixed invoice follows the main operation and M is for independent operations — confirm with the accountant;
  « action requise » on 210/213; vendor CII D22B + CDAR XSDs with READMEs.

## Implementation Notes (2026-10-17)

Branches (stacked, not merged): `chore/messaging-outbox-guards` (B1) → `chore/scheduler-lock` (B2) →
`feature/client-tax-profile` (B3). `make ci` green on each commit, except the one-line B2 follow-up
`fix(scheduler): declare the lock table to the Doctrine schema`, validated by the next B3 run.

Deviations from the plan:
- Workers (`messenger-worker`, `scheduler-worker`) run under the compose profile `workers`, so `make install`
  never starts them before vendors and the database exist; `make stop`/`kill` include the profile. Their command
  runs PHP with `memory_limit=512M` (the dev container cache rebuild exceeds the 128M CLI default).
- `shared.lock.store` is tagged `lock.store` so the Doctrine schema listener knows `shared__lock_keys` (otherwise
  `make shared-migrations` would generate a `DROP TABLE`). The lock factory is exposed to tests through the
  test-only alias `test.shared.lock.factory`.
- `FailureTransportTest` asserts that no `messenger_messages` table is *created* by a send (the test DB still had one,
  left by the former auto-setup of the `failed` transport).
- `ClientTaxProfileUpdated` also carries the contact's first/last name (Animal owner label without an extra read).
  The client search index stores the legal name in a new nullable `client__search_entries.legal_name` column.
- AC 27 second half (a new particulier with only a landline refused): not enforced — the existing « Nouveau
  particulier » form (JS and server) already accepts any French number; decision 2 keeps that form untouched.
- AC 10 « two scheduler workers handle a tick once » is covered by the lock test (AC 9), not by a manual run.

## Review Notes

- Adversarial review completed (17 findings).
- Findings: 17 total, 8 fixed, 9 skipped (5 noise, 4 left for a decision).
- Resolution approach: auto-fix, with the following directions. Each fix sits on the branch that introduced the problem,
  one commit per finding, referenced in the message.

Fixed:
- **F1** (`chore/scheduler-lock`): the scheduler worker stops after 3540 s instead of 3600 s, so it never restarts on an
  hourly tick; Docker's restart policy brings it back. A test keeps the time limit off any multiple of the shortest
  task period.
- **F2**: the counter « Professionnel » tab asks for the legal name whatever the status (block and server,
  `structureOnly`).
- **F4**: the « refuse ses coordonnées » flag only counts on the « Particulier » tab. No UI path was found to leak
  it into the « Professionnel » tab; the flag is now scoped by construction.
- **F8**: the sheet's « Facturation » link searches the invoices by the legal name when the client has one.
- **F9**: no silent cut of the legal name any more (server `mb_substr` at 120 and the field's `maxlength` both gone).
  The columns were already `VARCHAR(255)`; beyond 255 characters the block and the server say so.
- **F10**: the SIREN rules (length, key, a pasted SIRET reduced to its SIREN) live on the server only (`Siren`,
  `TaxProfileInput::checkSiren()`). The block sends the cleaned digits as typed and learns the verdict through
  `GET /clients/by-siren`, which now also returns `siren` and `error`. The SIREN and VAT fields no longer cut a paste.
- **F11**: the counter sale checks a structure's tax profile itself (`InvalidCounterClientTaxProfileException`, worded by
  `BillingErrorMessage`); the controller's broad `catch (\InvalidArgumentException)` is gone. `ClientTaxProfileFactory`
  reports an unknown status as `InvalidClientTaxProfileException::STATUS_UNKNOWN` instead of a `ValueError`.
- **F12**: a professional who cannot be read back after its creation fails the sale
  (`CounterClientNotReadBackException`, whole sale rolled back) instead of billing the contact.

Skipped:
- Noise: F5 (SIREN/VAT in the payer snapshot, slice 1), F7 (DAMA savepoints, accepted), F13 (landline labelled
  « mobile », existing behaviour), F14 (Animal reads `client__clients`, existing pattern), F16 (compose hardening).
- Left for a decision: F3 (a professional whose contact's phone is already on file cannot be created at the
  counter), F6 (the lock shares the business DBAL connection), F15 (event payloads gained `legalName` without a
  `VERSION` bump), F17 (`COALESCE` sort without an index; a pasted SIRET in the pro search finds nothing).

Found while covering the Client context:
- `app:search:reindex` labelled animals with their owner's person name while the live index uses a structure's
  legal name; it now shares the rule (`fix(search)`, with the command's first integration test).
- Client and Billing are back to 100% line coverage. The client search index is covered by integration tests that
  write through `ClientSearchEntryWriter` and read back, and `ClientSearchProvider` has a unit test. An unreachable
  guard was dropped from `DoctrineClientSearchRepository`.

