---
title: 'Staff Profiles for Practitioners (VET and ASV)'
slug: 'staff-profiles-vet-asv'
created: '2026-04-18'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
adversarialReviewRounds: 2
findingsIntegrated: 'F1-F16 + G1-G10'
tech_stack:
  - 'PHP 8.2+'
  - 'Symfony 7 (Messenger, Security, Twig)'
  - 'Doctrine ORM + DBAL, custom BC-prefix naming strategy'
  - 'MySQL with BIN_TO_UUID storage'
  - 'PHPUnit (strict: failOnDeprecation/Warning/Notice)'
  - 'DAMA DoctrineTestBundle (per-test transaction rollback)'
  - 'Zenstruck Foundry v2.8 (PersistentProxyObjectFactory)'
  - 'Tailwind CSS v4, Stimulus, Twig'
files_to_modify:
  - 'src/Context/Clinic/Domain/Staff/ValueObject/ClinicMemberRole.php'
  - 'src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ClinicVeterinarianItem.php'
  - 'src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinariansHandler.php'
  - 'src/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipRole/ChangeClinicMembershipRoleHandler.php'
  - 'templates/clinic/scheduling/_agenda.html.twig'
  - 'templates/clinic/scheduling/_scheduling_aside.html.twig'
  - 'templates/clinic/scheduling/_modal_new_appointment.html.twig'
  - 'tests/Unit/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinariansHandlerTest.php'
  - 'fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php'
  - 'fixtures/Context/Clinic/Story/ClinicMembershipDataStory.php (or equivalent entry)'
files_to_create:
  - 'src/Context/Clinic/Domain/Staff/StaffProfile.php'
  - 'src/Context/Clinic/Domain/Staff/VeterinaryCredentials.php'
  - 'src/Context/Clinic/Domain/Staff/ValueObject/StaffProfileId.php'
  - 'src/Context/Clinic/Domain/Staff/ValueObject/DisplayName.php'
  - 'src/Context/Clinic/Domain/Staff/ValueObject/HexColor.php'
  - 'src/Context/Clinic/Domain/Staff/ValueObject/ProfessionalRegistrationNumber.php'
  - 'src/Context/Clinic/Domain/Staff/ValueObject/ProfessionalTitle.php'
  - 'src/Context/Clinic/Domain/Staff/ValueObject/SignatureImageKey.php'
  - 'src/Context/Clinic/Domain/Staff/Repository/StaffProfileRepositoryInterface.php'
  - 'src/Context/Clinic/Application/Port/StaffProfileReadRepositoryInterface.php'
  - 'src/Context/Clinic/Application/Port/UserSearchForOnboardingInterface.php'
  - 'src/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMember.php'
  - 'src/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandler.php'
  - 'src/Context/Clinic/Application/Command/Staff/RenameStaffProfile/…'
  - 'src/Context/Clinic/Application/Command/Staff/UpdateStaffProfilePhone/…'
  - 'src/Context/Clinic/Application/Command/Staff/UpdateStaffProfileAgendaPreferences/…'
  - 'src/Context/Clinic/Application/Command/Staff/RegisterVeterinaryCredentials/…'
  - 'src/Context/Clinic/Application/Command/Staff/ClearVeterinaryCredentials/…'
  - 'src/Context/Clinic/Application/Exception/CannotRegisterCredentialsForNonVeterinarianRole.php'
  - 'src/Context/Clinic/Application/Exception/CannotChangeRoleWhileVeterinaryCredentialsExist.php'
  - 'src/Context/Clinic/Infrastructure/Console/CheckRoleCredentialsConsistencyCommand.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/StaffProfileEntity.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/StaffProfileMapper.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineStaffProfileRepository.php'
  - 'src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineStaffProfileReadRepository.php'
  - 'src/Context/Clinic/Infrastructure/Adapter/IdentityAccess/IdentityAccessUserSearchAdapter.php'
  - 'src/Presentation/Backoffice/Controller/Clinic/Staff/… (single-action controllers, one per route)'
  - 'templates/backoffice/clinic/staff/… (list + form templates)'
  - 'migrations/Clinic/Version2026…CreateStaffProfiles.php'
  - 'fixtures/Context/Clinic/Factory/StaffProfileEntityFactory.php'
  - 'tests/Unit/Context/Clinic/Domain/Staff/…'
  - 'tests/Unit/Context/Clinic/Application/Command/Staff/…'
  - 'tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineStaffProfileRepositoryTest.php'
code_patterns:
  - 'DDD aggregate — private ctor, create() named constructor, reconstitute() for rehydration, extends App\Shared\Domain\Aggregate\AggregateRoot'
  - 'Value Object — fromString() factory, toString() + equals(), final + readonly'
  - 'Command — final readonly DTO implementing App\Shared\Application\Bus\CommandInterface'
  - 'Command handler — final readonly with #[AsMessageHandler], __invoke(), injects repo + port + UuidGenerator + Clock + DomainEventPublisher'
  - 'Named domain exception — extends \RuntimeException, constructor with explicit context (mirror DuplicateClinicSlugException)'
  - 'Port/Adapter for cross-BC — port in Application/Port/, adapter in Infrastructure/Adapter/<OtherBC>/'
  - 'Doctrine read repo — DBAL Connection + EntityManager metadata for table name; fetchAllAssociative; BIN_TO_UUID for output; binary UUID bindings'
  - 'Doctrine entity — #[ORM\Entity] + #[ORM\Table] (no explicit name, BC-prefix naming strategy fills it)'
  - 'Migration — namespace DoctrineMigrations\Clinic, extends AbstractMigration, up()+down() with raw SQL'
  - 'Foundry factory v2 — extends PersistentProxyObjectFactory, class() returns entity class, defaults() array, named states as withX() chainable methods'
  - 'Single-action controller — __invoke() only, one Route attribute per class'
  - 'Backoffice route naming — backoffice_{resource_plural}_{action}'
test_patterns:
  - 'Unit domain — aggregates tested via recordedDomainEvents() payload assertions + state getters; aggregates with no events (StaffProfile, D14) use state-based assertions only'
  - 'Unit handlers — direct $handler($command) invocation, all infra (repo, ports, clock, uuidGenerator, publisher) mocked'
  - 'Unit VO — testFromStringWith*/testFromStringRejects* patterns, one case per validation branch'
  - 'Integration — KernelTestCase + Factories trait, DAMA auto-rollback, Foundry createOne() for seeding, assertions on repository round-trip'
  - 'Completeness — snapshot test for enum capability methods (D16)'
---

# Tech-Spec: Staff Profiles for Practitioners (VET and ASV)

**Created:** 2026-04-18

## Overview

### Problem Statement

The Clinic BC currently has no notion of a staff member's displayed identity
or professional data beyond `ClinicMembership` (authorization-only aggregate).
The gaps show up in three places:

- **Agenda rendering**: `templates/clinic/scheduling/_agenda.html.twig:80`
  falls back to `appointment.practitionerUserId` (a UUID / email) because
  there is no `displayName` to read.
- **Prescriptions / consultation data readiness**: no way to surface a
  practitioner's professional title (`Dr.`, `Pr.`) or registration
  number (the French "numéro d'ordre", the Spanish "Nº de colegiado",
  the UK RCVS number, etc.) for future prescription flows. **v1 does
  not ship a prescription feature — it prepares the data**, making
  `professionalTitle` + `registrationNumber` available for cross-BC
  consumption when Consultation / e-prescriptions arrive.
- **Agenda display preferences** (per-practitioner color, sort order,
  visibility) have nowhere to live today.

### Solution

Introduce a new aggregate `StaffProfile` under
`src/Context/Clinic/Domain/Staff/`, linked **1:1 to `ClinicMembership` via
`membershipId`** (not a `(clinicId, userId)` composite). The profile owns:

- **Local identity** — `firstName`, `lastName`, `displayName` stored on the
  profile itself. No dependency on `IdentityAccess.User` for display (explicit
  tradeoff: simpler write flow, acceptable divergence risk).
- **Agenda preferences** — `agendaColor`, `sortOrder`, `isVisibleInAgenda`.
- **Optional veterinary credentials** — grouped in a small embedded VO
  `VeterinaryCredentials` with internationally-neutral naming
  (`registrationNumber`, `professionalTitle`, `signatureImageKey?`). No
  country-specific regex. `registrationCountry` and `registrationAuthority`
  are deliberately **not** introduced in v1 — they will be derivable from
  the clinic the day a concrete use case (multi-country electronic
  prescriptions) justifies them.

**Role capabilities** are centralized as methods on `ClinicMemberRole`
(`canHoldVeterinaryCredentials()`, `canBePractitionerOfRecord()`,
`appearsInMedicalAgendaByDefault()`), so any future role addition
(`SECRETARY`, additional practitioner types) is a one-line enum extension —
no `if role === VETERINARY` branching is allowed anywhere else in the codebase.

The **VET/ASV guard for credentials** is enforced in the application handler,
not inside `StaffProfile` — the profile aggregate does not hold a reference to
`ClinicMembership`, avoiding inter-aggregate coupling.

### Scope

**In Scope (v1):**

- New aggregate `StaffProfile` with its VOs, Doctrine entity/mapper/repository
  and migration.
- Profile creation for `VETERINARY` and `VETERINARY_ASSISTANT` roles only
  (other roles are structurally supported but not rolled out).
- Combined command `OnboardStaffMember` creating `ClinicMembership` +
  `StaffProfile` in one transaction.
- Profile update commands: rename, change phone, update agenda
  preferences, `registerVeterinaryCredentials` /
  `clearVeterinaryCredentials`.
- Policy: a role change that invalidates existing credentials (target role
  fails `canHoldVeterinaryCredentials()`) is refused while
  `credentials !== null` — no silent purge.
- `ClinicVeterinarianItem` extended with display fields (`displayName`,
  `professionalTitle`, `agendaColor`, `sortOrder`, `isVisibleInAgenda`) —
  intra-BC, no Option D composition required for v1.
- Agenda UI renders `Dr. {displayName}` instead of the raw userId/email.
- Backoffice **manager-only** admin UI to CRUD staff profiles in their clinic.
- Foundry fixtures seeding realistic profiles for dev clinics.
- 100% line coverage on Domain + Application + Infrastructure of the Clinic BC
  (project-wide non-negotiable).

**Out of Scope (v1):**

- `SECRETARY`, `RECEPTIONIST`, `MANAGER` profiles — structurally addable in
  one enum value + capability definition, not delivered in this iteration.
- Self-service "My profile" page for a staff member editing their own data.
- Domain events on `StaffProfile` — no consumers today, explicit YAGNI.
- Dedicated CQRS read models (`GetPractitionerAgendaView`,
  `GetStaffDirectoryView`, `GetVeterinarianSignatureView`) — deferred until
  independent evolution is actually needed.
- Country-specific format validation for `registrationNumber`
  (ONVF / RCVS / RPPS / colegiación regex). Light validation only in v1.
- `registrationCountry` and `registrationAuthority` fields. Deferred until a
  concrete multi-country use case (electronic prescriptions) demands them.
  When introduced, they will most likely be derivable from the clinic
  context, not re-entered manually on each profile.
- `Specialty` enum and collection on the profile.
- Consultation BC wiring — data is made available for a future prescription
  flow but no consumer is written in this spec.
- Signature image upload infra — only the `signatureImageKey` slot exists;
  the actual image storage pipeline is out of scope.
- Cross-membership identity continuity — a member who leaves and returns gets
  a new `ClinicMembership` and therefore a fresh `StaffProfile` (no historical
  merge in v1).
- RPPS number field (France-specific medical identifier) — not needed until
  electronic prescriptions are on the roadmap.
- `jobTitle` display field (e.g. "ASV d'accueil") — considered in design
  but dropped from v1. No UI consumer, so no column. Addable later as a
  non-breaking migration when a use case materializes.

## Context for Development

### Codebase Patterns

- **BC layout**: `src/Context/<BC>/{Domain,Application,Infrastructure}`,
  Presentation in `src/Presentation/<App>/`. Already applied in
  `src/Context/Clinic/`.
- **Aggregate pattern** (see `src/Context/Clinic/Domain/Staff/ClinicMembership.php`):
  private constructor, static `create()` named constructor, static
  `reconstitute()` for rehydration, extends
  `App\Shared\Domain\Aggregate\AggregateRoot`. `AggregateRoot` exposes
  `recordDomainEvent()`, `pullDomainEvents()` (clears the buffer),
  `recordedDomainEvents()` (read-only, useful for tests).
- **Command DTO**: `final readonly` class implementing
  `App\Shared\Application\Bus\CommandInterface` — all fields public
  readonly, constructor-promoted (see
  `CreateClinicMembership.php`).
- **Command handler**: `final readonly` class with `#[AsMessageHandler]`,
  single `__invoke(<Command> $command)` method. Typical dependencies:
  domain repository, cross-BC port (`UserExistenceCheckerInterface`
  exists today), `UuidGeneratorInterface`, `ClockInterface`,
  `DomainEventPublisher`. Mirror `CreateClinicMembershipHandler.php`
  for `OnboardStaffMemberHandler`, except the handler returns a
  `StaffProfileId` (D19).
- **VO convention**: `fromString()` factory, `toString()` and `equals()`
  methods, `final` class. See `PhoneNumber`
  (`src/Shared/Domain/ValueObject/PhoneNumber.php`), `ClinicSlug` and
  `TimeZone` (`src/Context/Clinic/Domain/ValueObject/`).
- **Named domain exception** (see
  `src/Context/Clinic/Application/Exception/DuplicateClinicSlugException.php`,
  `ClinicMembershipAlreadyExistsException.php`): `final class`
  extending `\RuntimeException`, constructor that accepts the minimum
  domain context and builds the message via `sprintf()`. Mirror this
  for `CannotRegisterCredentialsForNonVeterinarianRole` (D22) and
  `CannotChangeRoleWhileVeterinaryCredentialsExist` (D22).
