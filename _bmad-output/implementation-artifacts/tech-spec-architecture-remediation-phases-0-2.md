---
title: 'Architecture Remediation Phases 0-2: Hygiene, i18n Foundations & Regulatory Decoupling'
slug: 'architecture-remediation-phases-0-2'
created: '2026-05-02'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['PHP 8.3', 'Symfony 7', 'Doctrine ORM', 'Zenstruck Foundry', 'PHPUnit', 'Twig', 'Tailwind CSS v4']
files_to_modify:
  - 'src/Context/Admission/Infrastructure/Adapter/Regulatory/ICADLookupAdapter.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicEntity.php'
  - 'src/Context/Client/Infrastructure/Persistence/Doctrine/Entity/ClientEntity.php'
  - 'src/System/IdentityAccess/Infrastructure/Persistence/Doctrine/Entity/UserEntity.php'
  - 'src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Entity/PlanningBlockEntity.php'
  - 'src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Entity/AppointmentEntity.php'
  - 'src/Context/Scheduling/Domain/ValueObject/PlanningBlockType.php'
  - 'src/Context/Animal/Domain/ValueObject/RegistryType.php'
  - 'src/Context/Animal/Domain/ValueObject/Identification.php'
  - 'src/Context/Clinic/Domain/Clinic.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicEntity.php'
  - 'src/Context/Admission/Domain/ValueObject/IntakeChannel.php'
  - 'src/Context/Admission/Domain/ValueObject/ClosureReason.php'
  - 'src/Context/Admission/Domain/ValueObject/PresenterRole.php'
  - 'src/System/Translation/Infrastructure/Resolver/DefaultLocaleResolver.php'
  - 'src/Context/Regulatory/Domain/MairieNotification.php'
  - 'src/Context/Regulatory/Domain/ICADLookup.php'
  - 'src/Context/Regulatory/Domain/StrayCustody.php'
code_patterns:
  - 'DDD aggregates with AggregateRoot base class and domain event publishing'
  - 'Doctrine entity/mapper separation from domain aggregates'
  - 'Foundry PersistentProxyObjectFactory with named builder methods'
  - 'Manual POST form handling in controllers (no Symfony Form component for Clinic)'
  - 'Session-based clinic context via CurrentClinicContextInterface'
  - 'Local ClinicId VO per BC extending AbstractUuidId'
  - 'Enum string-backed with French values in DB (to be renamed)'
test_patterns:
  - 'PHPUnit unit tests in tests/Unit/Context/<BC>/'
  - 'Abstract contract test base classes for interface compliance'
  - 'Zenstruck Foundry Story classes for fixture orchestration'
  - 'TranslationEntryEntityFactory for seeding Translation BC entries'
---

# Tech-Spec: Architecture Remediation Phases 0-2

**Created:** 2026-05-02

> **Alpha context:** Project is in local alpha. DB schema/data changes follow one pattern only: (1) update Foundry factories, (2) update fixture seeders, (3) `make migrate-db && make load-fixtures`. No multi-step data migrations (UPDATE … SET … WHERE …) — not relevant at this stage. Enum renames propagate via fixture updates, not data migrations.

## Overview

### Problem Statement

The Kiveto codebase has been architected cleanly (DDD, ACL, bounded contexts) but carries three categories of accumulated debt identified by the cartography audit:

1. **Hygiene gaps**: an ICAD adapter stub that was never wired, missing optimistic-locking `@Version` on several aggregates, four obsolete READMEs, French-named PHP enum cases in `PlanningBlockType`, and `ClinicId` imported from Clinic BC in Animal and Client instead of using local VOs.
2. **Absent i18n foundations**: the `Clinic` aggregate has no `countryCode`, `jurisdictionCode`, or `currencyCode`; shared VOs `CountryCode` and `CurrencyCode` don't exist; `defaultLocale` is hard-coded in two places (Translation BC + Symfony framework config); and Admission enums still contain France-specific terms (`Municipality`).
3. **Structural France coupling in Regulatory**: aggregate names (`MairieNotification`, `ICADLookup`) are France-specific; there is no `RegulatoryPolicyInterface` or `WorkingDayCalculatorInterface`; no jurisdiction sub-namespace exists; and `Animal::RegistryType` + `SIRENumber` are not parameterisable.

Without addressing these, adding a second legal jurisdiction (DE/BE/UK) would require breaking changes across DB schema, domain model, and application code in every BC.

### Solution

Execute the three phases in sequence, each building on the last:

- **Phase 0** (week 1): close existing gaps with minimal risk — wire ICADLookupAdapter, add `@Version`, refresh READMEs, rename PlanningBlockType cases to English (DB values + fixtures + Twig templates + Translation keys), create local `ClinicId` VOs in Animal and Client.
- **Phase 1** (weeks 2-3): lay the i18n foundation — add `CountryCode`/`JurisdictionCode`/`CurrencyCode` VOs to Shared, enrich `Clinic` aggregate and backoffice form, externalise `defaultLocale` from both config locations, neutralise Admission enum values (DB + fixtures + Twig + Translation keys).
- **Phase 2** (weeks 4-7): decouple France from Regulatory — rename aggregates and DB tables (DROP/CREATE migration), extract `RegulatoryPolicyInterface` + `WorkingDayCalculatorInterface` with abstract contract tests, implement `Regulatory/Jurisdiction/France/` sub-namespace wired via `JurisdictionResolverInterface`, refactor `Animal::RegistryType` and `SIRENumber` to pluggable VOs.

### Scope

**In Scope:**
- Phase 0.1: Wire `ICADLookupAdapter::initiateChipLookup()` to dispatch `OpenICADLookup` command via command bus to Regulatory BC
- Phase 0.2: Add `#[Version]` attribute to `ClinicEntity`, `ClientEntity`, Scheduling entities (PlanningBlock/Slot), `User` entity in IdentityAccess — generate migrations
- Phase 0.3: Rewrite READMEs for Clinic, Scheduling, Consultation, IdentityAccess BCs (English, accurate)
- Phase 0.4: Rename `PlanningBlockType` PHP enum cases + DB values + Foundry factories + fixtures + Twig templates + Translation BC keys (CHIRURGIE→SURGERY/surgery, BILAN→HEALTH_CHECK/health_check, URGENCE→EMERGENCY/emergency, GARDE→ON_CALL/on_call, CONGE→LEAVE/leave, FORMATION→TRAINING/training; CONSULTATION and ADMIN unchanged)
- Phase 0.5: Create `ClinicId` local VO in Animal BC and Client BC; replace all `use App\Context\Clinic\Domain\ValueObject\ClinicId` imports; update corresponding Doctrine entity mappings and Foundry factories; generate migrations if schema changes
- Phase 1.1: Promote `CountryCode` (ISO 3166-1 alpha-2) and `CurrencyCode` (ISO 4217) to `Shared` VOs
- Phase 1.2: Add `countryCode: CountryCode` (required), `jurisdictionCode: ?string` (nullable, ISO 3166-2 format e.g. `FR-67`), `currencyCode: CurrencyCode` (required) to `Clinic` aggregate, Doctrine entity, read models, backoffice create/edit form, and Foundry `ClinicFactory`
- Phase 1.3: Externalise `defaultLocale` from both hard-coded locations: Translation BC internals AND `config/packages/framework.yaml` `default_locale` — both should read from a single `%app.default_locale%` parameter
- Phase 1.4: Rename Admission enums: `IntakeChannel::EmergencyByMunicipality→EmergencyByAuthority`, `ClosureReason::HandedToMunicipality→HandedToAuthority`, remove `PresenterRole::Municipality`; update DB values + Foundry factories + fixtures + Twig templates (`templates/clinic/admission/*.twig`) + Translation BC keys; update all unit tests that hardcode the old enum values
- Phase 2.1: Rename Regulatory aggregates and DB tables (DROP old table + CREATE new table in migration, `make load-fixtures` after): `MairieNotification→AuthorityNotification`, `ICADLookup→MicrochipRegistryLookup` (+ all related classes, events, commands, handlers, repositories, Foundry factories, fixtures, tests)
- Phase 2.2: Extract `RegulatoryPolicyInterface` (stray notification deadlines, authority transfer triggers, etc.) and `WorkingDayCalculatorInterface` from concrete France implementations; provide abstract contract tests `JurisdictionPolicyContractTest` and `WorkingDayCalculatorContractTest` that every future jurisdiction implementation must extend
- Phase 2.3: Introduce `Regulatory/Jurisdiction/France/` sub-namespace; move France-specific implementations there; wire via `JurisdictionResolverInterface` (stateless service that resolves `ClinicId → Jurisdiction` at runtime via DB lookup, using `MessageContext::currentClinicId()` from the existing Messenger middleware); service container aliases updated atomically with the namespace move
- Phase 2.4: Refactor `Animal::RegistryType` enum (neutral cases only; SIRE-specific logic moves to France sub-namespace) and `SIRENumber` VO to a generic `RegistryNumber` VO (`#[Embedded]` with `registry_type VARCHAR` + `registry_value VARCHAR` columns); update Foundry `AnimalFactory` and fixtures
- Phase 2.5: Investigate Translation BC data model and seed jurisdiction→UI-label mappings for Regulatory terms (`AuthorityNotification`, `MicrochipRegistryLookup`, jurisdiction-specific deadline labels)

**Out of Scope:**
- Real HTTP integration with the ICAD external API (Phase 0.1 is command-bus dispatch only)
- Phase 3 (IdentityAccess security, GDPR, RFC 5545 recurrence, Vitals units, Search promotion)
- Phase 4 (Billing BC, international breed catalogues, B2B client)
- New jurisdiction implementations (e.g. DE, BE, UK) — France sub-namespace is implemented, the interface + resolver mechanism enables future additions
- `StaffRolePermissionMap` parameterisation
- DLQ for async listeners

## Context for Development

### Codebase Patterns

- **Doctrine entity/mapper separation**: Doctrine entities live in `Infrastructure/Persistence/Doctrine/Entity/`, domain aggregates in `Domain/`. A `*Mapper` class bridges them. `#[Version]` attribute on `private int $version` field enables optimistic locking (already present in Regulatory, Admission, Animal, Patient entities; absent from Clinic, Client, Scheduling, IdentityAccess).
- **Local ClinicId VO pattern**: each BC owns its own `ClinicId` that extends `AbstractUuidId` with a single `fromString(string): self` factory. Both Animal and Client currently import `App\Context\Clinic\Domain\ValueObject\ClinicId` — 17 files in Animal, 16 in Client (Domain + Application + Infrastructure layers). **No schema migration needed**: Doctrine maps `clinicId` as plain `UuidType::NAME` column in both BCs.
- **Enum DB values (alpha)**: rename = update PHP enum case + update `->value` string + update Foundry factory defaults + update fixture Stories. No `UPDATE … WHERE` data migrations. Migration alters the ENUM column definition only if Doctrine uses native ENUM type (verify per-column).
- **ICADLookupAdapter wiring**: `ICADLookupPort::initiateChipLookup(string $chipNumber, string $clinicId): void`. Dispatch `new OpenICADLookup(chipNumber: $chipNumber, clinicId: $clinicId)` via `MessageBusInterface`. `OpenICADLookupHandler` already fully implemented — just needs the adapter to call it.
- **Shared VOs pattern**: `src/Shared/Domain/ValueObject/`. Private constructor, static `fromString()` factory with validation, `equals(self $other): bool`, no external dependencies. See `EmailAddress` as simplest reference.
- **Clinic aggregate**: rich aggregate with domain events, state machine (ACTIVE/SUSPENDED/CLOSED). Backoffice uses manual POST form handling (no Symfony Form component) — fields read via `$request->request->get('field_name')`. New fields need CSRF-validated POST params added to controller and entity.
- **CurrentClinicContextInterface** (NOT MessageContext — does not exist): session-based service. `getCurrentClinicId(): ?ClinicId`. Controllers inject it to get the active clinic. `JurisdictionResolverInterface` should accept `ClinicId` directly from command and do a DBAL lookup for `countryCode` — no session dependency in domain layer.
- **Translation defaultLocale — two hardcoded locations**: (1) `DefaultLocaleResolver::__construct(…, string $defaultLocale = 'fr-FR')` constructor default; (2) hardcoded `return Locale::fromString('fr-FR')` in the backoffice scope branch. Both to be replaced by a `%app.locale.backoffice%` (or `%app.default_locale%`) Symfony parameter. Framework `translation.yaml` already has `default_locale: en` (not FR).
- **Regulatory aggregates deadline hardcoding**: `MairieNotification::schedule()` hardcodes `$admissionOpenedAt->modify('+48 hours')`. `StrayCustody::begin()` calls `$calculator->addWorkingDays($admissionOpenedAt, 8)` injecting `FrenchWorkingDayCalculator`. Both deadlines move behind `RegulatoryPolicyInterface`.
- **sireNumber is a field in `Identification` VO** (not a standalone VO): `Identification` has `registryType: RegistryType`, `registryNumber: ?string`, `sireNumber: ?string`. `RegistryType` enum: `NONE`, `LOF`, `LOOF`, `OTHER`. The refactor (Phase 2.4) targets making `sireNumber` jurisdiction-neutral.
- **Translation BC seeding**: No dedicated seeder command. Translations are seeded via `TranslationEntryEntityFactory` instances in Foundry Story classes. Entity has: `appScope` (string), `locale` (string), `domain` (string), `translationKey` (string), `translationValue` (text). Unique constraint on `(appScope, locale, domain, translationKey)`.
- **StrayCustodyStatus scope extension (Phase 2.1)**: `StrayCustodyStatus::ClosedHandedToMunicipality` enum case + `StrayCustody::closeHandedToMunicipality()` method + `StrayCustodyClosedHandedToMunicipality` domain event — all renamed to `Authority` variants alongside the MairieNotification → AuthorityNotification rename.
- **Foundry factories**: `PersistentProxyObjectFactory` subclasses with named builder methods (`withId()`, `withClinicId()`, `dog()`, etc.) and `defaults(): array` returning the full field set. Every new field or renamed enum value requires updating the factory's `defaults()` and any builder methods that reference old values.
- **Twig enum references**: templates use hardcoded string maps (e.g. `'emergency_by_municipality': 'Urgence mairie'` in `queue.html.twig`). Search `templates/` for old string values after every rename.
- **Contract tests (Phase 2)**: `abstract class JurisdictionPolicyContractTest extends TestCase` with abstract `createPolicy(): RegulatoryPolicyInterface`. France implementation test extends it. Same for `WorkingDayCalculatorContractTest`. Ensures jurisdiction compliance.
- **PHPStan level: max** — all new code fully typed; no `mixed`, no untyped arrays at API boundaries.
- **`make ci` must be green** after each phase: php-cs-fixer → phpcs → phpstan → tailwind-build → test (100% line coverage per BC excl. Presentation).

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Context/Admission/Infrastructure/Adapter/Regulatory/ICADLookupAdapter.php` | Stub to wire — dispatch `OpenICADLookup` (Phase 0.1) |
| `src/Context/Admission/Application/Port/ICADLookupPort.php` | Port interface: `initiateChipLookup(string $chipNumber, string $clinicId): void` |
| `src/Context/Regulatory/Application/Command/OpenICADLookup/OpenICADLookup.php` | Command: `__construct(public string $chipNumber, public string $clinicId)` |
| `src/Context/Regulatory/Application/Command/OpenICADLookup/OpenICADLookupHandler.php` | Handler: already complete, just needs the adapter to dispatch |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicEntity.php` | Add `#[Version]` + future countryCode/jurisdictionCode/currencyCode (Phase 0.2, 1.2) |
| `src/Context/Client/Infrastructure/Persistence/Doctrine/Entity/ClientEntity.php` | Add `#[Version]` (Phase 0.2) |
| `src/System/IdentityAccess/Infrastructure/Persistence/Doctrine/Entity/UserEntity.php` | Add `#[Version]` — abstract base with SINGLE_TABLE inheritance (Phase 0.2) |
| `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Entity/PlanningBlockEntity.php` | Add `#[Version]` (Phase 0.2) |
| `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Entity/AppointmentEntity.php` | Add `#[Version]` (Phase 0.2) |
| `src/Context/Patient/Domain/ValueObject/ClinicId.php` | Canonical local ClinicId VO — extends `AbstractUuidId`, `fromString(): self` only |
| `src/Context/Scheduling/Domain/ValueObject/PlanningBlockType.php` | Enum: 8 cases, 6 to rename; has `acceptsAppointments()` and `hasCapacityLimit()` methods (Phase 0.4) |
| `fixtures/Context/Scheduling/Story/SchedulingPlanningBlockStory.php` | Story creating all 8 PlanningBlockType variants — update enum values (Phase 0.4) |
| `tests/Unit/Context/Scheduling/Domain/PlanningBlockTest.php` | Unit test likely referencing enum cases (Phase 0.4) |
| `tests/Unit/Context/Scheduling/Domain/ValueObject/PlanningBlockTypeCapabilityCompletenessTest.php` | Completeness test for `acceptsAppointments()` / `hasCapacityLimit()` — update cases (Phase 0.4) |
| `src/Context/Animal/Domain/Animal.php` | 17 files in Animal BC import `Clinic\ClinicId` (Phase 0.5) |
| `src/Context/Client/Domain/Client.php` | 16 files in Client BC import `Clinic\ClinicId` (Phase 0.5) |
| `src/Shared/Domain/ValueObject/EmailAddress.php` | Reference pattern for new CountryCode/CurrencyCode VOs (Phase 1.1) |
| `src/Context/Clinic/Domain/Clinic.php` | Aggregate to enrich: add countryCode, jurisdictionCode, currencyCode properties + factory + reconstitute (Phase 1.2) |
| `src/Context/Clinic/Application/Query/Clinic/GetClinic/ClinicDto.php` | Read model to extend with new fields (Phase 1.2) |
| `src/Presentation/Backoffice/Controller/ClinicController.php` | Manual POST form: add 3 new `$request->request->get()` reads (Phase 1.2) |
| `fixtures/Context/Clinic/Factory/ClinicEntityFactory.php` | Foundry factory to update with new fields (Phase 1.2) |
| `src/System/Translation/Infrastructure/Resolver/DefaultLocaleResolver.php` | Two 'fr-FR' hardcodes to externalise (Phase 1.3) |
| `src/Context/Admission/Domain/ValueObject/IntakeChannel.php` | `EmergencyByMunicipality='emergency_by_municipality'` → `EmergencyByAuthority='emergency_by_authority'` (Phase 1.4) |
| `src/Context/Admission/Domain/ValueObject/ClosureReason.php` | `HandedToMunicipality='handed_to_municipality'` → `HandedToAuthority='handed_to_authority'` (Phase 1.4) |
| `src/Context/Admission/Domain/ValueObject/PresenterRole.php` | Remove `Municipality='municipality'` case (`Authority` already exists) (Phase 1.4) |
| `templates/clinic/admission/queue.html.twig` | Has `'emergency_by_municipality': 'Urgence mairie'` label map (Phase 1.4) |
| `templates/clinic/admission/emergency_form.html.twig` | Has `'municipality'` in presenter role dropdown (Phase 1.4) |
| `tests/Unit/Context/Regulatory/StrayCustodyTest.php` | References `HandedToMunicipality` and `ClosedHandedToMunicipality` (Phase 1.4 + 2.1) |
| `src/Context/Regulatory/Domain/MairieNotification.php` | Rename to `AuthorityNotification`; deadline `+48 hours` hardcoded in `schedule()` → move behind interface (Phase 2.1, 2.2) |
| `src/Context/Regulatory/Domain/ICADLookup.php` | Rename to `MicrochipRegistryLookup` (Phase 2.1) |
| `src/Context/Regulatory/Domain/StrayCustody.php` | Rename `closeHandedToMunicipality()` → `closeHandedToAuthority()` + rename `StrayCustodyStatus::ClosedHandedToMunicipality` (Phase 2.1) |
| `src/Context/Regulatory/Domain/Service/FrenchWorkingDayCalculator.php` | Extract `WorkingDayCalculatorInterface`; France impl moves to `Jurisdiction/France/` (Phase 2.2, 2.3) |
| `src/Shared/Application/Context/CurrentClinicContextInterface.php` | Session-based clinic context; `JurisdictionResolverInterface` does NOT use this — resolves from `ClinicId` in command via DBAL (Phase 2.3) |
| `src/Context/Animal/Domain/ValueObject/Identification.php` | Contains `sireNumber: ?string` + `registryType: RegistryType` + `registryNumber: ?string` — refactor target (Phase 2.4) |
| `src/Context/Animal/Domain/ValueObject/RegistryType.php` | Enum: `NONE`, `LOF`, `LOOF`, `OTHER` — refactor to neutral cases (Phase 2.4) |
| `fixtures/System/Translation/Factory/TranslationEntryEntityFactory.php` | Factory for seeding Translation entries — use in a Story for Phase 2.5 seeds |
| `docs/cartography/README.md` | Cartography audit reference |