- **Doctrine entity**: `#[ORM\Entity]` + `#[ORM\Table]` **without** an
  explicit `name:` — see D25 below. Index/unique attributes with
  explicit names.
- **Doctrine read repo (DBAL)** (see
  `DoctrineClinicMembershipReadRepository.php`): constructor injects
  `Connection`, `ClockInterface`, `EntityManagerInterface` (the EM is
  used once to resolve the physical table name via
  `getClassMetadata(...)->getTableName()` — apply the same trick for
  the new `DoctrineStaffProfileReadRepository`). Uses
  `BIN_TO_UUID(...)` on output and binds user ids with
  `Uuid::fromString(...)->toBinary()`.
- **Migration pattern** (see `migrations/Clinic/Version20260411000001.php`):
  `namespace DoctrineMigrations\Clinic`, `final class
  Version<TIMESTAMP> extends AbstractMigration`, raw SQL in `up()` and
  `down()`. Table names hard-coded in the SQL — verify with D25 before
  writing.
- **Foundry v2 pattern** (see
  `fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php`):
  extends `PersistentProxyObjectFactory`, static `class(): string`
  returns the entity FQCN, `defaults()` returns an associative array
  using `self::faker()` and `Uuid::v7()`. Named states expressed as
  chainable `withX()` / `asX()` methods that call `$this->with([...])`.
- **Fixtures composition** (see `fixtures/DevDataset.php`): a root
  `Story` marked `#[AsFixture(name: 'dev')]` composes sub-stories
  (`PlatformDataset`, `ClinicDataset`, `PortalDataset`). Existing dev
  clinics: `clinic-paris`, `clinic-lyon`.
- **Backoffice routing** (see `config/routes/backoffice.yaml`,
  `src/Presentation/Backoffice/Controller/`): single-action
  controllers with `#[Route(path: '/…', name:
  'backoffice_<resource_plural>_<action>', methods: […])]`. Firewall
  enforces `IS_AUTHENTICATED_FULLY` via host match
  `backoffice.kiveto.local`; **no role-based voter exists yet — a
  manager-only access rule (D28) must be added for the new staff
  profile screens.**
- **Backoffice templates**: extend `templates/backoffice/base.html.twig`
  (blocks: `title`, `page_title`, `topbar_actions`, `content`,
  `modals`, `drawers`). Forms are hand-rolled (no Symfony Form
  component), CSRF via `CsrfTokenManagerInterface` + hidden
  `_token` input. Reusable macros in `templates/components/ui/`
  (e.g. `select.html.twig`).
- **Agenda composition**: `AgendaController` already composes 4
  queries (`GetClinic`, `GetAgendaForClinicDateRange`,
  `ListClinicVeterinarians`, `ListWaitingRoom`). Extending
  `ClinicVeterinarianItem` fits this flow without a new query, per
  D12.
- **Single-action controllers** (`__invoke` only) are a project-wide
  non-negotiable — one route per class.

### Test Patterns

- **Unit domain tests** live under `tests/Unit/Context/<BC>/Domain/…`.
  Existing aggregates (`Clinic`, `ClinicMembership`) assert domain
  events via `recordedDomainEvents()` payload checks. **StaffProfile
  records no events (D14)** — assertions are state-based only
  (getters + `hasVeterinaryCredentials()`).
- **Unit handler tests** (`tests/Unit/Context/<BC>/Application/Command|Query/…`):
  direct `$handler($command)` invocation, all infra (repos, ports,
  clock, uuid generator, event publisher) mocked via PHPUnit doubles
  or hand-rolled fakes. No DB. See `CreateClinicHandlerTest.php` for
  shape.
- **Unit VO tests** (`tests/Unit/Context/<BC>/Domain/ValueObject/…`):
  one `testFromString*` per valid/invalid branch, using
  `expectException(\InvalidArgumentException::class)` for rejections.
  See `ClinicSlugTest.php`.
- **Integration tests** (`tests/Integration/…`): `KernelTestCase`
  + `use Zenstruck\Foundry\Test\Factories;`. DB reset via DAMA
  `PHPUnitExtension` (per-test transaction wrapping — each test rolls
  back, no fixtures pollution). See
  `DoctrineClinicMembershipRepositoryTest.php`.
- **phpunit.xml** at project root. Strict flags:
  `failOnDeprecation="true" failOnWarning="true"
  failOnNotice="true"`. Two suites: `unit` (`tests/Unit/`) and
  `integration` (`tests/Integration/`).

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Shared/Domain/Aggregate/AggregateRoot.php` | Base aggregate — `recordDomainEvent`/`pullDomainEvents`/`recordedDomainEvents` |
| `src/Context/Clinic/Domain/Staff/ClinicMembership.php` | Existing aggregate pattern + FK target |
| `src/Context/Clinic/Domain/Staff/ValueObject/ClinicMemberRole.php` | Enum to extend with capability methods (cases: `MANAGER`, `VETERINARY`, `VETERINARY_ASSISTANT`, `RECEPTIONIST`) |
| `src/Context/Clinic/Domain/Staff/ValueObject/ClinicMembershipId.php` | FK type for `membershipId` |
| `src/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembership.php` | Command DTO shape to mirror |
| `src/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembershipHandler.php` | Handler shape to mirror (incl. `UserExistenceCheckerInterface`, `UuidGeneratorInterface`, `ClockInterface`, `DomainEventPublisher`) |
| `src/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipRole/ChangeClinicMembershipRoleHandler.php` | Handler to **modify** — add D7 role-change guard |
| `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinariansHandler.php` | Handler to **modify** — compose two read repos per D12 |
| `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ClinicVeterinarianItem.php` | DTO to extend with display fields |
| `src/Context/Clinic/Application/Port/UserExistenceCheckerInterface.php` | Reuse for user existence guard in `OnboardStaffMember` |
| `src/Context/Clinic/Application/Exception/DuplicateClinicSlugException.php` | Named exception style to mirror (D22) |
| `src/Context/Clinic/Application/Exception/ClinicMembershipAlreadyExistsException.php` | Second named-exception example |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/ClinicMembershipEntity.php` | Doctrine entity pattern |
| `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepository.php` | DBAL read-repo pattern (table-name via EM metadata, BIN_TO_UUID, binary UUID bindings) |
| `src/Context/Clinic/Infrastructure/Adapter/IdentityAccess/DbalUserExistenceChecker.php` | Existing Clinic→IdentityAccess DBAL adapter — reference for the new `IdentityAccessUserSearchAdapter` |
| `src/System/IdentityAccess/Domain/User.php` | Confirmed: **no** `firstName`/`lastName` fields — resolves D4 divergence risk |
| `src/System/IdentityAccess/Application/Port/UserReadRepositoryInterface.php` | `listAll(?search, ?type, ?status)` — email LIKE search surface available for D24 |
| `src/System/IdentityAccess/Application/Query/ListUsers/ListUsers.php` | Existing email search query; `ListUsers\UserListItem` DTO shape |
| `src/Shared/Infrastructure/Persistence/Doctrine/Mapping/BoundedContextPrefixNamingStrategy.php` | Custom naming strategy driving `clinic__*` table names — see D25 |
| `migrations/Clinic/Version20260411000001.php` | Example migration shape (`clinic__clinic_memberships` is the concrete membership table name) |
| `src/Shared/Domain/ValueObject/PhoneNumber.php` | VO style to mirror |
| `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php` | Consumer of `ListClinicVeterinarians` — signature unchanged, DTO enrichment is transparent |
| `src/Presentation/Backoffice/Controller/ClinicController.php` | Backoffice controller pattern (single-action, `#[Route]`, form via `request->request`) |
| `templates/backoffice/base.html.twig` | Backoffice layout base — extend for new admin screens |
| `templates/backoffice/clinics/edit.html.twig` | Backoffice form pattern + `components/ui/select.html.twig` usage |
| `templates/clinic/scheduling/_agenda.html.twig` | Template to modify (`practitionerUserId` → `displayName`, line 80) |
| `templates/clinic/scheduling/_scheduling_aside.html.twig` | Secondary agenda template — uses `ClinicVeterinarianItem` |
| `templates/clinic/scheduling/_modal_new_appointment.html.twig` | Practitioner `<select>` options currently show `userId` — update to display name |
| `config/packages/security.yaml` | Backoffice firewall definition — may need a role/voter addition for manager-only access |
| `fixtures/DevDataset.php` | Root fixture story (`#[AsFixture(name: 'dev')]`) |
| `fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php` | Foundry factory to mirror + extend |
| `fixtures/Context/Clinic/Story/ClinicMembershipDataStory.php` | Existing membership seeding (dispatches `CreateClinicMembership`) — reference for profile seeding strategy |
| `tests/Unit/Context/Clinic/Domain/ClinicTest.php` | Aggregate unit test shape (for event-recording aggregates) |
| `tests/Unit/Context/Clinic/Domain/ValueObject/ClinicSlugTest.php` | VO unit test shape |
| `tests/Unit/Context/Clinic/Application/Command/Clinic/CreateClinic/CreateClinicHandlerTest.php` | Handler unit test shape (mocks + direct invocation) |
| `tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipRepositoryTest.php` | Doctrine integration test shape (`KernelTestCase` + DAMA rollback + Foundry factories) |
| `phpunit.xml` | Strict flags + test suite structure |

### Technical Decisions

- **D1 — Separate aggregate.** `StaffProfile` is its own aggregate, distinct
  from `ClinicMembership`. Authorization (role, status, validity) and
  profile/display concerns stay segregated. Migration path to richer CQRS
  (events, dedicated read models) stays non-destructive.
- **D2 — FK on `membershipId`.** One-to-one with a `UNIQUE` constraint on
  `membership_id`. No composite `(clinicId, userId)` FK. Locked.
- **D3 — Lifecycle = new membership → new profile.** No historical continuity
  across membership lifecycles. A returning staff re-enters their profile
  once on the second onboarding.
- **D4 — Local identity storage.** `firstName`, `lastName`, `displayName`
  stored on `StaffProfile` directly. No read-side coupling to
  `IdentityAccess.User` for naming. Acknowledged divergence risk.
- **D5 — Role capabilities on the enum.** Methods like
  `ClinicMemberRole::canHoldVeterinaryCredentials()`,
  `canBePractitionerOfRecord()`, `appearsInMedicalAgendaByDefault()`
  centralize role behaviour. No `if role === VETERINARY` branching elsewhere.
- **D6 — VET/ASV guard at the application layer.** The "only certain roles
  may hold veterinary credentials" rule is enforced inside
  `RegisterVeterinaryCredentialsHandler`, which loads both aggregates and
  checks `role->canHoldVeterinaryCredentials()` before calling
  `$profile->registerVeterinaryCredentials(...)`. The profile aggregate never
  references a live `ClinicMembership`.
- **D7 — Role-change guard.** `ChangeClinicMembershipRoleHandler` loads the
  associated profile and refuses the transition when
  `profile->hasVeterinaryCredentials() && !$newRole->canHoldVeterinaryCredentials()`.
  The operator must `clearVeterinaryCredentials` explicitly first.
- **D8 — VOs obligatoires.** `StaffProfileId`, `DisplayName`, `HexColor`,
  `ProfessionalRegistrationNumber`, `SignatureImageKey`. Light validation
  only — non-empty + trim + length bound. No regex per country.
- **D9 — Composite VO for credentials.** `VeterinaryCredentials` (no
  "Lite" suffix — a domain name describes what the thing is, not its
  maturity) regroups `registrationNumber`, `professionalTitle`,
  `signatureImageKey?`. Embedded inside the aggregate, nullable as a
  whole (a profile either has vet credentials or it does not).
- **D10 — International naming.** `registrationNumber` is internationally
  neutral — not `licenseNumber` (US-centric) nor `ordinalNumber`
  (FR-centric). `registrationCountry` / `registrationAuthority` are
  explicitly out of scope for v1 and will be sourced from the clinic
  context when a multi-country use case materializes.
- **D10b — `ProfessionalTitle` as explicit enum.** `ProfessionalTitle` is
  modelled as an enum with cases `DR`, `PR`, `NONE` — non-nullable on the
  `VeterinaryCredentials` VO. Justification: not all veterinarians are
  doctors (the title depends on whether the practitioner has defended
  their "thèse d'exercice"), so "no title" is a real, explicit domain
  state — not the absence of a value. UI v1 composes the display as
  `{title} {firstName} {lastName}` (e.g. "Dr. Rousseau", or just
  "Rousseau" when `title === NONE`).
- **D11 — `OnboardStaffMember` combined command.** Creates membership +
  profile in one transaction. Existing `CreateClinicMembership` is retained
  for flows that don't need a profile (exact fate — keep, deprecate, or
  replace — to be confirmed in Step 2).
- **D12 — Read side extension via composition, no cross-table JOIN.**
  `ClinicVeterinarianItem` gains `displayName`, `professionalTitle`,
  `agendaColor`, `sortOrder`, `isVisibleInAgenda`. Implementation:
  **two composed queries** inside `ListClinicVeterinariansHandler` —
  the existing `ClinicMembershipReadRepository` returns the veterinary
  memberships, the new `StaffProfileReadRepository` (see D20) returns
  the corresponding profiles via a batch lookup (see D20b). The handler
  zips them into the enriched DTO. Intra-BC, no SQL JOIN across the
  two tables, repo naming stays honest.
- **D13 — Persistence.** `role` column stays `VARCHAR` mapped to the
  applicative enum (not a DB-level `ENUM` type) — keeps future role
  additions cheap.
- **D14 — No domain events on `StaffProfile` in v1.** `ClinicMembership`
  events remain untouched. Events are a non-destructive upgrade path when a
  consumer materializes.
- **D15 — Manager-only admin UI.** Backoffice surface only, no self-service
  "My profile" in v1. Exact route structure and form shape to be designed
  in Step 2 / Step 3. Rationale for deferring self-service: real cost
  under-estimated (edit flow + permissions + visibility rules), assisted
  onboarding in year 1 is sufficient to guarantee data quality, and
  adding self-service later is a non-destructive addition.
- **D16 — Role enum completeness via a unit test with an explicit
  expectations table.** Replaces a "grep the codebase" approach.
  Introduce
  `tests/Unit/Context/Clinic/Domain/Staff/ValueObject/ClinicMemberRoleCapabilityCompletenessTest`
  that holds an **explicit expectations snapshot** — a mapping from
  each `ClinicMemberRole` case to the expected boolean value of every
  capability method (`canHoldVeterinaryCredentials`,
  `canBePractitionerOfRecord`, `appearsInMedicalAgendaByDefault`). The
  test iterates `ClinicMemberRole::cases()` and asserts both
  **completeness** (every case is present in the snapshot) and
  **correctness** (actual values match the snapshot). Any future role
  addition forces a manual, reviewed update of the snapshot — no
  silent copy-paste drift, no reliance on developer discipline.
- **D17 — Known concurrency debt: role-change race.** The VET/ASV guard
  (D6) and the role-change guard (D7) both run at the application layer
  without optimistic locking. A role can be changed from `VETERINARY` to
  `VETERINARY_ASSISTANT` by operator A while operator B is concurrently
  registering credentials, leaving the aggregate in a state that
  violates the invariant. **Accepted debt for v1**: manager-only UI and
  the expected load profile make a concurrent collision improbable.
  Mitigation (optimistic locking via a `version` column on `StaffProfile`
  and/or `ClinicMembership`, or a domain policy that locks both in the
  same DB transaction) is deferred to the iteration that enables
  self-service or multi-operator workflows.
- **D18 — Onboarding atomicity via the `doctrine_transaction`
  middleware.** **Step 3b confirms** the command bus
  (`messenger.bus.command` in `config/packages/messenger.yaml`)
  wires the `doctrine_transaction` middleware. Every command
  handler dispatched via the bus is therefore already wrapped in a
  Doctrine transaction that rolls back on any thrown exception.
  **`OnboardStaffMemberHandler` does not call `wrapInTransaction`
  explicitly** — it mirrors existing handlers (see
  `CreateClinicMembershipHandler.php`) that combine `save()` +
  `publish()` in a single method and rely on the middleware.
  Atomicity is proven by AC9: a test decorator throws from
  `StaffProfileRepository::save`, the handler propagates, the
  middleware rolls back, the integration test asserts no membership
  row remains.
- **D19 — `OnboardStaffMember` returns `StaffProfileId`.** The command
  handler returns the newly generated `StaffProfileId` (not `void`, not
  `ClinicMembershipId`), so the admin controller can redirect to the
  profile edit screen immediately after creation. The membership id is
  retrievable from the profile via `getMembershipId()`.
- **D20 — Dedicated `DoctrineStaffProfileReadRepository`.** The JOIN on
  `clinic__clinic_memberships` x `clinic__staff_profiles` for the extended
  `ClinicVeterinarianItem` lives in a **new**
  `DoctrineStaffProfileReadRepository` (port:
  `StaffProfileReadRepositoryInterface`). The existing
  `DoctrineClinicMembershipReadRepository` is **not** repurposed — a repo
  named after memberships must not return profile data. The
  `ListClinicVeterinariansHandler` is updated to compose the two read
  repositories (intra-BC, same handler) rather than putting a
  profile-shaped query on the membership repo.
- **D21 — Known debt: manager self-role-change.** A MANAGER can change
  their own role to `VETERINARY` and subsequently register credentials
  for themselves. **Accepted debt for v1**: manager role is already
  privileged and the blast radius of this misuse is limited (logged role
  change, reversible). A future iteration can introduce a
  "role change requires approval" policy or forbid self-role-change
  outright.
- **D17b — Consistency check via a periodic Symfony command, not
  read-time logging.** Rejected: logging a warning on every read that
  spots a credentials/role inconsistency — too much noise, repeated
  per request. Accepted: a **scheduled Symfony console command**
  `app:clinic:staff:check-role-credentials-consistency` that scans
  all `StaffProfile` rows whose linked `ClinicMembership` has a role
  failing `canHoldVeterinaryCredentials()`, writes a
  machine-parseable line per offender to **stdout**, emits a
  `LoggerInterface::warning` per offender, and exits with code **1**
  if any offender is found (exit 0 otherwise). Runs daily (or
  on-demand). **Scope: business consistency only** — credentials vs
  role divergence — **not** infra-level concerns like event-publish
  failures (see D30). Ownership of the non-zero exit signal is
  documented in Notes → "Event and consistency monitoring ownership".
- **D20b — Batch read to prevent N+1.** `StaffProfileReadRepositoryInterface`
  exposes a batch method `findByMembershipIds(array $membershipIds):
  array` returning a `membershipId => StaffProfileReadItem` map.
  `ListClinicVeterinariansHandler` calls it once per request with the
  full list of membership ids it has already fetched. No per-row
  lookup, one round-trip, predictable cost even at 50+ practitioners
  per clinic.
- **D22 — Named domain exceptions, aligned with existing convention.**
  Mirror `DuplicateClinicSlugException` style.
  `StaffProfile::registerVeterinaryCredentials()` throws
  `CannotRegisterCredentialsForNonVeterinarianRole` when the
  application-layer guard (D6) detects the role mismatch.
  `ChangeClinicMembershipRoleHandler` throws
  `CannotChangeRoleWhileVeterinaryCredentialsExist` when the D7 guard
  fires. Placed under
  `src/Context/Clinic/Application/Exception/`. No generic
  `\DomainException` fallbacks for these known business rules.
- **D23 — `SignatureImageKey` VO with storage-neutral validation.**
  New VO `src/Context/Clinic/Domain/Staff/ValueObject/SignatureImageKey.php`
  with `fromString()` factory applying: trim, non-empty, length ≤ 255
  (G6 correction — aligned with Task A2 and the VARCHAR(255) in C4),
  ASCII-safe charset (no control chars, no unicode), no `..` substring
  (path-traversal guard), no leading slash. Deliberately **neutral vs
  the storage backend** — the VO validates the *shape* of the key, not
  that it points to an existing object (that is the storage adapter's
  concern in a future signature-upload iteration).
- **D24 — User selection flow v1: email autocomplete on existing
  IdentityAccess users.** `OnboardStaffMember` form exposes an
  autocomplete search by email against existing users in the
  IdentityAccess BC. If the email does not match an existing user,
  the manager is told to invite the user via a **separate upstream
  IdentityAccess flow** — user creation + invitation + tokens +
  expiration + security is treated as a standalone IdentityAccess
  sub-project, explicitly out of this WIP. Manual UUID input is
  **excluded**. **Step 2 confirms the cost is low**: IdentityAccess
  already exposes an email-LIKE search via
  `UserReadRepositoryInterface::listAll(?search, ?type, ?status)`
  (see `src/System/IdentityAccess/Application/Port/UserReadRepositoryInterface.php`).
  No new query needed inside IdentityAccess. The only new piece is a
  Clinic-side port + adapter (see D24b). Create-on-the-fly upgrade
  still deferred — the invitation flow (tokens, email, expiration) is
  the expensive bit, not the lookup.
- **D24b — Clinic-side port `UserSearchForOnboardingInterface`**
  (new). Defines
  `searchByEmail(string $emailFragment, int $limit): array` returning
  lean DTOs (`UserSearchResultItem { userId, email }`) suitable for
  populating an autocomplete. Implemented by
  `IdentityAccessUserSearchAdapter` in
  `src/Context/Clinic/Infrastructure/Adapter/IdentityAccess/`, which
  delegates to
  `UserReadRepositoryInterface::listAll($fragment, UserType::CLINIC->value, UserStatus::ACTIVE->value)`.
  **Signature correction (post-audit):** `listAll` accepts
  `(?string $search, ?string $type, ?string $status)` and returns a
  `UserCollection`, not an array — the adapter iterates the collection
  (`->items()` or equivalent accessor) and maps each `UserListItem`
  to `UserSearchResultItem`. Minimum fragment length is enforced at
  the port boundary (see AC21b). Keeps the cross-BC boundary clean:
  Clinic owns the port it needs, IdentityAccess keeps its internal
  read repo.
- **D25 — Table naming resolved via naming strategy.** Investigation
  confirms a custom Doctrine naming strategy
  `App\Shared\Infrastructure\Persistence\Doctrine\Mapping\BoundedContextPrefixNamingStrategy`
  (wired in `config/services.yaml` and `config/packages/doctrine.yaml`).
  Entities in the Clinic BC that declare `#[ORM\Table]` with no
  explicit `name:` are prefixed `clinic__` and pluralised. Concrete
  names in production: `clinic__clinics`, `clinic__clinic_groups`,
  `clinic__clinic_memberships`. **The new entity
  `StaffProfileEntity` therefore maps to `clinic__staff_profiles`
  automatically — no `name:` attribute required.** The migration
  writes table literals; verify with a dry run (`make
  migrations-diff`) before committing.
- **D26 — Role enum: keep `VETERINARY`.** Investigation confirms
  `ClinicMemberRole::VETERINARY` is used across repos, queries,
  migrations, fixtures (`ClinicMembershipEntityFactory::asVeterinary()`)
  and the DB-stored value is the literal string `VETERINARY`.
  Renaming to `VETERINARIAN` would force a data migration + touch 10+
  files for cosmetic gain. **Decision: keep `VETERINARY` everywhere**,
  spec uses the same token consistently.
- **D27 — Onboarding uses `OnboardStaffMember`;
  `CreateClinicMembership` is restricted to non-practitioner roles.**
  **Reformulated post-F9.** Investigation shows that the current
  `ClinicMembershipDataStory` dispatches **6** commands: 1 MANAGER,
  1 RECEPTIONIST (approx), and **4–5 practitioner roles** (VET +
  ASV). After B10's anti-bypass guard lands, the practitioner
  dispatches **must** move to `OnboardStaffMember`. Concretely:
  - `ClinicMembershipDataStory` retains only the non-practitioner
    dispatches (MANAGER, RECEPTIONIST). It is effectively gutted to
    1–2 calls.
  - The new `ClinicStaffProfileDataStory` dispatches
    `OnboardStaffMember` for all VET/ASV seeding.
  - `CreateClinicMembership` itself stays in the codebase (used by
    other hypothetical flows and unit tests), but its handler now
    refuses VET/ASV roles at runtime (B10).
  - Full deprecation of `CreateClinicMembership` is **not** planned
    in this spec — the command still makes sense for MANAGER /
    RECEPTIONIST onboarding where no practitioner profile is
    warranted.
- **D28 — Manager-only backoffice access: BLOCKED pending authN
  redesign (F2 audit).** **Originally planned**: add a
  `ClinicManagerOnlyVoter` that checks "the authenticated user has
  an active MANAGER membership in the clinic targeted by the
  current request via `CurrentClinicContextInterface`".
  **Audit finding (F2)**: this design has no runtime foundation in
  the current codebase:
  - The backoffice firewall uses the `backoffice_user_provider`
    (`config/packages/security.yaml`), which hydrates users whose
    `UserType === BACKOFFICE`. Those users **do not** have
    `ClinicMembership` rows — the two user types are structurally
    separate in `src/System/IdentityAccess/Domain/User.php`.
  - `CurrentClinicContextInterface` is used exclusively by
    controllers under `src/Presentation/Clinic/` (the tenant app).
    A grep under `src/Presentation/Backoffice/` returns zero
    results — there is no existing "current clinic" context on the
    backoffice host.
  - The existing `ClinicController` and `ClinicMembershipController`
    in the backoffice perform **no** membership check today; any
    authenticated backoffice user CRUDs any clinic.
  **Consequence**: the `ClinicManagerOnlyVoter` as specified would
  deny every request. Phase D (backoffice UI) **cannot ship with
  D28 as originally drafted**.
  **Resolution paths (decision deferred to a dedicated follow-up
  spec)**:
  - (a) Introduce an explicit `BackofficeUser ↔ ClinicMembership`
    relation (modifies IdentityAccess + data model) — significant
    scope.
  - (b) Accept that backoffice users are privileged operators and
    gate the new routes with a simple `ROLE_BACKOFFICE_ADMIN`
    attribute on `SecurityUser` — cheap, but conflates "admin over
    any clinic" with "manager of clinic X".
  - (c) Migrate the staff-profile admin UI out of the backoffice
    host onto the tenant app (`src/Presentation/Clinic/`) where
    `CurrentClinicContextInterface` and real `ClinicMembership`
    checks already exist — aligns best with D28's original intent
    but changes where the UI lives.
  **v1 stance**: **Phase D is deferred from this spec** until the
  authN decision lands. PR3 in the D29 plan becomes the empty head
  of the chain or is re-scoped entirely. The domain + application
  + infrastructure layers (Phases A, B, C) are independently
  shippable and useful (fixtures populate profiles, agenda
  consumes the enriched DTO via existing `AgendaController` on the
  tenant app — not the backoffice). **Do not code D28 as
  specified.**