### Technical Decisions

- **ClinicId local VOs (Phase 0.5)**: copy exact shape of `Patient/Domain/ValueObject/ClinicId.php` — `final class ClinicId extends AbstractUuidId` with `fromString(string): self` only. No schema migration (Doctrine already maps as `UuidType::NAME` column). Touch 17 Animal files + 16 Client files for import replacement.
- **PlanningBlockType rename (Phase 0.4)**: update PHP enum case names + `->value` strings. `acceptsAppointments()` and `hasCapacityLimit()` match blocks reference `self::CONGE` etc. — update to new case names. Update `SchedulingPlanningBlockStory` + 5 test files. Check `PlanningBlockEntity` column type (string or native ENUM) — if native ENUM, migration alters column.
- **`PresenterRole::Municipality` removal (Phase 1.4)**: grep all of `templates/`, `tests/`, `src/` for `'municipality'` string. `emergency_form.html.twig` has it in a dropdown. Update fixture to use `'authority'`. Then delete the PHP case.
- **JurisdictionResolverInterface (Phase 2.3)**: stateless service injected into Regulatory handlers. Takes `ClinicId` from command and does a DBAL read on `clinic.country_code` to return the jurisdiction string. No session dependency. `CurrentClinicContextInterface` is NOT used here (it's HTTP-session-coupled; handlers must work outside HTTP context).
- **`RegulatoryPolicyInterface` surface**: based on investigation — `getAuthorityNotificationDelay(): \DateInterval` (returns `PT48H` for France) and `getStrayCustodyWorkingDays(): int` (returns `8` for France). Aggregate `schedule()`/`begin()` methods receive the policy as a parameter instead of hardcoding.
- **`WorkingDayCalculatorInterface` surface**: `addWorkingDays(\DateTimeImmutable $from, int $days): \DateTimeImmutable` and `isWorkingDay(\DateTimeImmutable $date): bool`. `FrenchWorkingDayCalculator` moves to `Regulatory/Jurisdiction/France/` and implements this interface.
- **Regulatory aggregate renaming (Phase 2.1)**: alpha — DROP old table + CREATE new table in migration. All file/class renames in one commit. `StrayCustodyStatus::ClosedHandedToMunicipality` → `ClosedHandedToAuthority` + `closeHandedToMunicipality()` → `closeHandedToAuthority()` + `StrayCustodyClosedHandedToMunicipality` event → `StrayCustodyClosedHandedToAuthority`.
- **`Identification` VO refactor (Phase 2.4)**: `sireNumber: ?string` is inside `Identification` VO (not standalone). Refactor: rename `sireNumber` → generic `registryReference: ?string` (or keep as second registry slot with a jurisdiction-specific key). `RegistryType` enum: neutralise `LOF`/`LOOF` to remain as-is (breed-registry neutral enough) or add `NONE`/`BREED_REGISTRY`/`OTHER`. SIRE format validation moves to France sub-namespace. Exact VO shape to decide at implementation based on consistency with `Identification::ensureConsistency()` invariants.
- **Translation BC seeding (Phase 2.5)**: create a new Foundry Story class (e.g. `RegulatoryTranslationStory`) using `TranslationEntryEntityFactory` with fields: `appScope='shared'`, `locale='fr-FR'`, `domain='regulatory'`, `translationKey='…'`, `translationValue='…'`. Keys to seed: authority_notification labels, microchip_registry_lookup labels, stray_custody deadline labels.
- **Contract tests (Phase 2)**: `abstract class JurisdictionPolicyContractTest extends TestCase` with abstract `createPolicy(): RegulatoryPolicyInterface`. `FrancePolicyTest extends JurisdictionPolicyContractTest`. Same for `WorkingDayCalculatorContractTest`. Ensures compliance of any future jurisdiction implementation.
- **Phase 2 DI wiring**: atomic commit — service container aliases and namespace move in the same commit. No intermediate broken state.
- **No backwards-compat shims**: rename, migrate, done. No dual-write, no fallback aliases.
- **Suggested task order within Phase 0** (non-blocking, solo dev): 0.4 → 0.3 → 0.5 → 0.2 → 0.1.

## Implementation Plan

### Tasks

---

#### Phase 0 — Hygiene (Week 1)

- [ ] **T1: Rename PlanningBlockType enum cases and DB values (Phase 0.4)**
  - File: `src/Context/Scheduling/Domain/ValueObject/PlanningBlockType.php`
  - Action: Rename 6 cases: `CHIRURGIE→SURGERY('surgery')`, `BILAN→HEALTH_CHECK('health_check')`, `URGENCE→EMERGENCY('emergency')`, `GARDE→ON_CALL('on_call')`, `CONGE→LEAVE('leave')`, `FORMATION→TRAINING('training')`. Update `acceptsAppointments()` match arms from `self::CONGE, self::FORMATION, self::ADMIN` to `self::LEAVE, self::TRAINING, self::ADMIN`. Same for `hasCapacityLimit()`.
  - File: `fixtures/Context/Scheduling/Story/SchedulingPlanningBlockStory.php`
  - Action: Replace all old enum case references with new ones (5 planning block fixtures use various types).
  - Files: `tests/Unit/Context/Scheduling/Domain/PlanningBlockTest.php`, `tests/Unit/Context/Scheduling/Domain/ValueObject/PlanningBlockTypeCapabilityCompletenessTest.php`, `tests/Unit/Context/Scheduling/Application/Command/DeletePlanningBlock/DeletePlanningBlockHandlerTest.php`, `tests/Unit/Context/Scheduling/Application/Command/ScheduleAppointment/ScheduleAppointmentHandlerTest.php`, `tests/Unit/Context/Scheduling/Application/Command/UpdatePlanningBlock/UpdatePlanningBlockHandlerTest.php`
  - Action: Update all enum case references in tests to new English names.
  - File: Migration (generate via `make doctrine:migrations:diff`)
  - Action: If `PlanningBlockEntity.type` is native ENUM column, migration ALTERs the ENUM list. If VARCHAR, no schema change needed — verify by inspecting entity column definition.
  - Action: Search `templates/` for hardcoded strings 'chirurgie', 'bilan', 'urgence', 'garde', 'conge', 'formation' — update any Twig label maps to new DB values.
  - Files: `assets/js/pages/scheduling/planning.js`, `assets/js/pages/scheduling/agenda.js`, `assets/controllers/waiting_room_controller.js`
  - Action: Update all hardcoded French DB string values in JS (`{ id: 'chirurgie' }`, style map keys, `{ type: 'chirurgie' }`, etc.) to new English values. In `waiting_room_controller.js`, update the regex `/chirurgie|opérat|surgery/` — replace `chirurgie` with `surgery` in the alternation (already has `surgery` — confirm the two don't conflict).
  - File: `assets/styles/pages/scheduling/waiting-room.css` (or equivalent)
  - Action: Check for CSS selectors or class names derived from French enum values (e.g. `.wr-urgence`) — update if present.
  - Action: Run `make assets` after any JS/CSS change to rebuild frontend assets.

- [ ] **T2: Rewrite 4 BC READMEs (Phase 0.3)**
  - File: `src/Context/Clinic/README.md`
  - Action: Rewrite in English. Include: BC purpose (multi-tenant root), aggregates (Clinic, ClinicGroup, ClinicMembership, StaffProfile), Staff sub-domain description (professional titles, agenda colors, registration numbers), cross-BC dependencies (produces Membership events consumed by AccessControl; reads IdentityAccess via UserExistenceChecker port).
  - File: `src/Context/Scheduling/README.md`
  - Action: Rewrite in English. Remove all WaitingRoomEntry references. Describe: PlanningBlock aggregate (with correct English enum values), Appointment aggregate, cross-BC ports (MembershipEligibilityChecker, ClinicTimezoneResolver from Clinic), RecurrenceRule simplification note.
  - File: `src/Context/Consultation/README.md`
  - Action: Full rewrite — current doc is very outdated (references WaitingRoomEntry, AnimalId, OwnerId, files that no longer exist). Describe: SOAP note structure, Vitals, PerformedActRecord, cross-BC ports (SchedulingAppointmentContextProvider, AdmissionContextProvider, PractitionerEligibilityChecker).
  - File: `src/System/IdentityAccess/README.md`
  - Action: Expand from minimalist state. Add: authentication flow description, 3 concrete UserEntity sub-types, `AuthenticateUser` query, security gaps noted (2FA, lockout — out of scope but documented as known debt).

- [ ] **T3: Create local ClinicId VOs in Animal and Client BCs (Phase 0.5)**
  - Files to create: `src/Context/Animal/Domain/ValueObject/ClinicId.php`, `src/Context/Client/Domain/ValueObject/ClinicId.php`
  - Action: Copy exact shape of `src/Context/Patient/Domain/ValueObject/ClinicId.php` — `final class ClinicId extends AbstractUuidId` with single `public static function fromString(string $value): self` factory. Change namespace to `App\Context\Animal\Domain\ValueObject` / `App\Context\Client\Domain\ValueObject`.
  - Files to modify (Animal BC — 17 files): `src/Context/Animal/Domain/Animal.php`, `src/Context/Animal/Application/Command/ArchiveAnimal/ArchiveAnimalHandler.php`, `src/Context/Animal/Application/Command/CreateAnimal/CreateAnimalHandler.php`, `src/Context/Animal/Application/Command/ReplaceAnimalOwners/ReplaceAnimalOwnersHandler.php`, `src/Context/Animal/Application/Command/UpdateAnimalIdentity/UpdateAnimalIdentityHandler.php`, `src/Context/Animal/Application/Command/UpdateAnimalLifeCycle/UpdateAnimalLifeCycleHandler.php`, `src/Context/Animal/Application/Command/UpdateAnimalTransfer/UpdateAnimalTransferHandler.php`, `src/Context/Animal/Application/Query/CountAnimals/CountAnimalsHandler.php`, `src/Context/Animal/Application/Query/GetAnimalById/GetAnimalByIdHandler.php`, `src/Context/Animal/Application/Query/ListAnimalSummariesPerClientIds/ListAnimalSummariesPerClientIdsHandler.php`, `src/Context/Animal/Application/Query/SearchAnimals/SearchAnimalsHandler.php`, `src/Context/Animal/Domain/Repository/AnimalRepositoryInterface.php`, `src/Context/Animal/Application/Port/AnimalReadRepositoryInterface.php`, `src/Context/Animal/Infrastructure/Messaging/Consumer/ClientArchivedIntegrationEventConsumer.php`, `src/Context/Animal/Infrastructure/Persistence/Doctrine/DoctrineAnimalRepository.php`, `src/Context/Animal/Infrastructure/Persistence/Doctrine/DoctrineAnimalReadRepository.php`, `src/Context/Animal/Infrastructure/Persistence/Doctrine/AnimalMapper.php`
  - Action: In all 17 Animal files, replace `use App\Context\Clinic\Domain\ValueObject\ClinicId;` → `use App\Context\Animal\Domain\ValueObject\ClinicId;`.
  - Files to modify (Client BC — 16 files): same pattern for `src/Context/Client/**` files importing from Clinic.
  - Action: Replace `use App\Context\Clinic\Domain\ValueObject\ClinicId;` → `use App\Context\Client\Domain\ValueObject\ClinicId;` in all 16 Client files.
  - Notes: No DB migration needed — `clinicId` column already mapped as `UuidType::NAME` (plain UUID string) in both Doctrine entities. No schema change. After changes, run `make ci` — PHPStan must pass at level max.

- [ ] **T4: Add `#[Version]` optimistic locking to Clinic, Client, Consultation, Scheduling, IdentityAccess entities (Phase 0.2)**
  - Files to modify: `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicEntity.php`, `src/Context/Client/Infrastructure/Persistence/Doctrine/Entity/ClientEntity.php`, `src/Context/Consultation/Infrastructure/Persistence/Doctrine/Entity/ConsultationEntity.php`, `src/System/IdentityAccess/Infrastructure/Persistence/Doctrine/Entity/UserEntity.php`, `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Entity/PlanningBlockEntity.php`, `src/Context/Scheduling/Infrastructure/Persistence/Doctrine/Entity/AppointmentEntity.php`
  - Action: For concrete entities (ClinicEntity, ClientEntity, ConsultationEntity, PlanningBlockEntity, AppointmentEntity): add `#[ORM\Version] #[ORM\Column] private int $version = 1;` and a `public function getVersion(): int { return $this->version; }` getter.
  - Action: For `UserEntity` (abstract base with `#[ORM\InheritanceType('SINGLE_TABLE')]`): declare the field as `protected` not `private` — `#[ORM\Version] #[ORM\Column] protected int $version = 1;` — so sub-classes can access it without PHPStan "access private property from child class" errors. Add `public function getVersion(): int { return $this->version; }` on the abstract base.
  - Action: Check each BC's domain aggregate `reconstitute()` factory — Regulatory aggregates expose `version(): int`; replicate the same pattern here (add `int $version` param to `reconstitute()` and update the corresponding Mapper class). If the BC has no `reconstitute()` with version, entity-only is acceptable.
  - Action: Run `make doctrine:migrations:diff` to generate migration, then `make migrate-db`.

- [ ] **T5: Wire ICADLookupAdapter to dispatch OpenICADLookup command (Phase 0.1)**
  - File: `src/Context/Admission/Infrastructure/Adapter/Regulatory/ICADLookupAdapter.php`
  - Action: Add constructor parameter `private readonly CommandBusInterface $commandBus` (use `App\Shared\Application\Bus\CommandBusInterface` — the project's own abstraction, not raw `MessageBusInterface`). In `initiateChipLookup(string $chipNumber, string $clinicId): void`, add: `$this->commandBus->dispatch(new OpenICADLookup(chipNumber: $chipNumber, clinicId: $clinicId));`. Add `use` for `CommandBusInterface` and `OpenICADLookup`.
  - File to create: `tests/Unit/Context/Admission/Infrastructure/Adapter/ICADLookupAdapterTest.php`
  - Action: Unit test with mocked `CommandBusInterface`. Assert `dispatch()` called exactly once with `OpenICADLookup` instance matching `chipNumber` and `clinicId`.

---

#### Phase 1 — i18n Foundations (Weeks 2-3)

- [ ] **T6: Create CountryCode and CurrencyCode Shared VOs (Phase 1.1)**
  - Files to create: `src/Shared/Domain/ValueObject/CountryCode.php`, `src/Shared/Domain/ValueObject/CurrencyCode.php`
  - Action `CountryCode`: `final class CountryCode` with `private function __construct(private readonly string $value)`. Factory `public static function fromString(string $value): self` — validate exactly 2 uppercase ASCII letters (e.g. `preg_match('/^[A-Z]{2}$/', $value)`) or throw `\InvalidArgumentException`. `public function toString(): string`, `public function equals(self $other): bool`.
  - Action `CurrencyCode`: same pattern, validate exactly 3 uppercase ASCII letters (`/^[A-Z]{3}$/`).
  - Files to create: `tests/Unit/Shared/Domain/ValueObject/CountryCodeTest.php`, `tests/Unit/Shared/Domain/ValueObject/CurrencyCodeTest.php`
  - Action: Test valid codes (`FR`, `DE`, `EUR`, `USD`), invalid codes throw exception, `equals()` with same and different values.

- [ ] **T7: Enrich Clinic aggregate, entity, read model, form, and factory (Phase 1.2)**
  - File: `src/Context/Clinic/Domain/Clinic.php`
  - Action: Add properties `private CountryCode $countryCode`, `private ?string $jurisdictionCode`, `private CurrencyCode $currencyCode`. Add to `create()` static factory (required params). Add to `reconstitute()` static factory. Add accessors `countryCode(): CountryCode`, `jurisdictionCode(): ?string`, `currencyCode(): CurrencyCode`.
  - File: `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicEntity.php`
  - Action: Add three columns: `#[ORM\Column(name: 'country_code', type: 'string', length: 2)] private string $countryCode;`, `#[ORM\Column(name: 'jurisdiction_code', type: 'string', length: 16, nullable: true)] private ?string $jurisdictionCode = null;`, `#[ORM\Column(name: 'currency_code', type: 'string', length: 3)] private string $currencyCode;`. Add getters and setters.
  - File: Clinic mapper (find in `src/Context/Clinic/Infrastructure/Persistence/Doctrine/`)
  - Action: Map the three new fields between entity and domain aggregate in both directions.
  - File: `src/Context/Clinic/Application/Query/Clinic/GetClinic/ClinicDto.php`
  - Action: Add `public readonly string $countryCode`, `public readonly ?string $jurisdictionCode`, `public readonly string $currencyCode` to DTO constructor/properties.
  - File: `src/Presentation/Backoffice/Controller/ClinicController.php`
  - Action: In the create and update form handlers, read `$request->request->get('country_code')`, `$request->request->get('jurisdiction_code')`, `$request->request->get('currency_code')` and pass to the appropriate command. Add these fields to the Twig create/edit templates.
  - File: `fixtures/Context/Clinic/Factory/ClinicEntityFactory.php`
  - Action: Add to `defaults()`: `'countryCode' => 'FR'`, `'jurisdictionCode' => null`, `'currencyCode' => 'EUR'`. Update all Story fixtures calling `ClinicEntityFactory` to include these defaults (or rely on factory defaults).
  - File: Migration
  - Action: `ADD COLUMN country_code VARCHAR(2) NOT NULL DEFAULT 'FR'`, `ADD COLUMN jurisdiction_code VARCHAR(16) NULL`, `ADD COLUMN currency_code VARCHAR(3) NOT NULL DEFAULT 'EUR'`. Remove DEFAULTs after migration (fixtures set explicit values).
  - Notes: `jurisdictionCode` format is ISO 3166-2 (e.g. `'FR-67'`, `'DE-BY'`). No validation in domain — accept any string. `Clinic` does NOT raise a domain event for these fields (configuration data, not business state change).
  - Notes: `countryCode`, `jurisdictionCode`, `currencyCode` are **write-once at creation** (alpha decision) — no `ChangeClinicCountryCode` command exists and none should be created in this spec. If update is needed later, a dedicated command can be added then.
  - Notes: `ClinicController` has multiple public methods (pre-existing violation of `CLAUDE.md §5`). T7 enriches the existing controller without fixing the split — refactoring to single-`__invoke` controllers is tracked separately as a follow-up chore. Do NOT add new methods; only extend the existing `create()` and `update()` handlers.

- [ ] **T8: Externalise defaultLocale and locale mappings to Symfony config parameters (Phase 1.3)**
  - File: `src/System/Translation/Infrastructure/Resolver/DefaultLocaleResolver.php`
  - Action: Change constructor signature to receive three explicit `string` parameters: `string $defaultLocale`, `string $backofficeLocale`, and `array $shortLocaleMap` (e.g. `['fr' => 'fr-FR', 'en' => 'en-GB']`). Remove the `= 'fr-FR'` hardcoded default. Replace the hardcoded `return Locale::fromString('fr-FR')` in the BACKOFFICE branch with `return Locale::fromString($this->backofficeLocale)`. Replace the hardcoded `match` in `normalizeCandidate()` (currently `'fr' => 'fr-FR', 'en' => 'en-GB'`) with a lookup into `$this->shortLocaleMap`, falling back to the candidate as-is. Remove ALL hardcoded locale string literals from the class.
  - File: `config/services.yaml` (or `config/packages/translation.yaml`)
  - Action: Define parameters: `app.default_locale: 'en'`, `app.backoffice_locale: 'fr-FR'`, `app.short_locale_map: { fr: 'fr-FR', en: 'en-GB' }`. Wire them as service arguments on `DefaultLocaleResolver`.
  - Action: Update unit tests for `DefaultLocaleResolver`: inject parameters explicitly; test that `normalizeCandidate('en')` returns `'en-GB'` from config (not hardcoded), and that changing the map changes the output.

- [ ] **T9: Neutralise Admission enum values (Phase 1.4)**
  - File: `src/Context/Admission/Domain/ValueObject/IntakeChannel.php`
  - Action: **CAUTION — collision risk**: `EmergencyByAuthority = 'emergency_by_authority'` **already exists** as a distinct case. `EmergencyByMunicipality = 'emergency_by_municipality'` is a separate concept (town-hall pound intake). Do NOT rename it to `EmergencyByAuthority` — that would silently merge two semantically different intake channels. Instead: **DELETE `EmergencyByMunicipality`** and update all usages to point to `EmergencyByAuthority` (the existing case, which is the correct neutral successor). Before deleting, grep all of `src/`, `templates/`, `tests/`, `fixtures/` for `EmergencyByMunicipality` and `emergency_by_municipality` — update every reference to `EmergencyByAuthority` / `'emergency_by_authority'`.
  - File: `src/Context/Admission/Domain/ValueObject/ClosureReason.php`
  - Action: Rename `HandedToMunicipality = 'handed_to_municipality'` → `HandedToAuthority = 'handed_to_authority'`.
  - File: `src/Context/Admission/Domain/ValueObject/PresenterRole.php`
  - Action: Run `grep -rn "Municipality\|municipality" src/ templates/ tests/` first. After confirming only fixture/form references remain, delete `Municipality = 'municipality'` case. Update all references to use `Authority = 'authority'` instead.
  - File: `src/Context/Regulatory/Application/EventSubscriber/AdmissionClosedHandler.php`
  - Action: This handler imports `ClosureReason` from Admission BC and references `ClosureReason::HandedToMunicipality`. Update the reference to `ClosureReason::HandedToAuthority` after the rename above. Failure to update this file will cause a fatal error at runtime and break `make ci`.
  - File: `templates/clinic/admission/queue.html.twig`
  - Action: Remove `'emergency_by_municipality': 'Urgence mairie'` from the channelLabels map (the case is deleted, not renamed). Ensure `'emergency_by_authority'` entry already present covers authority-based emergency display.
  - File: `templates/clinic/admission/emergency_form.html.twig`
  - Action: Remove `municipality` option from the presenter role dropdown. Update any value/label referencing `'municipality'`.
  - File: Admission fixtures (find Admission Story/Factory files)
  - Action: Update any fixture data using `'emergency_by_municipality'` → `'emergency_by_authority'`, `'handed_to_municipality'` → `'handed_to_authority'`, `'municipality'` presenter role → `'authority'`.
  - File: `tests/Unit/Context/Regulatory/StrayCustodyTest.php`
  - Action: Update references to `HandedToMunicipality` closure reason and `StrayCustodyStatus::ClosedHandedToMunicipality` (the latter renamed in T10 — do both if doing T9 and T10 together).
  - File: Migration
  - Action: If Admission enum columns are native ENUM type, ALTER the ENUM list to remove `'emergency_by_municipality'` and `'handed_to_municipality'` (and `'municipality'` from presenter_role). Otherwise VARCHAR — no schema change. Alpha: run `make load-fixtures` after.

---

#### Phase 2 — Regulatory Decoupling (Weeks 4-7)

- [ ] **T10: Rename Regulatory aggregates, events, VOs, infrastructure, DB tables (Phase 2.1)**
  - This is one large atomic refactor. All renames in a single commit. Run `make load-fixtures` after migration.
  - **MairieNotification → AuthorityNotification** (rename all files, classes, namespaces):
    - `src/Context/Regulatory/Domain/MairieNotification.php` → `AuthorityNotification.php`
    - `src/Context/Regulatory/Domain/ValueObject/MairieNotificationId.php` → `AuthorityNotificationId.php`
    - `src/Context/Regulatory/Domain/ValueObject/MairieNotificationStatus.php` → `AuthorityNotificationStatus.php`
    - `src/Context/Regulatory/Domain/Repository/MairieNotificationRepositoryInterface.php` → `AuthorityNotificationRepositoryInterface.php`
    - `src/Context/Regulatory/Domain/Event/MairieNotificationScheduled.php` → `AuthorityNotificationScheduled.php`
    - `src/Context/Regulatory/Domain/Event/MairieNotificationSent.php` → `AuthorityNotificationSent.php`
    - `src/Context/Regulatory/Domain/Event/MairieNotificationCancelled.php` → `AuthorityNotificationCancelled.php`
    - `src/Context/Regulatory/Domain/Exception/MairieNotificationNotFoundException.php` → `AuthorityNotificationNotFoundException.php`
    - `src/Context/Regulatory/Infrastructure/Persistence/Doctrine/Entity/MairieNotificationEntity.php` → `AuthorityNotificationEntity.php` (update `#[ORM\Table(name: 'authority_notifications')]`)
    - `src/Context/Regulatory/Infrastructure/Persistence/Doctrine/DoctrineMairieNotificationRepository.php` → `DoctrineAuthorityNotificationRepository.php`
    - `src/Context/Regulatory/Infrastructure/Persistence/Doctrine/MairieNotificationMapper.php` → `AuthorityNotificationMapper.php`
    - `fixtures/Context/Regulatory/Factory/MairieNotificationEntityFactory.php` → `AuthorityNotificationEntityFactory.php`
    - `src/Context/Regulatory/Application/EventSubscriber/AdmissionOpenedWithUnidentifiedPatientHandler.php` — update class name + call to `AuthorityNotification::schedule()`
    - `src/Context/Regulatory/Application/Port/RegulatoryTasksReadRepositoryInterface.php` — rename `findPendingMairieNotifications()` → `findPendingAuthorityNotifications()`
    - `src/Context/Regulatory/Infrastructure/Persistence/Doctrine/DoctrineRegulatoryTasksReadRepository.php` — rename the method
  - **ICADLookup → MicrochipRegistryLookup** (rename all files, classes, namespaces):
    - `src/Context/Regulatory/Domain/ICADLookup.php` → `MicrochipRegistryLookup.php`
    - `src/Context/Regulatory/Domain/ValueObject/ICADLookupId.php` → `MicrochipRegistryLookupId.php`
    - `src/Context/Regulatory/Domain/ValueObject/ICADLookupStatus.php` → `MicrochipRegistryLookupStatus.php`
    - `src/Context/Regulatory/Domain/Repository/ICADLookupRepositoryInterface.php` → `MicrochipRegistryLookupRepositoryInterface.php`
    - `src/Context/Regulatory/Domain/Event/ICADLookupInitiated.php` → `MicrochipRegistryLookupInitiated.php`
    - `src/Context/Regulatory/Domain/Event/ICADLookupFound.php` → `MicrochipRegistryLookupFound.php`
    - `src/Context/Regulatory/Domain/Event/ICADLookupNotFound.php` → `MicrochipRegistryLookupNotFound.php`
    - `src/Context/Regulatory/Domain/Event/ICADLookupFailed.php` → `MicrochipRegistryLookupFailed.php`
    - `src/Context/Regulatory/Domain/Exception/ICADLookupNotFoundException.php` → `MicrochipRegistryLookupNotFoundException.php`
    - `src/Context/Regulatory/Infrastructure/Persistence/Doctrine/Entity/ICADLookupEntity.php` → `MicrochipRegistryLookupEntity.php` (update `#[ORM\Table(name: 'microchip_registry_lookups')]`)
    - `src/Context/Regulatory/Infrastructure/Persistence/Doctrine/DoctrineICADLookupRepository.php` → `DoctrineMicrochipRegistryLookupRepository.php`
    - `src/Context/Regulatory/Infrastructure/Persistence/Doctrine/ICADLookupMapper.php` → `MicrochipRegistryLookupMapper.php`
    - Create `fixtures/Context/Regulatory/Factory/MicrochipRegistryLookupEntityFactory.php` (no factory existed before — create new one following `MairieNotificationEntityFactory` pattern)
    - `src/Context/Regulatory/Application/Command/OpenICADLookup/OpenICADLookup.php` → `OpenMicrochipRegistryLookup.php`
    - `src/Context/Regulatory/Application/Command/OpenICADLookup/OpenICADLookupHandler.php` → `OpenMicrochipRegistryLookupHandler.php` (update repository injection to `MicrochipRegistryLookupRepositoryInterface`)
    - `src/Context/Admission/Application/Port/ICADLookupPort.php` → `MicrochipRegistryLookupPort.php`
    - `src/Context/Admission/Infrastructure/Adapter/Regulatory/ICADLookupAdapter.php` → `MicrochipRegistryLookupAdapter.php` (update command dispatch to use `OpenMicrochipRegistryLookup`)
  - **StrayCustody renames** (in-place, no file rename):
    - `src/Context/Regulatory/Domain/StrayCustody.php`: rename method `closeHandedToMunicipality()` → `closeHandedToAuthority()`
    - `src/Context/Regulatory/Domain/ValueObject/StrayCustodyStatus.php`: rename case `ClosedHandedToMunicipality` → `ClosedHandedToAuthority`
    - `src/Context/Regulatory/Domain/Event/StrayCustodyClosedHandedToMunicipality.php` → `StrayCustodyClosedHandedToAuthority.php`
    - `src/Context/Regulatory/Application/EventSubscriber/AdmissionClosedHandler.php`: update call from `$strayCustody->closeHandedToMunicipality()` → `closeHandedToAuthority()` and any `StrayCustodyStatus::ClosedHandedToMunicipality` reference → `ClosedHandedToAuthority`.
  - **DB migration**: DROP TABLE `mairie_notifications`, CREATE TABLE `authority_notifications` (same columns). DROP TABLE `icad_lookups`, CREATE TABLE `microchip_registry_lookups` (same columns). Run `make load-fixtures` after.
  - **All Regulatory tests**: update all class/method references throughout `tests/Unit/Context/Regulatory/`.

- [ ] **T11: Extract RegulatoryPolicyInterface and WorkingDayCalculatorInterface (Phase 2.2)**
  - Files to create:
    - `src/Context/Regulatory/Domain/Policy/RegulatoryPolicyInterface.php`
    - `src/Context/Regulatory/Domain/Service/WorkingDayCalculatorInterface.php`
    - `tests/Unit/Context/Regulatory/Policy/JurisdictionPolicyContractTest.php` (abstract PHPUnit TestCase)
    - `tests/Unit/Context/Regulatory/Service/WorkingDayCalculatorContractTest.php` (abstract PHPUnit TestCase)
  - Action — `RegulatoryPolicyInterface` (clean surface — no cross-interface parameter coupling):
    ```
    interface RegulatoryPolicyInterface {
        public function getAuthorityNotificationDeadline(\DateTimeImmutable $admissionOpenedAt): \DateTimeImmutable;
        public function getStrayCustodyDeadline(\DateTimeImmutable $admissionOpenedAt): \DateTimeImmutable;
    }
    ```
  - Action — `WorkingDayCalculatorInterface`:
    ```
    interface WorkingDayCalculatorInterface {
        public function addWorkingDays(\DateTimeImmutable $from, int $days): \DateTimeImmutable;
        public function isWorkingDay(\DateTimeImmutable $date): bool;
    }
    ```
  - Notes on interface design: `getStrayCustodyDeadline()` takes NO `WorkingDayCalculatorInterface` parameter. Instead, `FranceRegulatoryPolicy` injects `WorkingDayCalculatorInterface` in its **constructor** and uses it internally. This keeps the interface clean and callers (handlers) need only one service per jurisdiction — not two. The `WorkingDayCalculatorInterface` is an implementation detail of the France policy, not a concern of the interface contract.
  - Action — `JurisdictionPolicyContractTest` (abstract): `abstract protected function createPolicy(): RegulatoryPolicyInterface`. Tests: `authority_notification_deadline_is_strictly_after_admission_date()`, `stray_custody_deadline_is_strictly_after_admission_date()`, `deadlines_differ_for_two_consecutive_days()`. Note: the abstract factory must return a fully constructed policy (with calculator injected) — `FrancePolicyTest::createPolicy()` returns `new FranceRegulatoryPolicy(new FrenchWorkingDayCalculator())`.
  - Action — `WorkingDayCalculatorContractTest` (abstract): `abstract protected function createCalculator(): WorkingDayCalculatorInterface`. Tests: `adding_zero_days_returns_next_working_day_or_same()`, `saturdays_are_not_working_days()`, `sundays_are_not_working_days()`, `adds_correct_number_of_working_days()`.
  - File: `src/Context/Regulatory/Domain/AuthorityNotification.php`
  - Action: Change `schedule()` to accept `RegulatoryPolicyInterface $policy` parameter. Replace `$admissionOpenedAt->modify('+48 hours')` with `$policy->getAuthorityNotificationDeadline($admissionOpenedAt)`.
  - File: `src/Context/Regulatory/Domain/StrayCustody.php`
  - Action: Change `begin()` parameter from concrete `FrenchWorkingDayCalculator $calculator` to `RegulatoryPolicyInterface $policy`. Replace `$calculator->addWorkingDays($admissionOpenedAt, 8)` with `$policy->getStrayCustodyDeadline($admissionOpenedAt)`. Remove the calculator parameter entirely from the aggregate method signature.
  - Files: handlers that call `AuthorityNotification::schedule()` and `StrayCustody::begin()` — inject `RegulatoryPolicyInterface` into handlers (resolved via jurisdiction in T12). Remove any direct `FrenchWorkingDayCalculator` injection from handlers.

- [ ] **T12: Create Jurisdiction/France/ sub-namespace and JurisdictionResolverInterface (Phase 2.3)**
  - Files to create:
    - `src/Context/Regulatory/Domain/JurisdictionResolverInterface.php`
    - `src/Context/Regulatory/Infrastructure/Resolver/ClinicJurisdictionResolver.php`
    - `src/Context/Regulatory/Jurisdiction/France/FranceRegulatoryPolicy.php`
    - `src/Context/Regulatory/Jurisdiction/France/FrenchWorkingDayCalculator.php` (moved from `Domain/Service/`, same content, updated namespace)
    - `tests/Unit/Context/Regulatory/Jurisdiction/France/FrancePolicyTest.php` (extends `JurisdictionPolicyContractTest`)
    - `tests/Unit/Context/Regulatory/Jurisdiction/France/FrenchWorkingDayCalculatorTest.php` (extends `WorkingDayCalculatorContractTest`)
  - Action — `JurisdictionResolverInterface`:
    ```
    interface JurisdictionResolverInterface {
        public function resolveForClinic(string $clinicId): string; // Returns ISO 3166-1 alpha-2 e.g. 'FR'
    }
    ```
  - Action — `ClinicJurisdictionResolver`: inject `\Doctrine\DBAL\Connection $connection`. In `resolveForClinic(string $clinicId): string`: `SELECT country_code FROM clinics WHERE id = :clinicId`. Throws `\RuntimeException` if clinic not found. Requires Phase 1.2 (`country_code` column) to exist.
  - Action — `FranceRegulatoryPolicy` (implements `RegulatoryPolicyInterface`): inject `WorkingDayCalculatorInterface $calculator` in constructor. `getAuthorityNotificationDeadline()` returns `$admissionOpenedAt->modify('+48 hours')`. `getStrayCustodyDeadline()` returns `$this->calculator->addWorkingDays($admissionOpenedAt, 8)`.
  - Action — `FrenchWorkingDayCalculator` moved to `Jurisdiction/France/` namespace; implements `WorkingDayCalculatorInterface`; same implementation, new namespace. Delete old file at `Domain/Service/FrenchWorkingDayCalculator.php`.
  - File: `config/services.yaml`
  - Action — **Exact DI wiring (Symfony 7, PHPStan-compatible)**:
    1. Tag `FranceRegulatoryPolicy` with attribute `#[AutoconfigureTag('regulatory_policy', ['jurisdiction' => 'FR'])]` (or equivalent YAML tag).
    2. In each Regulatory handler that needs a policy, inject via `#[AutowireIterator('regulatory_policy', indexAttribute: 'jurisdiction')] iterable $policies`. This gives a `ServiceLocator`-like iterable keyed by jurisdiction string, accessible as `iterator_to_array($policies)['FR']`. For PHPStan: annotate with `@param iterable<string, RegulatoryPolicyInterface> $policies`.
    3. Alternatively, use `#[AutowireLocator(['FR' => FranceRegulatoryPolicy::class])] ContainerInterface $policies` for strict keyed access without iteration.
    4. Wire `JurisdictionResolverInterface` → `ClinicJurisdictionResolver` in services.yaml.
    5. Wire `WorkingDayCalculatorInterface` → `FrenchWorkingDayCalculator` (aliased, or injected explicitly into `FranceRegulatoryPolicy`).
  - Notes: Atomically commit namespace move + service container wiring in a single commit. No intermediate state where old namespace is gone but DI still references it. Verify `make ci` passes before merging.

- [ ] **T13: Refactor Animal Identification.sireNumber and RegistryType enum (Phase 2.4)**
  - File: `src/Context/Animal/Domain/ValueObject/RegistryType.php`
  - Action: Add `FOREIGN_REGISTRY = 'foreign_registry'` case. Keep `NONE`, `LOF`, `LOOF`, `OTHER` as-is. Add unit test covering new case.
  - File: `src/Context/Animal/Domain/ValueObject/Identification.php`
  - Action: Rename `sireNumber: ?string` property → `registryReference: ?string` throughout (constructor param, property declaration, getter, `createEmpty()`, any `with*()` methods). Update `ensureConsistency()` if it references `sireNumber`. Remove any SIRE-specific format validation from this VO (if any exists — move to France namespace). The invariant check remains: `RegistryType::NONE` must have null `registryNumber` (and null `registryReference`).
  - File: `src/Context/Animal/Infrastructure/Persistence/Doctrine/Entity/AnimalEntity.php`
  - Action: Rename column from `sire_number` to `registry_reference` in `#[ORM\Column]` attribute.
  - File: `src/Context/Regulatory/Jurisdiction/France/SireNumberValidator.php`
  - Action: Create France-specific validator class. Contains SIRE format check (e.g. `'/^\d{15}$/'` or applicable regex). Used by France-specific application services, not core Animal domain.
  - File: `fixtures/Context/Animal/Factory/AnimalEntityFactory.php`
  - Action: Update `defaults()`: rename `'sireNumber' => null` → `'registryReference' => null`.
  - File: Migration
  - Action: `RENAME COLUMN sire_number TO registry_reference` (or DROP/ADD if DB doesn't support RENAME COLUMN). Run `make load-fixtures` after.
  - Files: All unit tests for `Identification`, `Animal` — update `sireNumber` references to `registryReference`.

- [ ] **T14: Seed Translation BC with Regulatory jurisdiction-neutral labels (Phase 2.5)**
  - File to create: `fixtures/System/Translation/Story/RegulatoryTranslationStory.php`
  - Action: Create Foundry Story class extending `Story`. Use `TranslationEntryEntityFactory` to create entries with `appScope='shared'`, `locale='fr-FR'`, `domain='regulatory'`. Seed the following keys with French labels:
    - `authority_notification.scheduled` → `'Notification autorité planifiée'`
    - `authority_notification.sent` → `'Notification autorité envoyée'`
    - `authority_notification.cancelled` → `'Notification autorité annulée'`
    - `authority_notification.deadline_label` → `'Délai légal de notification (48h)'`
    - `microchip_registry_lookup.pending` → `'Recherche registre puce en cours'`
    - `microchip_registry_lookup.found` → `'Animal trouvé dans le registre'`
    - `microchip_registry_lookup.not_found` → `'Animal non trouvé dans le registre'`
    - `microchip_registry_lookup.failed` → `'Échec de la recherche registre'`
    - `stray_custody.active` → `'Garde errant en cours'`
    - `stray_custody.deadline_label` → `'Délai légal de garde (8 jours ouvrés)'`
    - `stray_custody.closed_handed_to_authority` → `'Remis à l\'autorité compétente'`
  - Action: Wire the Story into the fixture loader. The project uses Foundry Story classes — inspect the main fixture orchestration file (likely `fixtures/AppFixtures.php` or a `*Dataset.php` class) to find where existing Stories are called. Add a `RegulatoryTranslationStory::load()` call at the appropriate point in the chain (after Regulatory fixtures are loaded). Verify by running `make load-fixtures` and confirming no errors and the 11 rows appear in `translation_entries`.

---

### Acceptance Criteria

- [ ] **AC1 (T1 — PlanningBlockType rename):** Given the `PlanningBlockType` enum, when `PlanningBlockType::SURGERY` is accessed, then `->value === 'surgery'` and `acceptsAppointments()` returns `true`. Given `PlanningBlockType::LEAVE`, then `acceptsAppointments()` returns `false` and `hasCapacityLimit()` returns `false`. Given `make load-fixtures`, then no row in `planning_blocks` has `type` IN `('chirurgie', 'bilan', 'urgence', 'garde', 'conge', 'formation')`. Given `make ci`, then green.

- [ ] **AC2 (T2 — READMEs):** Given the four README files, when read, then: Clinic README mentions StaffProfile; Scheduling README has no occurrence of 'WaitingRoomEntry'; Consultation README has no occurrence of 'AnimalId', 'OwnerId', or 'WaitingRoomEntry'; IdentityAccess README describes `AuthenticateUser` query and 3 UserEntity sub-types. All READMEs are in English.

- [ ] **AC3 (T3 — ClinicId local VOs):** Given `grep -r "App\\Context\\Clinic\\Domain\\ValueObject\\ClinicId" src/Context/Animal/ src/Context/Client/`, then zero results. Given `Animal\Domain\ValueObject\ClinicId::fromString('550e8400-e29b-41d4-a716-446655440000')`, then a valid `ClinicId` instance is returned. Given `make ci`, then PHPStan level max passes with no errors in Animal or Client BC.

- [ ] **AC4 (T4 — @Version):** Given the Doctrine schema diff, when `make doctrine:migrations:diff` is run after changes, then no further `version` column additions are pending. Given `ClinicEntity`, `ClientEntity`, `UserEntity`, `PlanningBlockEntity`, `AppointmentEntity`, when inspected, then each has a `version INT NOT NULL DEFAULT 1` column in the DB schema. Given `make ci`, then green.

- [ ] **AC5 (T5 — ICADLookupAdapter):** Given `ICADLookupAdapter::initiateChipLookup('123456789012345', '550e8400-e29b-41d4-a716-446655440000')` is called with a mocked `CommandBusInterface` (`App\Shared\Application\Bus\CommandBusInterface`), then `dispatch()` is called exactly once with an `OpenICADLookup` instance where `chipNumber === '123456789012345'` and `clinicId === '550e8400-e29b-41d4-a716-446655440000'`. Given `grep "MessageBusInterface" src/Context/Admission/Infrastructure/Adapter/Regulatory/ICADLookupAdapter.php`, then zero results. Given `make ci`, then green.

- [ ] **AC6 (T6 — CountryCode/CurrencyCode VOs):** Given `CountryCode::fromString('FR')`, when called, then no exception. Given `CountryCode::fromString('france')` or `CountryCode::fromString('FRA')`, then `\InvalidArgumentException`. Given `CountryCode::fromString('FR')->equals(CountryCode::fromString('FR'))`, then `true`. Same pattern for `CurrencyCode`. Given `make ci`, then 100% coverage on these VOs.

- [ ] **AC7 (T7 — Clinic enrichment):** Given `Clinic::create()` with `countryCode: CountryCode::fromString('DE')` and `currencyCode: CurrencyCode::fromString('EUR')`, when persisted and reloaded, then `$clinic->countryCode()->toString() === 'DE'` and `$clinic->currencyCode()->toString() === 'EUR'`. Given `jurisdictionCode: 'DE-BY'`, then `$clinic->jurisdictionCode() === 'DE-BY'`. Given `make load-fixtures`, then all rows in `clinics` table have non-null `country_code` and `currency_code`. Given a POST to Clinic create with `country_code=FR`, then Clinic stored with `country_code='FR'`.

- [ ] **AC8 (T8 — defaultLocale):** Given `DefaultLocaleResolver` with `$backofficeLocale = 'fr-FR'` and `$shortLocaleMap = ['fr' => 'fr-FR', 'en' => 'en-GB']`, when `resolve()` is called in BACKOFFICE scope, then returns `Locale::fromString('fr-FR')`. Given `normalizeCandidate('en')` called with `shortLocaleMap = ['en' => 'en-GB']`, then returns `'en-GB'`. Given `grep "'fr-FR'\|'en-GB'" src/System/Translation/Infrastructure/Resolver/DefaultLocaleResolver.php`, then zero results (no hardcoded locale string literals in the PHP class). Given `make ci`, then green.

- [ ] **AC9 (T9 — Admission enum neutralisation):** Given `IntakeChannel` enum, when inspected, then `EmergencyByMunicipality` case does NOT exist (deleted) and `EmergencyByAuthority = 'emergency_by_authority'` is the sole authority-emergency case. Given `ClosureReason::HandedToAuthority`, then `->value === 'handed_to_authority'`. Given `grep -rn "EmergencyByMunicipality\|emergency_by_municipality\|HandedToMunicipality\|handed_to_municipality\|Municipality\b\|municipality" src/ templates/ tests/`, then zero results. Given `make load-fixtures`, then no `admissions` row has `intake_channel = 'emergency_by_municipality'` or `presenter_role = 'municipality'`. Given `make ci`, then green.

- [ ] **AC10 (T10 — Regulatory renaming):** Given `grep -rn "MairieNotification\|ICADLookup" src/ tests/ fixtures/`, then zero results. Given `make load-fixtures`, then table `authority_notifications` exists and `mairie_notifications` does not; `microchip_registry_lookups` exists and `icad_lookups` does not. Given `StrayCustody::closeHandedToAuthority()`, when called on an Active custody, then status transitions to `StrayCustodyStatus::ClosedHandedToAuthority` and event `StrayCustodyClosedHandedToAuthority` is recorded. Given `make ci`, then green.

- [ ] **AC11 (T11 — Policy interfaces):** Given `FrancePolicyTest extends JurisdictionPolicyContractTest`, when all inherited test methods run, then all pass. Given `FrenchWorkingDayCalculatorTest extends WorkingDayCalculatorContractTest`, when all inherited tests run, then all pass. Given `AuthorityNotification::schedule()` with a mock `RegulatoryPolicyInterface`, then `getAuthorityNotificationDeadline()` is called on the mock (no hardcoded `+48 hours` in aggregate). Given `StrayCustody::begin()` with a mock `RegulatoryPolicyInterface`, then `getStrayCustodyDeadline()` is called on the mock (no `FrenchWorkingDayCalculator` or hardcoded `8` in aggregate). Given `grep -rn "FrenchWorkingDayCalculator\|'+48 hours'" src/Context/Regulatory/Domain/`, then zero results. Given `make ci`, then 100% coverage on Regulatory BC.

- [ ] **AC12 (T12 — France sub-namespace + JurisdictionResolver):** Given `ClinicJurisdictionResolver::resolveForClinic($clinicId)` where the clinic has `country_code='FR'` in DB, then returns `'FR'`. Given `grep -rn "FrenchWorkingDayCalculator" src/Context/Regulatory/Domain/`, then zero results (class moved to `Jurisdiction/France/`). Given a Regulatory handler resolving jurisdiction `'FR'` for a command, when the tagged service locator is queried, then `FranceRegulatoryPolicy` is selected. Given `make ci`, then green.

- [ ] **AC13 (T13 — Identification refactor):** Given `Identification` constructed with `registryReference: '2023-123456'`, then `$identification->registryReference === '2023-123456'`. Given `grep -rn "sireNumber\|sire_number" src/ tests/ fixtures/`, then zero results. Given `make load-fixtures`, then `animals` table has `registry_reference` column (not `sire_number`). Given `make ci`, then 100% coverage on Animal BC, green.

- [ ] **AC14 (T14 — Translation seeds):** Given `make load-fixtures`, when `TranslationEntryEntity` table is queried with `WHERE domain = 'regulatory' AND locale = 'fr-FR'`, then at least 11 rows exist (one per seeded key). Given `translationKey = 'authority_notification.scheduled'`, then `translationValue` is non-empty French text. Given `make ci`, then green.

## Additional Context

### Dependencies

- Phase 0.5 (local ClinicId) before Phase 1.2 (Clinic enrichment) — Animal/Client must not import from Clinic before new VOs added
- Phase 1.1 (Shared VOs) before Phase 1.2 (Clinic aggregate) — CountryCode/CurrencyCode VOs must exist first
- Phase 1.2 (Clinic carries countryCode) before Phase 2.3 (JurisdictionResolver reads it) — resolver needs the DB column
- Phase 2.2 (interfaces extracted) before Phase 2.3 (sub-namespace + wiring) — interfaces must exist before implementations move
- Phase 2.3 (France sub-namespace) before Phase 2.4 (RegistryType SIRE → France) — France namespace must exist before SIRE logic moves there

### Testing Strategy

- **100% line coverage per BC** (excl. Presentation) maintained throughout all phases
- **Unit tests for every new VO** (CountryCode, CurrencyCode, RegistryNumber, local ClinicId VOs)
- **Enum rename tests**: update all unit tests and integration tests that hardcode old enum string values (PlanningBlockType, IntakeChannel, ClosureReason, PresenterRole)
- **Contract tests** (Phase 2): `abstract class JurisdictionPolicyContractTest` and `abstract class WorkingDayCalculatorContractTest` — every jurisdiction implementation test extends these
- **`make ci` green after each phase** before starting the next
- **Fixture integrity**: after every rename, run `make load-fixtures` to confirm fixtures replay cleanly with zero errors

### Notes

- **READMEs (Phase 0.3)**: written in English per project convention. Four to update: Clinic (Staff sub-domain missing), Scheduling (mentions deleted WaitingRoomEntry), Consultation (very outdated — references AnimalId/OwnerId/WaitingRoomEntry all removed), IdentityAccess (minimaliste, missing auth/sub-types/AuthenticateUser query).
- **Phase 0.5 confirmed**: no schema migration — Animal and Client both map `clinicId` as `UuidType::NAME` (plain UUID string column). Only PHP import paths change.
- **Phase 1.3 confirmed**: fix `DefaultLocaleResolver.php` constructor default `= 'fr-FR'` AND the hardcoded `return Locale::fromString('fr-FR')` in the BACKOFFICE branch. `translation.yaml` already has `default_locale: en` — may just need to wire the parameter properly.
- **Phase 2.3**: `MessageContext` does not exist in this codebase. `JurisdictionResolverInterface` resolves from `ClinicId` (in command) via DBAL read — reads `country_code` from clinic table after Phase 1.2 adds the column.
- **Phase 2.4 Identification VO**: `sireNumber` lives inside `Identification` VO alongside `registryType`, `registryNumber`, `microchipNumber`, `tattooNumber`, `passportNumber`. `ensureConsistency()` validates `RegistryType::NONE` → null `registryNumber`. Refactor must preserve this invariant.
- **ICADLookupEntityFactory**: does NOT exist (no Foundry factory for ICADLookup entity). Will need to create one when renaming to `MicrochipRegistryLookup`.
- **PresenterRole::Municipality**: `emergency_form.html.twig` has it in the presenter role dropdown. `queue.html.twig` may reference it in label maps. Grep before deleting.
- **RISK — T13 Identification VO shape (F10)**: The exact refactor shape of `Identification.registryReference` (rename vs restructure) must be decided at implementation by reading `ensureConsistency()` invariants carefully. The accepted minimal approach: rename `sireNumber: ?string` → `registryReference: ?string` and extend `ensureConsistency()` to validate `RegistryType::NONE → null registryReference`. Deeper restructuring (typed VO, multiple registry entries) is out of scope for this spec.
- **DECISION — T7 write-once fields (F6)**: `countryCode`, `currencyCode`, `jurisdictionCode` on `Clinic` are write-once at creation. No update commands exist and none should be added in this spec. If modification is needed in production, create a dedicated `ChangeClinicJurisdiction` command as a follow-up.
- **DEBT NOTE — T7 ClinicController (F4)**: `ClinicController` has multiple public methods, violating `CLAUDE.md §5` (single-action controllers). This is pre-existing debt. T7 does not fix this; the split into individual `__invoke` controllers is tracked as a separate chore. Do not introduce additional methods — only modify existing create/update handlers.
- **AC8 scope**: AC8 now also checks `'en-GB'` externalisation — `grep "'en-GB'" src/System/Translation/Infrastructure/Resolver/DefaultLocaleResolver.php` should return zero results after T8.