- **D29 — Merge strategy: 4 sequential PRs, with B10 + fixture
  migration bundled together (F1 fix).** Rejected: a single large
  PR. Accepted: four sequential PRs whose intermediate states
  remain runnable AND keep `make ci` green on `master`:
  - **PR1** — Phase A + B1 + B2. Pure domain + ports + exceptions.
    Zero runtime impact.
  - **PR2** — Phases B3a, B3b, B4, B5, B6, B7, B8, B9 + **Phase C
    in full (including C6 fixture migration) + B10 anti-bypass
    guard**. Bundling B10 with C6 is non-negotiable: B10 rejects
    VET/ASV commands through `CreateClinicMembership`, and the
    existing `ClinicMembershipDataStory` dispatches 4–5 VET/ASV
    commands today — without the fixture migration, `make
    load-fixtures` (and therefore `make ci` integration tests)
    fails immediately on `master`. The `ClinicVeterinarianItem` DTO
    gains its new fields as **nullable** so the agenda keeps
    rendering during the window between PR2 and PR3.
  - **PR3** — Phase D (backoffice controllers + templates + voter)
    — **blocked on the D28 redesign** (see F2 audit finding). If
    the authN redesign grows beyond "tiny" in scope, Phase D is
    deferred to a follow-up spec; PR3 becomes the empty head of the
    chain.
  - **PR4** — Agenda template flip to the enriched path + flip
    `display_name` to `NOT NULL` once the fixtures + optional
    production backfill have run.
  Rationale: conforms to CLAUDE.md "one branch = one logical
  change", keeps reviews meaningful, and the nullable DTO fields
  neutralise any "broken window" between merges. Documented in
  Notes → "Merge Strategy".
  **Fallback contingency**: if any of these PRs balloons beyond
  review capacity, the alternative is a single PR — acceptable as
  a last resort.
- **D30 — Event publish semantics (requalified post-F7 adversarial
  review).** Messenger routing in this project splits into two
  event channels:
  - **Domain events** (`DomainEventInterface: sync` per
    `config/packages/messenger.yaml`). Published inside the command
    handler via `DomainEventPublisher::publish()` → synchronous
    bus dispatch → **inside** the `doctrine_transaction` middleware
    wrapping the handler. **At-least-once intra-BC**: if a sync
    handler throws, the whole transaction rolls back — writes and
    events move together. This part is verified.
  - **Integration events** (`IntegrationEventInterface: async`).
    Routed to the `async` transport (`doctrine://default` →
    `shared__messenger_messages` table). The exact commit timing
    of the transport insert vs. the outer `doctrine_transaction`
    commit is **not conclusively verified by reading alone**.
    Complicating factor: `config/packages/doctrine.yaml` enables
    `use_savepoints: true`, which makes nested `beginTransaction`
    calls use SAVEPOINTs. A RELEASE SAVEPOINT is not an independent
    COMMIT — it still depends on the outer transaction — so the
    transport insert most likely rolls back with the outer
    transaction (at-least-once cross-BC). But a subagent review
    concluded the opposite (at-most-once). **Both positions are
    plausible without a runtime test.**
  **v1 stance — TBD by runtime test, not by documentation**:
  before merging PR2, a small integration test must verify the
  actual behaviour — dispatch an `IntegrationEventInterface` from a
  command handler, force the handler to throw after the dispatch,
  observe whether a row landed in `shared__messenger_messages`.
  Outcomes:
  - **If at-least-once cross-BC (insert rolls back)** — the D30
    debt effectively vanishes. No outbox needed. Remove the
    "log-and-pray" language from this spec.
  - **If at-most-once cross-BC (insert commits independently)** —
    keep the current log-a-warning-on-publish-failure stance and
    defer the outbox pattern to its own dedicated spec. Manual
    retry via ad-hoc script.
  In **both** outcomes, D17b stays scoped to business consistency
  (credentials/role divergence) and is **not** extended to cover
  event delivery — those are distinct responsibilities.
- **D31 — Anti-bypass of `OnboardStaffMember`: runtime guard in
  `CreateClinicMembershipHandler`.** `CreateClinicMembership`
  remains available for non-practitioner roles, but the handler
  rejects commands whose `role` satisfies
  `VETERINARY`/`VETERINARY_ASSISTANT` by throwing
  `CannotCreatePractitionerMembershipWithoutProfile`. Chosen over a
  custom PHPStan rule: zero new tooling, strong runtime safety, and
  fixtures / backoffice / tests calling the bypass fail fast with a
  clear message pointing them to `OnboardStaffMember`. A
  lightweight architecture test is acceptable as a complement but
  not required.

### Step 2 Investigation Findings

Each Step 1 open item is resolved below; unresolved items carry an
explicit remaining action for Step 3.

1. **Table naming — RESOLVED (D25).** Custom naming strategy
   `BoundedContextPrefixNamingStrategy` auto-prefixes `clinic__` and
   pluralises. Target: `clinic__staff_profiles`. Existing concrete
   names confirmed: `clinic__clinics`, `clinic__clinic_groups`,
   `clinic__clinic_memberships`. No `name:` attribute on the new
   entity.
2. **`IdentityAccess.User` identity fields — RESOLVED.** The User
   aggregate (`src/System/IdentityAccess/Domain/User.php`) holds only
   `id`, `email`, `passwordHash`, `createdAt`, `status`,
   `emailVerifiedAt`, `type`. **No `firstName` / `lastName`.** D4
   (local identity storage) carries **zero divergence risk** — there
   is nothing to diverge from.
3. **Role naming — RESOLVED (D26).** Keep `VETERINARY`. Rename cost
   rejected.
4. **`ListClinicVeterinarians` blast radius — RESOLVED.** 7 files
   touch `ClinicVeterinarianItem` today:
   - Port: `ClinicMembershipReadRepositoryInterface.php`
   - DTO: `ClinicVeterinarianItem.php`
   - Handler: `ListClinicVeterinariansHandler.php`
   - Impl: `DoctrineClinicMembershipReadRepository.php` (method
     `findVeterinariansForClinic`)
   - Templates: `clinic/scheduling/agenda/index.html.twig`,
     `clinic/scheduling/_scheduling_aside.html.twig`
   - Tests: `ListClinicVeterinariansHandlerTest.php`,
     `ListClinicVeterinariansTest.php`
   The existing `findVeterinariansForClinic` JOIN currently returns
   only `userId/role/engagement`. Per D12+D20, split the data source:
   keep the membership-only query as-is, add a separate profile batch
   query in the new `DoctrineStaffProfileReadRepository`, compose in
   the handler. Templates switch to the enriched DTO fields.
5. **Fixtures strategy — RESOLVED (D27).** Foundry v2.8
   (`PersistentProxyObjectFactory`). Factories live under
   `fixtures/Context/<BC>/Factory/`. Dev clinics already seeded:
   `clinic-paris`, `clinic-lyon`. Add
   `fixtures/Context/Clinic/Factory/StaffProfileEntityFactory.php`
   mirroring `ClinicMembershipEntityFactory` (named states:
   `withDoctorTitle()`, `withRegistrationNumber(...)`, `asAssistant()`
   producing a profile with no credentials, etc.). Existing
   `ClinicMembershipDataStory` creates memberships via
   `CreateClinicMembership` commands — a new
   `ClinicStaffProfileDataStory` will dispatch `OnboardStaffMember`
   for VET/ASV roles and leave the others to the existing story.
6. **`CreateClinicMembership` fate — RESOLVED (D27).** Kept.
   Coexists with `OnboardStaffMember`. Full deprecation is a later
   cleanup once fixtures + backoffice fully migrate.
7. **`DisplayName` maxLength — RESOLVED. Locked at 60 characters.**
   Rationale: fits the agenda template layout
   (`_agenda.html.twig:80`, mono-ish cell ~18rem) and the
   appointment modal `<select>` option
   (`_modal_new_appointment.html.twig:127`). The Step 3 AC includes
   a visual regression check at 60-char names.
8. **Legacy memberships without a profile — REMAINING ACTION.** No
   blocker today (the feature is pre-prod, fixtures are reset with
   `make load-fixtures`). But the migration PR **must** include a
   pre-deployment checklist in its description: "(a) verify the
   staging/prod `clinic__clinic_memberships` count; if non-zero,
   run the backfill script; if zero, no action." Step 3 includes
   writing the optional backfill command as a safety net, invoked
   only if needed.
9. **i18n name composition — RESOLVED (assumption documented).** v1
   renders `displayName` as-is. Locale-aware formatting deferred to
   a future `NameFormatter` service. Documented in Scope.
10. **State-based testing of `StaffProfile` — RESOLVED.** Confirmed
    safe by `AggregateRoot`'s API: `recordedDomainEvents()` returns
    an empty array without clearing, so it never interferes with
    state-based tests. `StaffProfile` tests use getters +
    `hasVeterinaryCredentials()` only, no `pullDomainEvents()` calls.
11. **IdentityAccess email search API — RESOLVED (D24 + D24b).**
    `UserReadRepositoryInterface::listAll(?search, ?type, ?status)`
    supports email LIKE today. Expose to Clinic BC via a lean port +
    adapter (D24b). No changes required in IdentityAccess.
12. **Foundry factories inventory — RESOLVED.** Create:
    `StaffProfileEntityFactory`. Extend:
    `ClinicMembershipEntityFactory` with an `.withLinkedProfile(...)`
    helper **only if** it simplifies fixture composition; otherwise
    compose at the Story level. `VeterinaryCredentials` is an
    embedded VO, not an entity — no standalone factory needed, a
    trait/helper in `StaffProfileEntityFactory` is enough.

#### Step 3b — Transactional pattern verification (post-review)

Investigation run after the Round-3 party to resolve Amelia's
blocking concern (implementation-readiness of the transactional
wrapping in `OnboardStaffMemberHandler`).

- **`doctrine_transaction` middleware is wired on the command bus.**
  `config/packages/messenger.yaml` (bus `messenger.bus.command`)
  includes `doctrine_transaction` in its middleware chain.
  Consequence: every command handler dispatched via the bus is
  already wrapped in a Doctrine transaction — **no explicit
  `wrapInTransaction()` call is needed** in the handler. D18 and
  tasks B3a/B3b updated accordingly.
- **Domain events are sync intra-BC.** Messenger routing maps
  `App\Shared\Domain\Event\DomainEventInterface` to the `sync`
  transport. When `DomainEventPublisher::publish()` runs inside the
  handler, the sync dispatch happens inside the same transaction —
  any failure rolls back writes. **At-least-once intra-BC**.
- **Integration events are async cross-BC — commit timing
  unresolved (G5 reconciliation).** Messenger routing maps
  `App\Shared\Domain\Event\IntegrationEventInterface` to the
  `async` transport (`doctrine://default` →
  `shared__messenger_messages`). Whether the transport insert
  commits independently of the outer `doctrine_transaction`
  middleware (→ at-most-once cross-BC) or rolls back with it (→
  at-least-once) is **not conclusively determined by reading
  alone**, notably because `doctrine.yaml` enables
  `use_savepoints: true`. A runtime test (task B11, AC14b) will
  produce the verdict before PR2 merges. The earlier claim that
  "transport insert happens after the commit" was premature — D30
  now tracks this as TBD.
- **No existing `#[AsCommand]` precedent.** A `grep AsCommand src/`
  returns zero results. Our consistency command
  (`CheckRoleCredentialsConsistencyCommand`) is the first in the
  project. Location moved from
  `Application/Console/` to `Infrastructure/Console/` — more honest
  to DDD (Symfony commands are I/O adapters). B8 updated.
- **`DomainEventPublisher`** (`src/Shared/Application/Event/DomainEventPublisher.php`)
  is the canonical API: takes an `AggregateRoot`, pulls its events,
  dispatches them on the event bus. No modifications required.

## Implementation Plan

### Tasks

Tasks are ordered by dependency — lowest level first, no layer leaps.

#### Phase A — Domain layer (pure, no DB)

- [ ] **A1 — Extend `ClinicMemberRole` with capability methods.**
  - File: `src/Context/Clinic/Domain/Staff/ValueObject/ClinicMemberRole.php`
  - Action: add three public methods — `canHoldVeterinaryCredentials(): bool`,
    `canBePractitionerOfRecord(): bool`, `appearsInMedicalAgendaByDefault(): bool`.
    Each returns `match($this) { self::VETERINARY => true, default => false }`.
  - Notes: keep the enum string-backed, no case value change.

- [ ] **A2 — Create atomic VOs.**
  - Files (new):
    - `src/Context/Clinic/Domain/Staff/ValueObject/StaffProfileId.php`
    - `src/Context/Clinic/Domain/Staff/ValueObject/DisplayName.php`
    - `src/Context/Clinic/Domain/Staff/ValueObject/HexColor.php`
    - `src/Context/Clinic/Domain/Staff/ValueObject/ProfessionalRegistrationNumber.php`
    - `src/Context/Clinic/Domain/Staff/ValueObject/SignatureImageKey.php`
  - Action: mirror `src/Shared/Domain/ValueObject/PhoneNumber.php` style
    (`final readonly class`, private ctor, `fromString()`, `toString()`,
    `equals()`).
  - Validations:
    - `StaffProfileId` — UUID parse via `Symfony\Component\Uid\Uuid::fromString()`.
    - `DisplayName` — trim, non-empty, length 1..60.
    - `HexColor` — regex `/^#[0-9a-f]{6}$/` (stored lowercase).
    - `ProfessionalRegistrationNumber` — trim, non-empty, length 1..32.
    - `SignatureImageKey` — trim, non-empty, length ≤255, regex
      `/^[A-Za-z0-9_\-\.\/]+$/`, no `..` substring, no leading `/`.
      Storage-neutral opaque identifier — not a URL.

- [ ] **A3 — Create `ProfessionalTitle` enum.**
  - File: `src/Context/Clinic/Domain/Staff/ValueObject/ProfessionalTitle.php`
  - Action: `enum ProfessionalTitle: string { case DR = 'DR'; case PR = 'PR';
    case NONE = 'NONE'; }`. Non-nullable inside `VeterinaryCredentials`.

- [ ] **A4 — Create `VeterinaryCredentials` composite VO.**
  - File: `src/Context/Clinic/Domain/Staff/VeterinaryCredentials.php`
  - Action: `final readonly class` with ctor
    `(ProfessionalRegistrationNumber $registrationNumber,
    ProfessionalTitle $professionalTitle,
    ?SignatureImageKey $signatureImageKey)`. Expose getters.

- [ ] **A5 — Create `StaffProfile` aggregate.**
  - File: `src/Context/Clinic/Domain/Staff/StaffProfile.php`
  - Action: extends `App\Shared\Domain\Aggregate\AggregateRoot`. Private
    ctor, static
    `create(StaffProfileId, ClinicMembershipId, string $firstName,
    string $lastName, DisplayName, ?PhoneNumber, HexColor $agendaColor,
    int $sortOrder, bool $isVisibleInAgenda,
    \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): self`,
    static `reconstitute(...)` accepting the same state + optional
    credentials. **Both `createdAt` and `updatedAt` are first-class
    fields** (G2 correction — the migration column
    `created_at_utc` is NOT NULL, so the aggregate must carry the
    value; the handler populates both from `ClockInterface::now()`
    at creation, and `updatedAt` only on mutations).
  - Methods: `rename(string, string, DisplayName, \DateTimeImmutable $now)`,
    `changePhoneNumber(?PhoneNumber, \DateTimeImmutable $now)`,
    `updateAgendaPreferences(HexColor, int, bool, \DateTimeImmutable $now)`,
    `registerVeterinaryCredentials(VeterinaryCredentials, \DateTimeImmutable $now)`,
    `clearVeterinaryCredentials(\DateTimeImmutable $now)`,
    `hasVeterinaryCredentials(): bool`, getters for all state
    including `createdAt()` and `updatedAt()`.
  - **No `recordDomainEvent()` calls** (D14 — no events v1).

- [ ] **A6 — Define `StaffProfileRepositoryInterface`.**
  - File: `src/Context/Clinic/Domain/Staff/Repository/StaffProfileRepositoryInterface.php`
  - Action: methods `save(StaffProfile): void`,
    `findById(StaffProfileId): ?StaffProfile`,
    `findByMembershipId(ClinicMembershipId): ?StaffProfile`.

- [ ] **A7 — Unit tests — Domain.**
  - Files (new): one test class per VO + `VeterinaryCredentialsTest.php`
    + `StaffProfileTest.php` under `tests/Unit/Context/Clinic/Domain/Staff/`.
  - Action: mirror `ClinicSlugTest` (VO branches) and `ClinicTest`
    (aggregate behaviour). For `StaffProfileTest`, **state-based
    assertions only** — never call `pullDomainEvents()`.

- [ ] **A8 — `ClinicMemberRoleCapabilityCompletenessTest` (D16).**
  - File: `tests/Unit/Context/Clinic/Domain/Staff/ValueObject/ClinicMemberRoleCapabilityCompletenessTest.php`
  - Action: the class holds a private constant `EXPECTED_CAPABILITIES` —
    a map from each role value (`MANAGER`, `VETERINARY`,
    `VETERINARY_ASSISTANT`, `RECEPTIONIST`) to expected booleans for the
    three capability methods. `testEveryRoleIsSnapshot` asserts every
    `ClinicMemberRole::cases()` entry is present in the snapshot.
    `testCapabilitiesMatchSnapshot` asserts every actual return value
    equals the expected value.

#### Phase B — Application layer

- [ ] **B1 — Named domain exceptions.**
  - Files (new):
    - `src/Context/Clinic/Application/Exception/CannotRegisterCredentialsForNonVeterinarianRole.php`
    - `src/Context/Clinic/Application/Exception/CannotChangeRoleWhileVeterinaryCredentialsExist.php`
  - Action: mirror `DuplicateClinicSlugException.php` — `final class
    extends \RuntimeException`, constructor builds the message via
    `sprintf` with domain context (role value, membership id).

- [ ] **B2 — Ports + read DTOs.**
  - Files (new):
    - `src/Context/Clinic/Application/Port/StaffProfileReadRepositoryInterface.php`
      with `findByMembershipIds(array $membershipIds): array` returning
      `array<string, StaffProfileReadItem>` (membershipId → item),
      `findByMembershipId(string): ?StaffProfileReadItem`, and
      `hasVeterinaryCredentialsFor(ClinicMembershipId $membershipId): bool`
      (F13 — lightweight read predicate used by the role-change
      guard in B6 to avoid loading the full write aggregate just
      for a boolean check).
    - `src/Context/Clinic/Application/Port/StaffProfileReadItem.php` —
      plain DTO with flattened fields (displayName, professionalTitle,
      agendaColor, sortOrder, isVisibleInAgenda, plus userId for join).
    - `src/Context/Clinic/Application/Port/UserSearchForOnboardingInterface.php`
      with `searchByEmail(string $emailFragment, int $limit): array`
      returning `UserSearchResultItem[]`.
    - `src/Context/Clinic/Application/Port/UserSearchResultItem.php` —
      `{userId, email}`.

- [ ] **B3a — Command `OnboardStaffMember` (happy path, no credentials).**
  - Files (new):
    - `src/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMember.php`
    - `src/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandler.php`
  - Command fields: `clinicId`, `userId`, `role` (ClinicMemberRole —
    must be `VETERINARY` or `VETERINARY_ASSISTANT` for v1),
    `engagement`, `validFrom?`, `validUntil?`, `firstName`, `lastName`,
    `displayName`, `phone?`, `agendaColor`, `sortOrder`,
    `isVisibleInAgenda`.
  - Handler: **no explicit transaction wrapping** — the command bus's
    `doctrine_transaction` middleware wraps the whole `__invoke`
    automatically (D18 confirmed in Step 3b). Steps:
    (1) verify clinic, (2) `UserExistenceCheckerInterface::exists`,
    (3) guard role in {VET, ASV}, (4) check membership uniqueness,
    (5) create + save `ClinicMembership`, (6) create + save
    `StaffProfile` (no credentials yet).
    (7) `DomainEventPublisher::publish($membership)` — sync bus,
    fires inside the same transaction (D30). Returns the generated
    `StaffProfileId` (D19).
  - No credentials handling in this task — see B3b.

- [ ] **B3b — Extend `OnboardStaffMember` with embedded credentials.**
  - Files to modify:
    - `src/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMember.php`
      — add a nullable embedded credentials block
      (`registrationNumber`, `professionalTitle`,
      `signatureImageKey?`).
    - `src/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandler.php`
      — after step 6 of B3a, if credentials provided and
      `role->canHoldVeterinaryCredentials()`, call
      `$profile->registerVeterinaryCredentials(...)` + save **inside
      the same transaction**.
  - Rationale: splitting B3 keeps the first PR reviewable (≤200 LOC
    handler + tests) and makes the credentials path land with its
    own focused tests.

- [ ] **B4 — Profile update commands.**
  - Dirs (new, each with Command DTO + Handler):
    - `src/Context/Clinic/Application/Command/Staff/RenameStaffProfile/`
    - `src/Context/Clinic/Application/Command/Staff/UpdateStaffProfilePhone/`
    - `src/Context/Clinic/Application/Command/Staff/UpdateStaffProfileAgendaPreferences/`
  - Action: each handler loads the profile by `StaffProfileId`,
    calls the aggregate method, saves.

- [ ] **B5 — Credentials commands.**
  - Dirs (new):
    - `src/Context/Clinic/Application/Command/Staff/RegisterVeterinaryCredentials/`
    - `src/Context/Clinic/Application/Command/Staff/ClearVeterinaryCredentials/`
  - `RegisterVeterinaryCredentialsHandler` loads the membership **and**
    the profile, checks `role->canHoldVeterinaryCredentials()` — if
    false, throws `CannotRegisterCredentialsForNonVeterinarianRole`.
    Otherwise builds the `VeterinaryCredentials` VO and calls
    `$profile->registerVeterinaryCredentials(...)` + save (D6).
  - `ClearVeterinaryCredentialsHandler` loads the profile and
    unconditionally clears.

- [ ] **B6 — Modify `ChangeClinicMembershipRoleHandler` (D7 guard).**
  - File:
    `src/Context/Clinic/Application/Command/Staff/ChangeClinicMembershipRole/ChangeClinicMembershipRoleHandler.php`
  - Action: inject **`StaffProfileReadRepositoryInterface`** (read
    port, not the write repo — F13 correction). After loading the
    membership, call
    `$profileRead->hasVeterinaryCredentialsFor($membershipId)`. If
    `true && !$command->newRole->canHoldVeterinaryCredentials()`,
    throw `CannotChangeRoleWhileVeterinaryCredentialsExist`.
    Rationale: the guard is a pure read-side check; loading the
    write aggregate was architecturally inverted and risked an
    accidental UnitOfWork side effect.

- [ ] **B7 — Extend `ClinicVeterinarianItem` + rewrite handler (D12).**
  - Files to modify:
    - `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ClinicVeterinarianItem.php`
      — add `membershipId` (always present) and nullable display
      fields: `?string $displayName`, `?string $professionalTitle`,
      `?string $agendaColor`, `?int $sortOrder`, `?bool $isVisibleInAgenda`.
    - `src/Context/Clinic/Application/Port/ClinicMembershipReadRepositoryInterface.php`
      — update the return shape of `findVeterinariansForClinic` to
      carry `membershipId`.
    - `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineClinicMembershipReadRepository.php`
      — add `BIN_TO_UUID(m.id) AS membership_id` to the SELECT;
      propagate into the mapping. No JOIN on `clinic__staff_profiles`
      here.
    - `src/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinariansHandler.php`
      — inject the new `StaffProfileReadRepositoryInterface`. Execute:
      (1) get membership items, (2) extract membershipIds, (3) call
      `findByMembershipIds` batch, (4) zip into enriched DTOs. Missing
      profiles are tolerated — their display fields stay `null`.

- [ ] **B8 — Consistency console command (D17b).**
  - File (new):
    `src/Context/Clinic/Infrastructure/Console/CheckRoleCredentialsConsistencyCommand.php`
    (**location confirmed in Step 3b**: no existing `#[AsCommand]`
    precedent in `src/`, this command establishes the convention.
    `Infrastructure/Console/` chosen over `Application/Console/`
    because Symfony console commands are CLI I/O adapters —
    architecturally closer to infrastructure than to application
    use cases).
  - Action: Symfony `#[AsCommand(name:
    'app:clinic:staff:check-role-credentials-consistency')]`. Scans
    `clinic__staff_profiles` joined with `clinic__clinic_memberships`
    via DBAL, finds rows where the profile has non-null credentials
    but the membership's role fails `canHoldVeterinaryCredentials()`.
    For each offender: **writes to stdout** (machine-parseable one
    line per offender, format `membership_id staff_profile_id role`)
    **and** emits `LoggerInterface::warning`. If any offender is
    found, the command exits with code **1**; otherwise exit 0.
    Scope: **business consistency only** (credentials vs role
    divergence). Does **not** cover event-publish failure detection
    — that is a distinct infra concern (see D30).

- [ ] **B11 — Runtime test for Messenger/Doctrine transaction
  semantics (D30, G4 fix).** Gating task before PR2 merges.
  - File (new):
    `tests/Integration/System/Messenger/AsyncTransportTransactionalityTest.php`
  - Action: inside a `KernelTestCase`, dispatch a dummy command
    whose handler (a) performs a write on a real entity, (b)
    dispatches an `IntegrationEventInterface` via the event bus,
    and (c) throws `\RuntimeException('forced failure')` after
    the dispatch. Assert: the `shared__messenger_messages` table
    is empty after the exception bubbles up (at-least-once
    cross-BC) **or** non-empty (at-most-once cross-BC).
  - Outcome wiring: the test result **drives** the D30 write-up.
    If at-least-once, the spec drops the "log-and-pray" language
    and updates D30 + Notes accordingly. If at-most-once, D30
    stays as drafted and the corresponding debt gets a GitHub
    issue (F16).
  - This task is the material form of the "TBD by runtime test"
    clause in D30. Without it, the requalification is cosmetic.

- [ ] **B10 — Anti-bypass guard in `CreateClinicMembershipHandler`.**
  - File to modify:
    `src/Context/Clinic/Application/Command/Staff/CreateClinicMembership/CreateClinicMembershipHandler.php`
  - Action: add an early guard that refuses commands whose `role`
    satisfies `$role === ClinicMemberRole::VETERINARY` or
    `$role === ClinicMemberRole::VETERINARY_ASSISTANT`. Throw a new
    named exception
    `CannotCreatePractitionerMembershipWithoutProfile` (mirror
    `DuplicateClinicSlugException` style) whose message points the
    caller to `OnboardStaffMember`.
  - Files (new):
    - `src/Context/Clinic/Application/Exception/CannotCreatePractitionerMembershipWithoutProfile.php`
  - Rationale (D31): runtime guard instead of a static-analysis rule.
    No new tooling, strong compile-to-runtime safety, callers fail
    fast rather than silently creating profile-less VET/ASV
    memberships.
  - Fixture impact: any existing `ClinicMembershipDataStory` call
    with a VET/ASV role must migrate to `OnboardStaffMember` as part
    of Task C6 — otherwise `make load-fixtures` will fail after B10
    ships.

- [ ] **B9 — Unit tests — Application.**
  - Files (new or modified): one handler test per handler, plus the
    existing
    `tests/Unit/Context/Clinic/Application/Query/Staff/ListClinicVeterinarians/ListClinicVeterinariansHandlerTest.php`
    updated to exercise the composed read path with a fake
    `StaffProfileReadRepository`.
  - Pattern: direct `$handler($command)`, all infra mocked.

#### Phase C — Infrastructure

- [ ] **C1 — Doctrine entity + mapper + write repo.**
  - Files (new):
    - `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Entity/StaffProfileEntity.php`
      — `#[ORM\Entity]` + `#[ORM\Table]` (no explicit name, naming
      strategy resolves to `clinic__staff_profiles` per D25).
      Indexes: `#[ORM\UniqueConstraint(name: 'uniq_staff_profile_membership',
      columns: ['membership_id'])]`. Fields match the aggregate, plus
      nullable credentials columns
      (`registration_number`, `professional_title`,
      `signature_image_key`).
    - `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Mapper/StaffProfileMapper.php`
      — `toDomain(StaffProfileEntity): StaffProfile` + `toEntity(StaffProfile,
      ?StaffProfileEntity): StaffProfileEntity` (merge-or-create pattern
      for updates).
    - `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineStaffProfileRepository.php`
      — implements `StaffProfileRepositoryInterface`.

- [ ] **C2 — Doctrine read repo + batch query.**
  - File (new):
    `src/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineStaffProfileReadRepository.php`
  - Action: mirror `DoctrineClinicMembershipReadRepository`
    (DBAL `Connection` + `EntityManager` metadata for table-name
    resolution). Implement `findByMembershipIds(array $ids): array`
    with `WHERE membership_id IN (:ids)` using binary UUID bindings.
    Output: `array<membershipIdString, StaffProfileReadItem>`.

- [ ] **C3 — IdentityAccess search adapter.**
  - File (new):
    `src/Context/Clinic/Infrastructure/Adapter/IdentityAccess/IdentityAccessUserSearchAdapter.php`
  - Action: implements `UserSearchForOnboardingInterface`. Injects
    `App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface`.
    `searchByEmail($fragment, $limit)` delegates to
    `listAll($fragment, UserType::CLINIC->value, UserStatus::ACTIVE->value)`
    (G7 correction — port signature is `?string $type, ?string $status`,
    enum instances fail PHPStan max), iterates the returned
    `UserCollection` (via `->items()` or equivalent), truncates to
    `$limit`, maps each `UserListItem` to `UserSearchResultItem
    {userId, email}`.

- [ ] **C4 — Migration.**
  - File (new): `migrations/Clinic/Version<TIMESTAMP>.php`
  - Action: `CREATE TABLE clinic__staff_profiles` with `ENGINE=InnoDB`,
    `DEFAULT CHARSET=utf8mb4`, `COLLATE=utf8mb4_unicode_ci` (match
    existing Clinic migrations — verify with `make migrations-diff`
    before committing). Columns:
    - `id BINARY(16) NOT NULL` (PK)
    - `membership_id BINARY(16) NOT NULL` (UNIQUE, FK
      `clinic__clinic_memberships(id) ON DELETE RESTRICT`)
      — **RESTRICT not CASCADE** (F10 correction): D3 says new
      membership = new profile, so the profile carries credentials
      history that must survive membership deletion. CASCADE would
      silently erase registration numbers + signature keys without
      an audit trail. RESTRICT forces any future membership-delete
      flow to explicitly archive the profile first.
    - `created_at_utc DATETIME NOT NULL` (added per F10 —
      historical audit requires creation timestamp, not just
      update)
    - `first_name VARCHAR(80) NOT NULL`
    - `last_name VARCHAR(80) NOT NULL`
    - `display_name VARCHAR(60) NOT NULL`
    - `phone_number VARCHAR(32) NULL`
    - `agenda_color CHAR(7) NOT NULL`
    - `sort_order INT NOT NULL DEFAULT 0`
    - `is_visible_in_agenda TINYINT(1) NOT NULL DEFAULT 1`
    - `registration_number VARCHAR(32) NULL`
    - `professional_title VARCHAR(8) NULL` (stores `DR` / `PR` / `NONE`)
    - `signature_image_key VARCHAR(255) NULL` (D23 — 255 chosen to
      accommodate future object-storage keys, not 128)
    - `updated_at_utc DATETIME NOT NULL`
  - `down()` drops the table. Verify the table name is exactly
    `clinic__staff_profiles` via a `make migrations-diff` dry run
    before committing.

- [ ] **C5 — Foundry factory + membership factory hardening (F5).**
  - Files:
    - (new)
      `fixtures/Context/Clinic/Factory/StaffProfileEntityFactory.php`
    - (modified)
      `fixtures/Context/Clinic/Factory/ClinicMembershipEntityFactory.php`
  - `StaffProfileEntityFactory`: extends
    `PersistentProxyObjectFactory`, `class()` returns
    `StaffProfileEntity::class`, `defaults()` with `Uuid::v7()` +
    faker first/last/display names + random hex colour (from the
    restricted palette). Named states:
    `withVeterinaryCredentials(...)`, `asDoctor()`, `asProfessor()`,
    `withoutCredentials()`, `hiddenFromAgenda()`. Also a
    `forMembership(ClinicMembershipEntity|Proxy $m): self` helper
    to link via `membershipId`.
  - **Hardening `ClinicMembershipEntityFactory` (F5 — critical
    prerequisite of B10)**. The current `defaults()` picks a random
    role from `ClinicMemberRole::cases()` and persists the entity
    directly, bypassing the command bus and therefore bypassing
    B10's anti-bypass guard. Required changes:
    - Change the default role in `defaults()` from a random case to
      `ClinicMemberRole::RECEPTIONIST` (non-practitioner, safe).
    - Named states `asVeterinary()` / `asVeterinaryAssistant()`
      **auto-create a matching `StaffProfileEntity`** via the new
      factory using Foundry's `afterPersist` hook: after the
      membership entity persists, the hook calls
      `StaffProfileEntityFactory::createOne(['membershipId' => $proxy->getId(), ...])`.
      Without this hook, any test using
      `ClinicMembershipEntityFactory::new()->asVeterinary()->create()`
      would produce a profile-less practitioner membership and
      silently violate the D31 invariant (G10 lock — the earlier
      "sister-state" alternative is rejected in favour of the
      `afterPersist` approach, which leaves existing test
      call-sites untouched).

- [ ] **C6 — Extend dev fixtures.**
  - Files:
    - `fixtures/Context/Clinic/Story/ClinicStaffProfileDataStory.php` (new)
    - `fixtures/Dataset/ClinicDataset.php` (modified — wire the new
      story after `ClinicMembershipDataStory`; namespace
      `App\Fixtures\Dataset`)
  - Action: for `clinic-paris` and `clinic-lyon`, dispatch
    `OnboardStaffMember` for 2 VETs (with credentials) + 1 ASV (no
    credentials). Keep existing `ClinicMembershipDataStory` as-is for
    non-VET/ASV roles (D27).

- [ ] **C7 — Integration tests.**
  - Files (new):
    - `tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineStaffProfileRepositoryTest.php`
      — write-read round-trip, covers credentials-present and
      credentials-absent paths.
    - `tests/Integration/Context/Clinic/Infrastructure/Persistence/Doctrine/Repository/DoctrineStaffProfileReadRepositoryTest.php`
      — batch query returns correct map, empty-array input returns
      empty map.
    - `tests/Integration/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandlerTest.php`
      — happy path + **atomicity test**: stub the profile mapper to
      throw mid-flow and assert the membership is absent from the DB
      post-rollback (D18).
    - `tests/Integration/Context/Clinic/Infrastructure/Adapter/IdentityAccess/IdentityAccessUserSearchAdapterTest.php`
      — seeds a few IdentityAccess users, asserts the adapter returns
      only CLINIC + ACTIVE matches.

#### Phase D — Presentation (backoffice) — **DEFERRED pending D28 authN redesign (F2)**

> **STATUS: Not shippable in this spec's v1.**
> All D-tasks below are authored but blocked by the F2 audit
> finding on D28 (backoffice users do not carry a
> `ClinicMembership`, so the `ClinicManagerOnlyVoter` as specified
> cannot function). Ship Phase A+B+C without these. The tasks are
> preserved here as a **starting point for the follow-up spec**
> that will land the authN resolution chosen in D28.
> Task D4 (agenda + scheduling template updates) lives under the
> tenant app `src/Presentation/Clinic/`, NOT the backoffice — it
> is **not** subject to the F2 deferral and ships in **PR4** per
> D29.


- [ ] **D1 — Manager-only security gate (D28).**
  - File (new):
    `src/Presentation/Backoffice/Security/Voter/ClinicManagerOnlyVoter.php`
  - Action: votes `GRANTED` on attribute
    `BACKOFFICE_CLINIC_MANAGER` iff the authenticated user has an
    active (`ACTIVE`, within validity window) membership with role
    `MANAGER` in the clinic targeted by the current request (derived
    from `CurrentClinicContextInterface`). Deny otherwise.
  - Wire in `config/services.yaml` via the default autoconfiguration
    for voters.

- [ ] **D2 — Backoffice controllers (single-action, one route each).**
  - Dir: `src/Presentation/Backoffice/Controller/Clinic/Staff/`
  - Files (new, one per route):
    - `ListStaffProfilesController` — `GET /clinic/staff`
      (name `backoffice_clinic_staff_index`)
    - `ShowOnboardStaffFormController` — `GET /clinic/staff/new`
      (name `backoffice_clinic_staff_new`)
    - `HandleOnboardStaffSubmissionController` — `POST /clinic/staff/create`
      (name `backoffice_clinic_staff_create`)
    - `ShowEditStaffProfileFormController` — `GET /clinic/staff/{id}/edit`
      (name `backoffice_clinic_staff_edit`)
    - `HandleEditStaffProfileSubmissionController` — `POST /clinic/staff/{id}/update`
      (name `backoffice_clinic_staff_update`)
    - `HandleRegisterCredentialsController` — `POST /clinic/staff/{id}/credentials/register`
      (name `backoffice_clinic_staff_register_credentials`)
    - `HandleClearCredentialsController` — `POST /clinic/staff/{id}/credentials/clear`
      (name `backoffice_clinic_staff_clear_credentials`)
    - `SearchUsersForOnboardingController` — `GET /clinic/staff/search-users?q=…`
      (name `backoffice_clinic_staff_search_users`, returns JSON)
  - Action: every controller except `SearchUsers` uses
    `#[IsGranted('BACKOFFICE_CLINIC_MANAGER')]`. `SearchUsers` uses
    the same guard because it exposes IdentityAccess data. Forms
    hand-rolled from `$request->request`, CSRF via
    `CsrfTokenManagerInterface`, flash messages on success/failure.

- [ ] **D3 — Backoffice templates.**
  - Dir (new): `templates/backoffice/clinic/staff/`
  - Files (new):
    - `list.html.twig` — table (displayName, role, visibility,
      credentials badge) + empty-state CTA "Inviter un collaborateur"
      (strong default #5).
    - `_form.html.twig` — single-page form with conditional
      credentials block (strong default #3), using
      `components/ui/select.html.twig` for role and title selects,
      a Stimulus-driven email autocomplete wired to
      `backoffice_clinic_staff_search_users`, a restricted
      `agendaColor` swatch picker (strong default #1), numeric
      `sortOrder` input (strong default #2).
    - `new.html.twig`, `edit.html.twig` — extend
      `templates/backoffice/base.html.twig`, include `_form.html.twig`.
  - No hard-delete button (strong default #4 — deletion is via
    disabling the membership).

- [ ] **D4 — Agenda + scheduling template updates (F6 — override
  `practitionerLabel`).** The Scheduling BC's
  `GetAgendaForClinicDateRangeHandler` currently builds
  `appointment.practitionerLabel` via a cross-BC
  `LEFT JOIN identity_access__users` on email. Today the template
  falls back to that label: `{{ appointment.practitionerLabel ??
  appointment.practitionerUserId }}`. Simply injecting the new
  profile map without touching that line means **the enriched
  rendering never wins** — `practitionerLabel` (email) is always
  non-null. Required changes:
  - Files to modify:
    - `templates/clinic/scheduling/_agenda.html.twig:80` — change
      the priority. New render: if
      `practitionersByUserId[appointment.practitionerUserId]`
      exists, render
      `{{ practitioner.professionalTitlePrefix }}{{ practitioner.displayName }}`
      (where the prefix is `"Dr. "`, `"Pr. "`, or empty for
      `NONE`). Only if no profile entry exists, fall back to
      `appointment.practitionerLabel` (Scheduling's email), then
      `appointment.practitionerUserId` as a last resort.
    - `templates/clinic/scheduling/_scheduling_aside.html.twig` —
      same priority: profile map → Scheduling label → userId.
    - `templates/clinic/scheduling/_modal_new_appointment.html.twig:127`
      — `<select>` options render
      `{{ vet.professionalTitlePrefix }}{{ vet.displayName ?? vet.userId }}`
      (no reason to consult `practitionerLabel` here — the list
      comes from `ListClinicVeterinarians` directly).
  - File to modify:
    `src/Presentation/Clinic/Controller/Scheduling/Planning/AgendaController.php`
    — build the `practitionersByUserId` map from `$veterinarians`
    (the enriched DTO) and pass it to the template as a new
    variable. **Do not** attempt to modify the Scheduling BC's
    read query — that's out of scope and would violate the BC
    boundary.
  - **AC19 is updated** to assert *visible* effect: when a profile
    exists, `Dr. {displayName}` appears in the rendered HTML,
    **not** the email from `practitionerLabel`, **even when**
    `practitionerLabel` is non-null. This is the regression that
    must be detected.

#### Phase E — CI / regression

- [ ] **E1 — Run `make ci` and resolve failures without shortcuts.**
  - Action: no `--no-verify`, no `// phpstan-ignore-*` additions.
    Coverage on Clinic BC Domain + Application + Infrastructure stays
    at 100%.

### Acceptance Criteria

Given/When/Then. Happy paths, guards, edge cases and CI gates.

#### Domain

- [ ] **AC1 — VO rejection.** Given invalid input (empty, whitespace,
  over length, wrong charset, disallowed character), when any VO's
  `fromString()` is called, then `\InvalidArgumentException` is
  thrown with a descriptive message. Coverage: each VO has one test
  per validation branch.
- [ ] **AC2 — `SignatureImageKey` path-traversal guard.** Given input
  `"foo/../bar"` or `"/leading/slash"`, when
  `SignatureImageKey::fromString()` is called, then
  `\InvalidArgumentException` is thrown.
- [ ] **AC3 — `StaffProfile::registerVeterinaryCredentials` is
  reflected in state.** Given a freshly created profile, when
  `registerVeterinaryCredentials($creds)` is called, then
  `hasVeterinaryCredentials()` returns `true` and
  `credentials()` returns the VO.
- [ ] **AC4 — `StaffProfile::clearVeterinaryCredentials` is
  idempotent.** Given a profile without credentials, when
  `clearVeterinaryCredentials()` is called, then the state does not
  change and no exception is thrown. Given a profile with
  credentials, when it is called twice, then after the first call
  `hasVeterinaryCredentials()` returns `false` and the second call
  is a no-op.
- [ ] **AC5 — `ClinicMemberRole` capabilities match snapshot.** Given
  the snapshot in `ClinicMemberRoleCapabilityCompletenessTest`, when
  the test runs, then every `ClinicMemberRole::cases()` entry is
  present in the snapshot and every capability method returns the
  expected value. Adding a new role without updating the snapshot
  fails the test with a "missing case" message.

#### Application — commands

- [ ] **AC6 — `OnboardStaffMember` happy path (VET with credentials).**
  Given a valid clinic + user, when
  `OnboardStaffMemberHandler` is dispatched with
  `role = VETERINARY` + credentials, then a `ClinicMembership` is
  persisted, a `StaffProfile` with the provided credentials is
  persisted, the handler returns a `StaffProfileId`, and membership
  domain events are published.
- [ ] **AC7 — `OnboardStaffMember` happy path (ASV without
  credentials).** Given `role = VETERINARY_ASSISTANT`, when the
  handler runs, then a profile is created with `credentials === null`.
- [ ] **AC8 — `OnboardStaffMember` rejects non-VET/ASV roles.** Given
  `role = MANAGER` or `RECEPTIONIST` in the command, when the
  handler runs, then `\InvalidArgumentException` is thrown and
  nothing is persisted.
- [ ] **AC9 — `OnboardStaffMember` atomicity (D18).** Given a forced
  failure in `StaffProfileRepositoryInterface::save` (via a test
  decorator — see below), when `OnboardStaffMemberHandler` runs,
  then **neither** the membership **nor** the profile is present
  in the DB after the exception bubbles up (DAMA rollback still
  isolates other tests).
  Concrete wiring (F9 + G9 correction — neither
  `tests/Support/Doubles/` nor `config/services_test.yaml` exist
  in the repo; do not invent cross-cutting infrastructure).
  Ship the double inline with the test:
  - Class `ThrowingStaffProfileRepository` as a private `final
    class` at the bottom of the test file
    (`tests/Integration/Context/Clinic/Application/Command/Staff/OnboardStaffMember/OnboardStaffMemberHandlerTest.php`),
    implementing `StaffProfileRepositoryInterface` with `save()`
    throwing `\RuntimeException('forced failure for AC9')` and
    every other method throwing
    `\LogicException('not used in this test')`.
  - In `setUp()`, call
    `self::getContainer()->set(StaffProfileRepositoryInterface::class, new ThrowingStaffProfileRepository())`.
    This is Symfony's supported test-only container override —
    no new YAML, no new directory conventions.
  - DAMA's transaction rollback is orthogonal to this override
    and operates normally.
- [ ] **AC10 — `RegisterVeterinaryCredentials` guard (D6).** Given a
  membership with `role = VETERINARY_ASSISTANT`, when
  `RegisterVeterinaryCredentialsHandler` runs, then
  `CannotRegisterCredentialsForNonVeterinarianRole` is thrown and
  the profile state is unchanged.
- [ ] **AC11 — `ChangeClinicMembershipRole` guard (D7).** Given a
  profile with existing credentials and a command setting a role
  that fails `canHoldVeterinaryCredentials()`, when the handler
  runs, then `CannotChangeRoleWhileVeterinaryCredentialsExist` is
  thrown. Given the same setup with the credentials cleared first,
  the role change succeeds.
- [ ] **AC11b — `CreateClinicMembership` anti-bypass (D31).** Given
  a `CreateClinicMembership` command whose role is `VETERINARY` or
  `VETERINARY_ASSISTANT`, when the handler runs, then
  `CannotCreatePractitionerMembershipWithoutProfile` is thrown and
  no membership is persisted. Given the same command with a role of
  `MANAGER` or `RECEPTIONIST`, the handler succeeds unchanged.
- [ ] **AC11c — Cross-BC credentials data readiness (reformulated
  Problem Statement).** Given a VET profile with
  `registrationNumber = "FR-12345"` and
  `professionalTitle = DR`, when a consumer outside the Clinic BC
  calls `StaffProfileReadRepositoryInterface::findByMembershipId`,
  then both values are returned in the `StaffProfileReadItem` DTO.
  This AC proves the data is **available** for future prescription
  flows — it does not prove the flows themselves (out of scope v1).

#### Application — queries

- [ ] **AC12 — Enriched `ListClinicVeterinarians` returns display
  data.** Given 3 `VETERINARY` memberships each with a linked
  profile, when the query runs, then 3 items are returned each
  carrying `displayName`, `professionalTitle`, `agendaColor`,
  `sortOrder`, `isVisibleInAgenda`.
- [ ] **AC13 — No N+1 (D20b), batch-only read.** Given 50 veterinary
  memberships in a clinic, when `ListClinicVeterinariansHandler`
  runs, then **no SQL query is issued per individual membership**
  (i.e. the profile fetch is strictly a single batched query, not
  a loop). Asserted via a DBAL middleware or counter that tracks
  only *data* queries matching `SELECT ... FROM clinic__staff_profiles
  ...`; the assertion tolerates DAMA savepoints and setup queries.
  Reject any implementation that issues ≥ 2 profile SELECTs.
- [ ] **AC14 — Missing profile tolerated.** Given 2 memberships of
  which only 1 has a profile, when the query runs, then both items
  are returned; the item without a profile has `displayName === null`.

#### Infrastructure — consistency

- [ ] **AC14b — Runtime Messenger transactionality test records a
  verdict (B11, G4 fix).** Given the test dispatched per B11,
  when it runs, then it asserts either the at-least-once outcome
  (empty `shared__messenger_messages` after failure) or the
  at-most-once outcome (row present after failure), and **the
  test stores its verdict in the test output** (e.g. via a
  `self::markTestIncomplete` with a diagnostic message) so the
  reviewer can reconcile D30 with the observed behaviour before
  approving PR2.

- [ ] **AC15 — Consistency command reports offenders (D17b).** Given
  a profile with credentials linked to a membership whose role fails
  `canHoldVeterinaryCredentials()`, when
  `app:clinic:staff:check-role-credentials-consistency` runs, then
  (a) one machine-parseable line per offender is written to stdout,
  (b) a `LoggerInterface::warning` is emitted per offender, and
  (c) the command exits with code **1**. Given a clean dataset,
  nothing is written to stdout, no warnings are logged, and the
  command exits with code **0**.

#### Infrastructure — adapter

- [ ] **AC16 — IdentityAccess search adapter.** Given IdentityAccess
  users "alice@clinic.com" (CLINIC, ACTIVE), "bob@portal.com"
  (PORTAL, ACTIVE), "carol@clinic.com" (CLINIC, SUSPENDED), when
  `UserSearchForOnboardingInterface::searchByEmail('c', 10)` is
  called, then **only** "alice@clinic.com" is returned (bob filtered
  by type, carol filtered by status).

#### Backoffice / UI — **mixed: AC19 + AC20 ship (tenant app), AC17/AC18/AC21/AC21b/AC21c/AC22 DEFERRED (F2)**

> **AC19** (agenda label) and **AC20** (60-char display name,
> layout) belong to the tenant-app agenda, not the backoffice, and
> ship with PR4 per D29. All other ACs in this block are gated
> behind the D28 authN redesign and move to the follow-up spec.

- [ ] **AC17 — [DEFERRED F2] Manager-only access (D28).** Given a backoffice user
  whose active membership role is **not** MANAGER, when any
  `/clinic/staff/*` route is requested, then the response is HTTP
  403.
- [ ] **AC18 — [DEFERRED F2] Happy onboarding flow.** Given a MANAGER and a valid
  form submission, when
  `POST /clinic/staff/create` runs, then the user is redirected to
  `backoffice_clinic_staff_edit` with the newly generated profile
  id and a flash `success` message is shown.
- [ ] **AC19 — Agenda displays the right label, even over the
  Scheduling-built `practitionerLabel` (F6).** Given an
  appointment whose practitioner has a profile (`displayName =
  "Rousseau"`, `professionalTitle = DR`) **and** whose Scheduling
  BC `appointment.practitionerLabel` is non-null (the pre-spec
  email fallback), when the agenda template renders, then
  `"Dr. Rousseau"` appears in the DOM — and the email string from
  `practitionerLabel` does **not**. This AC is the regression
  gate that proves the template priority is correctly inverted.
  Given an appointment whose practitioner has no profile yet, then
  the template falls back to `practitionerLabel` (email), then to
  the raw userId as a last resort — matching the pre-spec behaviour
  exactly.
- [ ] **AC20 — 60-char display name does not break layout.** Given a
  `displayName` of exactly 60 characters, when the agenda and the
  appointment modal render, then no layout overflow is visible
  (manual visual check required).
- [ ] **AC21 — [DEFERRED F2] Autocomplete goes through the Clinic port (D24b).**
  Given a manager typing in the onboarding form email autocomplete,
  when the Stimulus controller calls
  `GET /clinic/staff/search-users?q=ali`, then the backend invokes
  `UserSearchForOnboardingInterface::searchByEmail` (not a direct
  IdentityAccess read repo call from the Presentation layer).
- [ ] **AC21b — [DEFERRED F2] Search endpoint hardening (F8).** Given a
  `GET /clinic/staff/search-users?q=<fragment>` request:
  - If `strlen(trim($fragment)) < 3`, the endpoint returns HTTP 400
    with a JSON error body; no call to the port is made.
  - The `limit` is capped at **10** results server-side; any `limit`
    query parameter is ignored or clamped.
  - Response shape is a stable JSON array of
    `{ "userId": "<uuid>", "email": "<email>" }` objects — no
    nested envelope.
  - The endpoint is protected by the same voter as the onboarding
    flow (no anonymous enumeration).
  - Stimulus controller (front) debounces at 200ms before calling
    the endpoint.
- [ ] **AC21c — [DEFERRED F2] User-facing strings are French (F11).** Given the
  backoffice surface shipped by Phase D, every user-facing string
  introduced by this spec is written in French. Covered items
  (non-exhaustive, add to the test matrix as the UI WIP ships):
  - List page column headers (name, role, visibility,
    credentials badge label)
  - Empty-state CTA ("Inviter votre premier collaborateur")
  - Form field labels and placeholders
  - Flash success / error messages
  - Error messages from named domain exceptions
    (`CannotRegisterCredentialsForNonVeterinarianRole`,
    `CannotChangeRoleWhileVeterinaryCredentialsExist`,
    `CannotCreatePractitionerMembershipWithoutProfile`) — the
    exception messages themselves stay English (CLAUDE.md: code
    comments/messages English; only user-facing strings French),
    but the handler-to-UI translation layer (flash / JSON error
    body) must present a French text.
  - Professional title prefix rendered on the agenda — the display
    `Dr.` / `Pr.` is acceptable as-is (these are language-neutral
    honorific abbreviations). `NONE` renders as no prefix, not a
    literal "NONE" string.
- [ ] **AC22 — [DEFERRED F2] Empty state CTA.** Given a clinic with zero staff
  profiles, when `GET /clinic/staff` renders, then a "Inviter votre
  premier collaborateur" CTA is visible (strong default #5).
  Rationale: the onboarding form covers VET **and** ASV; per the
  glossary, ASV is not a "praticien" — "collaborateur" is the
  umbrella term.

#### CI / quality

- [ ] **AC23 — `make ci` passes.** Given the final commit, when
  `make ci` runs, then php-cs-fixer (dry-run), phpcs, phpstan
  (level max, no added `phpstan-ignore`), tailwind-build and **all**
  tests pass with no deprecations / notices / warnings. Line
  coverage of the Clinic BC Domain + Application + Infrastructure
  is 100%.

## Additional Context

### Dependencies

- **No new Composer packages.** Everything reuses the existing stack
  (Symfony Messenger, Doctrine ORM+DBAL, Foundry v2.8, DAMA
  DoctrineTestBundle, PHPUnit, Tailwind v4, Stimulus).
- **Internal code reuse:**
  - `App\Shared\Domain\Aggregate\AggregateRoot`
  - `App\Shared\Domain\Time\ClockInterface`
  - `App\Shared\Domain\Identifier\UuidGeneratorInterface`
  - `App\Shared\Application\Event\DomainEventPublisher`
  - `App\Shared\Application\Bus\CommandInterface` and
    `QueryBusInterface`
  - `App\Context\Clinic\Application\Port\UserExistenceCheckerInterface`
    (already satisfied by `DbalUserExistenceChecker`)
  - `App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface`
    — consumed by the new `IdentityAccessUserSearchAdapter` (C3)
- **No cross-BC entity imports.** The new code uses `UserId` (a Clinic
  BC VO, not the IdentityAccess aggregate) and goes through the D24b
  port for email lookup.
- **Upstream blockers — none.** All pre-requisites exist in the
  codebase at time of writing.
- **Downstream consumers unblocked by this spec**:
  - Future "Electronic Prescriptions" feature — can now read
    practitioner display name + registration number + professional
    title from the profile.
  - Future "Self-service My Profile" — domain + commands already
    in place; only a new Presentation surface + voter changes needed.
- **Make targets used**:
  - `make assets` — to rebuild Tailwind/Twig assets after template
    edits (never `php bin/console asset-map:compile`, per
    CLAUDE.md).
  - `make migrations-diff` + `make migrate-db` — migration workflow.
  - `make load-fixtures` — re-seed dev data after extending the
    fixture story.
  - `make ci` — enforce CLAUDE.md gate before commit.

### Testing Strategy

**Targets:** 100% line coverage on Clinic BC Domain + Application +
Infrastructure (project non-negotiable). Presentation layer is
manually tested as per convention.

**Unit tests (fast, no DB):**

- **VOs** — one test class per VO under
  `tests/Unit/Context/Clinic/Domain/Staff/ValueObject/`. Pattern:
  `testFromStringWith*` (valid inputs) + one `testFromStringRejects*`
  per invalid branch. Use `expectException(\InvalidArgumentException::class)`
  for rejections.
- **Composite VO** — `VeterinaryCredentialsTest` covers construction
  with and without `signatureImageKey`, equality, getters.
- **`StaffProfile` aggregate** — state-based assertions only. Test
  each public method (`rename`, `changePhoneNumber`,
  `updateAgendaPreferences`, `registerVeterinaryCredentials`,
  `clearVeterinaryCredentials`, idempotency of clear). **Do not**
  call `pullDomainEvents()` — D14.
- **Completeness test (D16)** —
  `ClinicMemberRoleCapabilityCompletenessTest` drives snapshot
  assertions per case.
- **Handlers** — direct `$handler($command)` invocation. Infra
  doubled: `StaffProfileRepositoryInterface` as an in-memory fake
  (simple array), `ClinicMembershipRepositoryInterface` fake,
  `UserExistenceCheckerInterface` stub, `UuidGeneratorInterface`
  returning a fixed UUID, `ClockInterface` returning a frozen time,
  `DomainEventPublisher` spy. One test file per handler under
  `tests/Unit/Context/Clinic/Application/Command/Staff/<UseCase>/`.
- **Exceptions** — smoke tests asserting the message includes the
  relevant context (membership id, role value).

**Integration tests (`tests/Integration/...`):**

- **`DoctrineStaffProfileRepositoryTest`** — write via the aggregate,
  read back, compare state. Cover both credentials-present and
  credentials-absent paths.
- **`DoctrineStaffProfileReadRepositoryTest`** — `findByMembershipIds`
  batch query with 0, 1, many input ids. Assert output map keys are
  stringified membershipIds.
- **`OnboardStaffMemberHandlerTest`** — routed through a real
  `EntityManagerInterface` with DAMA rollback. Covers:
  - Happy path VET + credentials (AC6)
  - Happy path ASV no credentials (AC7)
  - Rejection MANAGER/RECEPTIONIST (AC8)
  - **Atomicity (AC9)**: override the `StaffProfileRepositoryInterface`
    binding with a decorator that throws on `save`, assert
    `clinic__clinic_memberships` has no new row post-exception.
- **`IdentityAccessUserSearchAdapterTest`** — seeds users of mixed
  types/statuses via the IdentityAccess factory, invokes
  `searchByEmail`, asserts filtering (AC16).
- **`ListClinicVeterinariansHandlerTest`** (updated) — covers
  enrichment + the missing-profile tolerance (AC12 + AC14). Query
  count assertion for N+1 detection (AC13) uses the DBAL logger.
- **`ChangeClinicMembershipRoleHandlerTest`** — covers the D7 guard
  (AC11), both refused and accepted paths.
- **`CheckRoleCredentialsConsistencyCommandTest`** — seeds an
  offender, runs the command, captures the logger output, asserts
  exit code 1 (AC15).

**Manual test steps (pre-merge, one-shot per PR):**

1. `make load-fixtures` — reseed.
2. Log in to `backoffice.kiveto.local` as a manager of `clinic-paris`.
3. Visit `/clinic/staff` — expect a list with 2 VETs + 1 ASV (from
   fixtures).
4. Click "Inviter un collaborateur", type "al" in the email autocomplete,
   expect existing IdentityAccess users to show.
5. Complete the form with role VET + credentials, submit — expect
   redirect to edit with success flash.
6. Visit `/scheduling/agenda` — expect `Dr. <displayName>` rows, not
   raw userIds.
7. Back in `/clinic/staff`, change role from VET to ASV — expect a
   failure message ("Impossible de changer le rôle tant que des
   credentials sont enregistrés").
8. Clear credentials via the dedicated action, retry role change —
   expect success.
9. (Optional) Create a long `displayName` (60 chars) via edit, revisit
   the agenda — expect no layout overflow.
10. Run `bin/console app:clinic:staff:check-role-credentials-consistency`
    — expect exit 0 and no warnings in logs.
11. Log out, log back in as a **non-manager** (a VET user), visit any
    `/clinic/staff/*` URL — expect HTTP 403.

Manual tests are **not** a substitute for ACs; they are a sanity
pass before merging.

### Notes

- **Ubiquitous language split**:
  - `ClinicMembership` = authorization (role, status, validity window).
  - `StaffProfile` = identity and display data scoped to a clinic.
  - `ClinicMemberRole` = permissions bucket, not a label.
- **Glossary (terminology anchors)**:
  - **staff member** — the person-to-clinic link (i.e. a
    `ClinicMembership`, independent of role).
  - **practitioner** — a staff member whose role satisfies
    `canBePractitionerOfRecord()` (today: `VETERINARY` only).
  - **veterinarian** — a practitioner who additionally holds
    `VeterinaryCredentials`.
  - **member** — generic alias for staff member.
  - **`SignatureImageKey`** — opaque, storage-neutral identifier.
    Not a URL.
- **Merge Strategy (D29) — authoritative plan** (aligned with the
  F1 + F2 corrections; if this section and D29 diverge, **D29
  wins** and this summary must be reconciled to match):
  1. **PR1** — Phase A + B1 + B2. Pure domain / ports / exceptions.
     Zero runtime impact.
  2. **PR2** — Phases B3a, B3b, B4, B5, B6, B7, B8, B9, B10 **+
     Phase C in full (C1–C7) including C6 fixture migration** + the
     B11 runtime Messenger test (see below). B10 and C6 are
     bundled together because B10's anti-bypass guard would break
     `make load-fixtures` if shipped without the fixture
     migration. The `ClinicVeterinarianItem` DTO fields ship as
     **nullable**; the agenda template keeps its pre-spec priority
     (Scheduling `practitionerLabel` wins) until PR4. Column
     `display_name` is created `NOT NULL` from the start (fixtures
     always provide a value). Legacy memberships from other
     clinics keep rendering their email fallback in prod between
     PR2 and PR4.
  3. **PR3** — **DEFERRED pending D28 authN redesign (F2 audit).**
     Phase D (backoffice UI + voter) cannot ship as originally
     drafted because the backoffice user model has no
     `ClinicMembership` path. PR3 is therefore either: (a) empty
     and dropped from this feature's chain, with Phase D handed
     off to a follow-up spec, or (b) re-scoped once one of the
     three resolution paths in D28 is picked. v1 of this spec
     does **not** ship backoffice staff-profile UI.
  4. **PR4** — Agenda template priority flip (D4), consuming the
     enriched `practitionersByUserId` map + optional prod backfill
     for legacy memberships. This is the PR that delivers the
     visible "Dr. Rousseau" effect (AC19).
  Between PR2 and PR4, the enrichment is **available but not
  rendered on the agenda** — AC19 deliberately lives at the end of
  the chain.
- **Post-merge runbook (PR4)**: after PR4 lands on `master`, run
  `make load-fixtures` locally, and in prod run the optional
  backfill command (see investigation item #8) for any legacy
  membership that predates the fixture seeding. Without backfill,
  the agenda keeps showing Scheduling's email label for legacy
  rows until profiles are created one-by-one — once Phase D
  unblocks, that will be through the backoffice UI; meanwhile,
  console commands or direct DB seeding is the only path.
- **Event and consistency monitoring ownership (D17b + D30)** —
  neither the consistency command nor the event-publish warnings
  have a wired monitoring destination today. Interim: developers
  watch `make load-fixtures` output locally; production will wire
  the command's non-zero exit into the monitoring stack when it
  lands. **Destinataire final à nommer quand stack monitoring prod
  en place** — not a v1 blocker.
- **Event publish semantics (D30)** — TBD by runtime test before
  PR2 merge. Two possible outcomes documented in D30 itself; the
  spec behaviour diverges based on the test result. D17b stays
  scoped to business consistency.
- **Known v1 debt — external issue tracking (F16)**. This spec is
  currently the only home for the accepted debts D17, D21, D30.
  Before PR1 merges, create linked tracking issues (GitHub /
  project tracker) — one per debt — and reference their IDs here:
  - D17 (concurrency race role-change) — issue ID: **TBD**
  - D21 (manager self-role-change) — issue ID: **TBD**
  - D30 (event publish semantics) — issue ID: **TBD**, created
    after the runtime test lands (the test outcome determines
    whether this debt actually exists).
  Without this external tracking, the debts vanish from the team's
  radar the moment the PRs merge.
- **Principle extracted from F6 (carried forward to future specs)**:
  *always verify the downstream visible path*. A perfectly
  designed domain layer is worthless if the final rendered UI
  still uses a pre-spec fallback. For any spec that claims a UX
  effect ("Dr. Rousseau appears instead of the email"), trace the
  exact template line that produces the effect, confirm that the
  new code path wins over every existing fallback, and write the
  AC as the visible DOM change — not as the data being available
  somewhere upstream.
- **Bonus caveat on backoffice patterns** — the existing
  `src/Presentation/Backoffice/Controller/ClinicController.php`
  hosts 6 routes in a single class, violating the CLAUDE.md
  "single-action controllers only" rule. Do **not** imitate it.
  The new staff profile controllers (when Phase D unblocks — see
  D28) must be single-action each.
- **Known v1 debts (non-blocking, tracked)**:
  - Role-change concurrency race (see D17). Detected by the periodic
    consistency command (D17b), not by read-time logging.
  - Manager self-role-change to `VETERINARY` (see D21).
  Both are called out so they can be picked up in a dedicated
  hardening iteration rather than rediscovered in production.
- **UX decisions deferred to the Step-3 UI WIP**: these are out of
  scope for the current technical spec but must be tranché before
  any template is touched. A dedicated UI WIP in Step 3 owns them.
  1. `agendaColor` picker — restricted WCAG-validated palette (8–12
     colours) vs free hex input. Strong default: restricted palette.
  2. `sortOrder` editor — numeric input (cheap, v1) vs drag-and-drop
     on the list (richer, v2). Strong default: numeric input v1.
  3. `OnboardStaffMember` form shape — single page with a credentials
     section that unfolds conditionally on
     `role.canHoldVeterinaryCredentials()`, vs multi-step wizard.
     Strong default: single page.
  4. Delete semantics — no hard delete of `StaffProfile` v1. A
     departing member disables the `ClinicMembership` (existing flow)
     and the profile stays as history.
  5. Empty states — new clinic with zero staff: explicit CTA
     ("Inviter votre premier collaborateur") rather than a neutral empty
     table.
  6. User selection in the onboarding form — wired to the D24
     decision (autocomplete on existing users, upgradable per
     investigation item #11).

- **High-risk items (watch list for Step-4 review)**:
  - **Atomicity of `OnboardStaffMember` (D18 + AC9)** — the
    failure-injection integration test is the load-bearing
    assertion; a false green here means orphan memberships in
    production. Do not approximate it with a unit test.
  - **No N+1 regression (D20b + AC13)** — the DBAL query-count
    assertion is the only signal that the read path stays at
    2 SQL statements regardless of clinic size.
  - **Manager-only voter (D28 + AC17)** — the backoffice firewall
    today only enforces `IS_AUTHENTICATED_FULLY`. Shipping D2
    without D1 would expose the routes to any authenticated
    backoffice user. D1 must land before D2 even on feature
    branches.
  - **Naming strategy dry-run (D25)** — a naming-strategy drift
    would silently create a `staff_profile` table instead of
    `clinic__staff_profiles`, and the JOIN adapter would fail at
    runtime. Always run `make migrations-diff` and inspect the
    generated SQL before committing the migration (C4).
  - **Credentials integrity guard (D6 + D7 + AC10 + AC11)** — the
    two application-layer guards are the only runtime defence of
    the "only VET holds credentials" invariant. Skipping either
    of their integration tests invalidates the acceptance of
    D17/D21 as tracked debts.

- **Known limitations carried into v1**:
  - Concurrency race on role-change (D17) — mitigated by the
    consistency command (D17b), not by locking.
  - Manager self-role-change (D21) — accepted dette.
  - No self-service "My profile" — managed-only edits (D15).
  - No delete of profiles — disabling the membership is the
    removal path (UX strong default #4).
  - No hard validation of foreign registration numbers — only a
    trim + length guard (D10).

- **Future considerations (not in scope, not blocking)**:
  - Add `registrationCountry` + `registrationAuthority` when
    multi-country prescriptions arrive.
  - Promote domain events on `StaffProfile` when a real consumer
    materializes (e.g., AccessControl projection or audit log).
  - Introduce a dedicated `GetPractitionerAgendaView` read model
    if the enriched `ClinicVeterinarianItem` outgrows the current
    DTO shape.
  - Replace numeric `sortOrder` with drag-and-drop once the
    backoffice has Stimulus components for it.
  - Build the "Invite user" flow inside IdentityAccess to upgrade
    D24 from "autocomplete existing" to "create-on-the-fly".
