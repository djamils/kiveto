---
title: 'Patient, Admission & Regulatory — Unidentified Animal Intake Domain Model'
slug: 'patient-admission-regulatory-unidentified-intake'
created: '2026-04-26'
status: 'implementation-complete'
stepsCompleted: [1, 2, 3, 4, 5]
tech_stack:
  - 'PHP 8.5 / Symfony 7.4'
  - 'Doctrine ORM ^3.5 (BINARY(16) UUIDv7, @Version optimistic lock)'
  - 'Symfony Messenger (CommandBus, QueryBus, EventBus, IntegrationEventBus)'
  - 'Zenstruck Foundry ^2.8'
  - 'PHPStan level max'
  - 'Twig + Turbo Frames/Streams + Stimulus'
files_to_modify:
  - 'src/Context/Animal/ — ADD AnimalNameChanged domain event + @Version'
  - 'src/Context/Consultation/ — REFACTOR: patientId (non-nullable), admissionId (non-nullable), remove animalId + ownerId'
  - 'src/Context/Scheduling/ — REMOVE WaitingRoomEntry aggregate, Repository, mapping, fixtures'
  - 'src/Context/Patient/ — CREATE new BC'
  - 'src/Context/Admission/ — CREATE new BC'
  - 'src/Context/Regulatory/ — CREATE new BC'
  - 'src/Presentation/Clinic/ — UPDATE waiting room view to query Admission read-model'
code_patterns:
  - 'AggregateRoot with recordDomainEvent() + pullDomainEvents()'
  - 'DomainEventPublisher::publish($agg) AFTER repo.save()'
  - 'IntegrationEventPublisher for cross-BC events (async, doctrine transport)'
  - 'Mapper final readonly class: Domain ↔ DoctrineEntity symmetry'
  - 'BoundedContextPrefixNamingStrategy: patient__patients, admission__admissions'
  - 'UuidGeneratorInterface::generate() for all IDs — never Uuid::v7() directly'
  - 'ClockInterface for all DateTimeImmutable — never new \DateTimeImmutable()'
  - '@Version int on Patient, Animal, Admission aggregates (optimistic lock)'
test_patterns:
  - 'Unit: aggregate invariants + pullDomainEvents() assertions'
  - 'Unit: command handler with mocked repos/ports'
  - 'Integration: Doctrine repository with Foundry factory + KernelTestCase'
  - 'Unit: Mapper symmetry toDomain(toEntity($agg)) === original'
---

# Tech-Spec: Patient, Admission & Regulatory — Unidentified Animal Intake

**Created:** 2026-04-26

---

## Overview

### Problem Statement

VetSaaS currently has an Animal-centric model: `Animal BC` (owner always required) and `Consultation BC` (references `?AnimalId`, `?OwnerId`, `?WaitingRoomEntryId`). This model cannot handle:

1. Emergency intake of an unidentified animal (no known owner, no chip)
2. A patient medical identity independent of administrative ownership
3. French legal obligations triggered at intake (mairie notification T+48h, legal custody 8 working days)
4. Patient reconciliation when identity is discovered post-intake
5. A rich admission lifecycle (triage escalation, intake channel, location tracking, multiple closure reasons)
6. The `WaitingRoomEntry` concept in Scheduling is a stub covering only the queue slot — it has no lifecycle, no presenter, no triage, no closure semantics

### Solution

Three new Bounded Contexts at `src/Context/` level (siblings of existing BCs), plus targeted modifications to Animal and Consultation:

- **Patient BC** (`src/Context/Patient/`) — `Patient` aggregate: central medical identity, can exist without Animal (emergency), carries DisplayLabel and observational data
- **Admission BC** (`src/Context/Admission/`) — `Admission` aggregate: replaces `WaitingRoomEntry`, covers all intake channels, full lifecycle Active → Closed(reason)
- **Regulatory BC** (`src/Context/Regulatory/`) — `MairieNotification`, `StrayCustody`, `ICADLookup` aggregates; reacts to integration events from Patient and Admission

Existing BCs modified:
- **Animal BC**: adds `AnimalNameChanged` domain event + `@Version`
- **Consultation BC**: `animalId`/`ownerId` replaced by `patientId` (non-nullable), `waitingRoomEntryId` replaced by `admissionId` (non-nullable)
- **Scheduling BC**: `WaitingRoomEntry` aggregate and all related infrastructure removed

### Scope

**In Scope:**
- Patient aggregate (new BC): create, link to animal, reconcile, DisplayLabel management, @Version
- Animal BC: add `AnimalNameChanged` event, add `@Version`
- Consultation BC: refactor to reference PatientId and AdmissionId
- Admission aggregate (new BC): replaces WaitingRoomEntry, LocationStatus VO, full lifecycle
- Regulatory subdomain (new BC): 3 aggregates + FrenchWorkingDayCalculator domain service
- Application services: PatientCreationService, EmergencyAdmissionService, PatientIdentityResolutionService, ChipLookupService
- Integration events: typed specification with 3-level typology
- UX spec: emergency admission form (Turbo/Stimulus)
- Migration plan: 6 ordered commits, Foundry fixtures for 4 scenarios

**Out of Scope:**
- Billing BC (PartiePayeuse, Invoice)
- Rename Consultation → ClinicalCare
- Public REST API
- Multi-tenant cross-clinic Animal registry
- Full event sourcing
- Redis caching
- Automatic I-CAD API integration (V1 = manual chip entry by ASV)
- Jours fériés "ponts" in FrenchWorkingDayCalculator

---

## Context for Development

### Locked Decisions (D1–D14)

| Decision | Summary |
|----------|---------|
| D1 | Patient is distinct from Animal, stable for life, can exist without Animal |
| D2 | Animal always requires owner (non-nullable), carries official identity fields |
| D3 | `count(Patient.Active where animalLink = X, clinicId = Y) ≤ 1` — enforced by PatientCreationService |
| D4 | Admission in its own BC, references PatientId by ID only |
| D5 | Three roles: Owner (Animal), Presenter (Admission VO), BillingParty (future Invoice) |
| D6 | Patient, Admission, Regulatory, CustomerRelations communicate via integration events only |
| D7 | Identity resolution: `linkToAnimal()` on aggregate (1 method), service orchestrates 2 use-cases + `reconcileInto()` |
| D8 | UI uses "patient" for care, "animal" for identity. Code in English. |
| D9 | DisplayLabel stored on Patient (kind: Provisional\|FromAnimal\|Custom), never calculated on the fly |
| D10 | Four validated business scenarios |
| D11 | French legal obligations as integration events |
| D12 | Animal is strictly per-clinic (clinicId readonly, non-nullable) |
| D13 | Patient is strictly per-clinic (clinicId readonly, non-nullable) |
| D14 | ChipNumber uniqueness scoped per clinic (partial unique index) |

### Resolved Trade-offs (T1–T6)

**[T1] Admission ↔ Consultation — DECISION: Parallel, both reference PatientId by ID**

Admission and Consultation are independent aggregates in separate BCs. They are NOT parent-child. A hospitalization creates 1 Admission + N Consultations over the stay. Post-op follow-up = new Admission. Consultation.admissionId is non-nullable and immutable — every consultation belongs to an admission episode. `reconcileInto()` uses eventual consistency: `PatientReconciledInto` integration event triggers Consultation BC handler to re-point consultations.

**[T2] Multi-clinic Animal identity — DECISION: Per-clinic (confirmed by existing code)**

`Animal.clinicId` is readonly non-nullable in existing code. No change. I-CAD manual lookup (V1) creates a new Animal in the current clinic when owner identified.

**[T3] ChipNumber uniqueness — DECISION: Per-clinic scope**

`UNIQUE INDEX ON (clinic_id, chip_number) WHERE chip_number IS NOT NULL`. Exception: `DuplicateChipInClinicException`.

**[T4] Reconciliation atomicity — DECISION: Eventual consistency via integration events**

Patient and Consultation are in separate BCs. `reconcileInto()` on Patient aggregate (single transaction in Patient BC) emits `PatientReconciledInto` integration event. Consultation BC handler updates patientId on affected consultations asynchronously. Admission BC handler same. For alpha (DB reset, zero clients), eventual consistency window is acceptable.

**[T5] DisplayLabel on AnimalNameChanged — DECISION: Respect Custom kind**

`AnimalNameChanged` integration event consumed by Patient BC. Handler updates DisplayLabel only if `kind !== Custom`. If `kind === Custom`, event is consumed silently without update (no prompt to ASV).

**[T6] UI vocabulary — DECISION: See L9 section (frozen French terms)**

ASV sees `DisplayLabel.value` directly in waiting room list. "Patient non identifié" appears only in detail views and audit reports.

### Structural Conventions (STRUCT-1 to STRUCT-5)

| Convention | Rule |
|-----------|------|
| STRUCT-1 | No `Medical/` wrapper. Patient, Admission, Regulatory are flat siblings at `src/Context/` level |
| STRUCT-2 | BC name = `Admission` (matches aggregate name, consistent with Animal/Client/Consultation pattern) |
| STRUCT-3 | Regulatory Domain/ is flat: MairieNotification.php, StrayCustody.php, ICADLookup.php co-located |
| STRUCT-4 | WaitingRoomEntry removed from Scheduling entirely; LocationStatus VO on Admission covers the concept |
| STRUCT-5 | SpeciesCode, BreedCode, Sex VOs stay in Animal BC; Patient imports them (exception assumed; future chore: extract to Shared/Catalog/) |

### Codebase Patterns (from investigation)

- Animal BC already has: Identification VO (chip/tattoo/passport), LifeCycle VO, Transfer VO, Ownership collection, AuxiliaryContact VO — **no changes to these**
- Consultation has `?WaitingRoomEntryId` → becomes `AdmissionId` (non-nullable); `?AnimalId` + `?OwnerId` → replaced by `PatientId` (non-nullable)
- PHP ≥ 8.5: `\NoDiscard` native attribute, `final class` default
- Named constructors: `create()` records events; `reconstitute()` NEVER does
- Domain events carry scalars only (serializable for Messenger)
- `ClockInterface` everywhere, never `new \DateTimeImmutable()`
- Write repository in `Domain/Repository/`; read repository in `Application/Port/`
- `#[AsMessageHandler]` on handlers — no manual YAML registration

### Files to Reference

| File | Purpose |
|------|---------|
| `src/Context/Animal/Domain/Animal.php` | Existing aggregate — add AnimalNameChanged, @Version |
| `src/Context/Consultation/Domain/Consultation.php` | Existing aggregate — refactor patientId/admissionId |
| `src/Context/Animal/Domain/ValueObject/Identification.php` | Chip/tattoo/passport — unchanged |
| `src/Context/Scheduling/Domain/WaitingRoomEntry.php` | To delete |
| `src/Shared/Domain/Aggregate/AggregateRoot.php` | Base class for all aggregates |
| `src/Shared/Domain/ValueObject/PhoneNumber.php` | Use for Presenter.phone |
| `src/Shared/Domain/` | Check for existing VOs before creating |
| `_bmad-output/project-context.md` | Non-negotiable implementation rules |

---

## Implementation Plan

### Phase 1 — Patient BC (new)

- **Task 1.1** Create `src/Context/Patient/` directory structure: `Domain/`, `Domain/Event/`, `Domain/Exception/`, `Domain/Repository/`, `Domain/ValueObject/`, `Application/`, `Application/Command/`, `Application/Port/`, `Application/Query/`, `Application/Service/`, `Application/EventSubscriber/`, `Infrastructure/`, `Infrastructure/Doctrine/`, `Infrastructure/Doctrine/Entity/`, `Infrastructure/Doctrine/Mapper/`, `Infrastructure/Doctrine/Repository/`, `Infrastructure/Adapter/`
- **Task 1.2** Create `PatientId` VO (`Patient\Domain\ValueObject\PatientId`) — wraps UUID string
- **Task 1.3** Create `ClinicId` VO in Patient BC (local copy, BC-isolation pattern)
- **Task 1.4** Create `DisplayLabel` VO: `kind: DisplayLabelKind` enum (`Provisional|FromAnimal|Custom`), `value: string`
- **Task 1.5** Create `AnimalLink` VO: `animalId: string` (raw UUID cross-BC reference)
- **Task 1.6** Create `PatientStatus` enum: `Active | Archived`
- **Task 1.7** Create `Patient` aggregate (see L1 spec)
- **Task 1.8** Create domain events: `PatientCreated`, `PatientLinkedToAnimal`, `PatientDisplayLabelCustomized`, `PatientReconciledInto`, `PatientArchived`
- **Task 1.9** Create domain exceptions (see L1 spec)
- **Task 1.10** Create `PatientRepositoryInterface` in `Patient\Domain\Repository\`
- **Task 1.11** Create `PatientReadRepositoryInterface` in `Patient\Application\Port\`
- **Task 1.12** Create `AnimalNameProviderInterface` in `Patient\Application\Port\` (returns animal name by animalId string)
- **Task 1.13** Create `PatientEntity` Doctrine entity + `PatientMapper` + `DoctrinePatientRepository`
- **Task 1.14** Create `PatientCreationService` in `Patient\Application\Service\`
- **Task 1.15** Create `PatientIdentityResolutionService` in `Patient\Application\Service\`
- **Task 1.16** Create `AnimalNameChangedHandler` in `Patient\Application\EventSubscriber\` (integration event consumer)
- **Task 1.17** Create `DoctrineAnimalNameProvider` in `Patient\Infrastructure\Adapter\Animal\` (implements AnimalNameProviderInterface, queries animal table directly)
- **Task 1.18** Write unit tests: Patient aggregate, PatientCreationService, PatientIdentityResolutionService, PatientMapper
- **Task 1.19** Write integration tests: DoctrinePatientRepository

### Phase 2 — Animal BC modifications

- **Task 2.1** Add `AnimalNameChanged` domain event to `Animal\Domain\Event\`
- **Task 2.2** Update `Animal::updateIdentity()`: if name changes, record `AnimalNameChanged` event
- **Task 2.3** Create `AnimalNameChangedIntegrationBridge` in `Animal\Infrastructure\Messaging\Consumer\` (NOT Application\EventSubscriber\): `#[AsMessageHandler(bus: 'messenger.bus.event')]`, consumes `AnimalNameChanged` domain event, publishes `AnimalNameChangedIntegrationEvent` via `IntegrationEventPublisher`
- **Task 2.4** Add `@Version int` to `AnimalEntity` Doctrine mapping (optimistic lock)
- **Task 2.5** Update `AnimalMapper` to map version field
- **Task 2.6** Update Animal tests to verify `AnimalNameChanged` event recorded on name change
- **Task 2.7** Add `findByMicrochip(string $microchipNumber, string $clinicId): ?AnimalReadModel` to `Animal\Application\Port\AnimalReadRepositoryInterface` and implement in `DoctrineAnimalReadRepository`

### Phase 3 — Consultation BC refactor

- **Task 3.1** Replace `?AnimalId` with `PatientId` (non-nullable) in `Consultation` aggregate — local VO in `Consultation\Domain\ValueObject\PatientId`
- **Task 3.2** Remove `?OwnerId` from `Consultation` aggregate entirely
- **Task 3.3** Replace `?WaitingRoomEntryId` with `AdmissionId` (non-nullable) — local VO in `Consultation\Domain\ValueObject\AdmissionId`
- **Task 3.4** Update named constructors: `startFromAppointment(admissionId, patientId, ...)`, `startFromAdmission(admissionId, patientId, ...)`
- **Task 3.5** Remove `attachPatientIdentity()` method (no longer needed — patient set at creation)
- **Task 3.6** Update `ConsultationEntity` Doctrine mapping: remove owner_id, add patient_id + admission_id
- **Task 3.7** Create `PatientReconciledIntoHandler` in `Consultation\Infrastructure\Messaging\Consumer\` (integration event consumer, `#[AsMessageHandler(bus: 'messenger.bus.integration_event')]`): updates patientId on affected consultations when `PatientReconciledInto` integration event received
- **Task 3.8** Update all Consultation tests (unit + integration)
- **Task 3.9** Remove `AttachPatientIdentity` command + handler
- **Task 3.10** Rename `StartConsultationFromWaitingRoomEntry` → `StartConsultationFromAdmission`: update command DTO (`waitingRoomEntryId: string` → `admissionId: string`), handler, and `ConsultationStartedFromWaitingRoomEntry` domain event → `ConsultationStartedFromAdmission`
- **Task 3.11** Update `SchedulingServiceCoordinatorInterface` in `Consultation\Application\Port\`: replace `ensureWaitingRoomEntryInService(WaitingRoomEntryId, UserId)` with `updateAdmissionLocationStatus(string $admissionId, string $newLocationStatus, string $triggeredByUserId)`. Update implementation `MessengerSchedulingServiceCoordinator`.
- **Task 3.12** Update `ConsultationEntity` and `ConsultationMapper`: remove `waiting_room_entry_id` column, add `admission_id` and `patient_id` non-nullable UUID columns
- **Task 3.13** Create `AdmissionContextProviderInterface` in `Consultation\Application\Port\`:
  ```php
  interface AdmissionContextProviderInterface {
      public function getAdmissionContext(string $admissionId): AdmissionContextDto;
  }
  // AdmissionContextDto: public readonly string $patientId, public readonly string $clinicId
  ```
  Create `DbalAdmissionContextProvider` in `Consultation\Infrastructure\Adapter\Admission\` querying `admission__admissions` table. Used by `StartConsultationFromAdmissionHandler` to resolve `patientId`/`clinicId` from `admissionId`.
- **Task 3.14** Create `AdmissionServiceCoordinatorInterface` in `Consultation\Application\Port\` (separate from `SchedulingServiceCoordinatorInterface`):
  ```php
  interface AdmissionServiceCoordinatorInterface {
      public function updateLocationStatus(string $admissionId, string $newLocationStatus, string $triggeredByUserId): void;
  }
  ```
  Create `MessengerAdmissionServiceCoordinator` in `Consultation\Infrastructure\Adapter\Admission\` dispatching via CommandBus to Admission BC. Remove stale `ensureWaitingRoomEntryInService` from `SchedulingServiceCoordinatorInterface`.
- **Task 3.15** Delete dead ports and adapters: `Consultation\Application\Port\AnimalExistenceCheckerInterface`, `Consultation\Application\Port\OwnerExistenceCheckerInterface`, `Consultation\Infrastructure\Adapter\Animal\DbalAnimalExistenceChecker`, `Consultation\Infrastructure\Adapter\Client\DbalOwnerExistenceChecker`

### Phase 4 — Scheduling BC: remove WaitingRoomEntry

- **Task 4.0** ⚠️ **MUST RUN BEFORE TASK 4.1** — Update `Consultation\Infrastructure\Adapter\Scheduling\DbalSchedulingAppointmentContextProvider`: remove SQL JOIN on `scheduling__waiting_room_entries`; provider now queries only appointment data. Update `AppointmentContextDTO` to remove `linkedWaitingRoomEntryId` field. Update `StartConsultationFromAppointmentHandler` accordingly.
- **Task 4.1** Delete `src/Context/Scheduling/Domain/WaitingRoomEntry.php`
- **Task 4.2** Delete WaitingRoomEntry Doctrine entity, mapper, repository
- **Task 4.3** Remove WaitingRoomEntry Foundry factory and fixtures
- **Task 4.4** Update Scheduling BC if any aggregate still references WaitingRoomEntryId (check cross-references)
- **Task 4.5** Update tests
- **Task 4.6** Refactor `Scheduling\Domain\Appointment` aggregate: remove `?OwnerId` and `?AnimalId` fields + related getters/setters; replace with `?string $admissionId` (nullable cross-BC ref). Update `AppointmentEntity`, `AppointmentMapper`, `ScheduleAppointment` command DTO, `ScheduleAppointmentHandler`, all Appointment tests.
- **Task 4.7** Map `WaitingRoomEntry.foundAnimalDescription` to new model: if Patient is unidentified at admission open, populate `Admission.physicalDescription` from `WRE.foundAnimalDescription`. If Patient is identified, field can be null. Add this field to `admission__admissions` table + entity + mapper.
- **Task 4.8** Delete Foundry fixtures exhaustively: `WaitingRoomEntryEntityFactory.php`, `WaitingRoomStory.php`, and any Story/Dataset file referencing `WaitingRoomEntry` (run `grep -r WaitingRoomEntry tests/ src/DataFixtures/ --include="*.php" -l` before deletion)

### Phase 5 — Admission BC (new)

- **Task 5.0** Add `triageNotes: ?string`, `?appointmentId: string`, and `?physicalDescription: string` fields to Admission aggregate constructor, entity, mapper — per INV-1, INV-2, and F11
- **Task 5.1** Create `src/Context/Admission/` directory structure (mirrors Patient BC structure)
- **Task 5.2** Create VOs: `AdmissionId`, `ClinicId` (local), `IntakeChannel` enum (8 values), `TriageLevel` enum (4 values), `ClosureReason` enum (10 values), `LocationStatus` VO (enum + enteredAt), `Presenter` VO, `PresenterRole` enum
- **Task 5.3** Create `AdmissionStatus` enum: `Active | Closed`
- **Task 5.4** Create `Admission` aggregate (see L3 spec)
- **Task 5.5** Create domain events: `AdmissionOpened`, `AdmissionOpenedWithUnidentifiedPatient`, `AdmissionTriageEscalated`, `AdmissionTriageDeescalated`, `AdmissionLocationStatusUpdated`, `AdmissionClosed`, `AdmissionReopenedInError`
- **Task 5.6** Create domain exceptions (see L3 spec)
- **Task 5.7** Create `AdmissionRepositoryInterface` + `AdmissionReadRepositoryInterface`
- **Task 5.8** Create `AdmissionEntity` Doctrine entity + `AdmissionMapper` + `DoctrineAdmissionRepository`
- **Task 5.9** Add `@Version int` to `AdmissionEntity`
- **Task 5.10** Create `WaitingRoomItemDto` read-model DTO (see L7 spec)
- **Task 5.11** Create `ChipLookupService` in `Admission\Application\Service\`
- **Task 5.12** Create `ICADLookupPort` in `Admission\Application\Port\` (returns `ChipLookupResult`). Define `ChipLookupResult` as sealed interface + 3 final classes:
  ```php
  interface ChipLookupResult {}
  final class AnimalNotInClinic implements ChipLookupResult {}  // chip unknown locally
  final class AnimalInClinicWithActivePatient implements ChipLookupResult {
      public function __construct(public readonly string $animalId, public readonly string $patientId, public readonly string $animalName) {}
  }
  final class AnimalInClinicWithoutActivePatient implements ChipLookupResult {
      public function __construct(public readonly string $animalId, public readonly string $animalName) {}
  }
  ```
- **Task 5.13** Create `PatientCreationPort` in `Admission\Application\Port\`:
  ```php
  interface PatientCreationPort {
      public function createUnidentifiedPatient(string $clinicId, string $provisionalLabel, ?string $observedSpecies, ?string $observedColor): string; // returns patientId
      public function createIdentifiedPatient(string $clinicId, string $animalId, string $animalName): string; // returns patientId
  }
  ```
  Create `CommandBusPatientCreationAdapter` in `Admission\Infrastructure\Adapter\Patient\` dispatching `CreateUnidentifiedPatient`/`CreateIdentifiedPatient` commands to Patient BC via CommandBus.
- **Task 5.14** Create `EmergencyAdmissionService` in `Admission\Application\Service\` — inject `PatientCreationPort`
- **Task 5.15** Create `PatientReconciledIntoHandler` in `Admission\Infrastructure\Messaging\Consumer\` (integration event consumer, re-points admission.patientId on reconciliation — bus: `messenger.bus.integration_event`)
- **Task 5.16** Write unit + integration tests

### Phase 6 — Regulatory BC (new)

- **Task 6.1** Create `src/Context/Regulatory/` directory structure
- **Task 6.2** Create VOs: `MairieNotificationId`, `StrayCustodyId`, `ICADLookupId`, `ClinicId` (local)
- **Task 6.3** Create `FrenchWorkingDayCalculator` in `Regulatory\Domain\Service\` (pure calcul, no framework dependencies)
- **Task 6.4** Create `MairieNotification` aggregate (see L4 spec)
- **Task 6.5** Create `StrayCustody` aggregate (see L4 spec)
- **Task 6.6** Create `ICADLookup` aggregate (see L4 spec)
- **Task 6.7** Create repositories + Doctrine infrastructure for all three aggregates
- **Task 6.8** Create `AdmissionOpenedWithUnidentifiedPatientHandler` (integration event consumer → creates MairieNotification + StrayCustody)
- **Task 6.9** Create `PatientLinkedToAnimalHandler` (integration event consumer → cancels StrayCustody)
- **Task 6.10** Create `AdmissionClosedHandler` (integration event consumer → closes StrayCustody on HandedToMunicipality)
- **Task 6.11** Create `OpenICADLookup` command + handler
- **Task 6.11b** Create `ICADLookupAdapter` in `Admission\Infrastructure\Adapter\Regulatory\` (DEC-C: moved to Commit E so Regulatory BC handler exists before adapter is wired — avoids `NoHandlerForMessageException`)
- **Task 6.12** Create `RegulatoryTasksReadRepositoryInterface` + Doctrine implementation
- **Task 6.13** Write unit + integration tests

### Phase 7 — Presentation layer + Fixtures

- **Task 7.1** Create `EmergencyAdmissionController` (single-action `__invoke`, Admission BC)
- **Task 7.2** Create emergency admission Twig template with Turbo Frame + Stimulus
- **Task 7.3** Update waiting room Twig view to query `AdmissionReadRepositoryInterface` (filter `status=Active, locationStatus.value=InWaitingRoom`)
- **Task 7.4** Create Foundry factories: `PatientEntityFactory`, `AdmissionEntityFactory`, `MairieNotificationEntityFactory`, `StrayCustodyEntityFactory`
- **Task 7.5** Create fixtures for 4 scenarios (see L8)
- **Task 7.6** Update existing Animal fixtures to populate `version` field
- **Task 7.7** Delete Presentation controllers for WaitingRoomEntry (INV-3): `WaitingRoomController`, `WaitingRoomQueueController`, `CreateWalkInController`, `UpdateTriageController`, `StartServiceController`, `StartServiceFromWaitingRoomController`, `WaitingRoomEntryDetailsController`, `CloseWaitingRoomEntryController`, `CloseEntryFromWaitingRoomController`, `CheckInAppointmentController`, `StartConsultationFromWaitingRoomController`
- **Task 7.8** Create replacement Presentation controllers for Admission BC: `AdmissionQueueController` (replaces WaitingRoomQueueController, queries Admission read-model), `CheckInFromAppointmentController` (creates Admission from appointment), `UpdateAdmissionTriageController`, `UpdateAdmissionLocationController`, `CloseAdmissionController`, `StartConsultationFromAdmissionController`
- **Task 7.9** Update `AgendaController` to remove WaitingRoomEntry references, update to use AdmissionId
- **Task 7.10** Add `BOUNDED_CONTEXT` constant override to all new domain events: `'patient'`, `'admission'`, `'regulatory'`
- **Task 7.11** Create `src/Context/Patient/README.md`, `src/Context/Admission/README.md`, `src/Context/Regulatory/README.md` — each must include: Ubiquitous Language, business invariants, use cases, fixture examples (follow `src/System/AccessControl/README.md` as reference template)

---

## L1 — Patient Aggregate Spec

```php
// src/Context/Patient/Domain/Patient.php
final class Patient extends AggregateRoot
{
    private function __construct(
        private readonly PatientId           $id,
        private readonly ClinicId            $clinicId,
        private PatientStatus                $status,        // Active | Archived
        private DisplayLabel                 $displayLabel,
        private ?AnimalLink                  $animalLink,    // null = unidentified
        private ?string                      $observedSpecies,
        private ?string                      $observedColor,
        private int                          $version,       // @Version optimistic lock
        private readonly \DateTimeImmutable  $createdAt,
        private \DateTimeImmutable           $updatedAt,
    ) {}

    // Named constructors

    public static function createUnidentified(
        PatientId $id,
        ClinicId $clinicId,
        string $provisionalLabel,
        ?string $observedSpecies,
        ?string $observedColor,
        \DateTimeImmutable $now,
    ): self
    // DisplayLabel::provisional($provisionalLabel)
    // Emits: PatientCreated(patientId, clinicId, isIdentified=false, displayLabelValue)

    public static function createFromAnimal(
        PatientId $id,
        ClinicId $clinicId,
        AnimalLink $animalLink,
        string $animalName,
        \DateTimeImmutable $now,
    ): self
    // DisplayLabel::fromAnimal($animalName)
    // Emits: PatientCreated(patientId, clinicId, isIdentified=true, displayLabelValue)

    // Domain methods

    public function linkToAnimal(AnimalLink $animalLink, \DateTimeImmutable $now): void
    // Invariant: status === Active, animalLink === null
    // Does NOT update DisplayLabel (done by service layer after repo.save + event publish)
    // Emits: PatientLinkedToAnimal(patientId, animalId=animalLink.animalId, clinicId)

    public function updateDisplayLabel(DisplayLabel $newLabel, \DateTimeImmutable $now): void
    // Called by service BEFORE repo.save() — after linkToAnimal(), before flush
    // Also called by AnimalNameChangedHandler (Patient BC Infrastructure\Messaging\Consumer\)
    // If current kind === Custom: no-op (T5 resolution)
    // Emits: PatientDisplayLabelChanged(patientId, newValue, newKind) — consumed by projections
    // Note: event name PatientDisplayLabelChanged distinct from PatientDisplayLabelCustomized (ASV explicit action)

    public function customizeDisplayLabel(string $value, \DateTimeImmutable $now): void
    // Sets kind=Custom, value=$value
    // Emits: PatientDisplayLabelCustomized(patientId, value)

    public function reconcileInto(string $targetPatientId, \DateTimeImmutable $now): void
    // Invariant: status === Active
    // Sets status = Archived
    // Emits: PatientReconciledInto(sourcePatientId=this.id, targetPatientId, clinicId)

    public function archive(\DateTimeImmutable $now): void
    // Invariant: status === Active
    // Sets status = Archived
    // Emits: PatientArchived(patientId, clinicId)

    // Reconstitution
    public static function reconstituteFromPersistence(...): self  // NEVER calls recordDomainEvent()
    // Matches majority pattern: Animal::reconstituteFromPersistence (F32 normalization)
}
```

**Invariants:**
- `animalLink` can only be set once: `linkToAnimal()` throws `PatientAlreadyLinkedException` if already linked
- All mutation methods throw `PatientNotActiveException` if `status !== Active`
- `reconcileInto` and `archive` require `status === Active`
- `count(Patient.Active where animalLink=X, clinicId=Y) ≤ 1` enforced by `PatientCreationService` before `createFromAnimal()` and before `linkToAnimal()`

**Domain Exceptions:**
- `PatientNotFoundException(string $patientId)` — thrown by repository when not found
- `PatientAlreadyLinkedException(string $patientId)`
- `PatientNotActiveException(string $patientId, string $currentStatus)` — covers all status≠Active cases incl. Archived
- `PatientClinicMismatchException(string $patientId, string $expectedClinicId)`
- `PatientAlreadyExistsForAnimalException(string $animalId, string $clinicId)` — thrown by service
- Note: `PatientAlreadyArchivedException` removed — redundant with `PatientNotActiveException`

**Doctrine Persistence:**
- Table: `patient__patients`
- Columns: `id BINARY(16)`, `clinic_id BINARY(16)`, `status VARCHAR(16)`, `display_label_kind VARCHAR(16)`, `display_label_value VARCHAR(255)`, `animal_link_id BINARY(16) NULL`, `observed_species VARCHAR(64) NULL`, `observed_color VARCHAR(64) NULL`, `version INT NOT NULL DEFAULT 1`, `created_at DATETIME`, `updated_at DATETIME`
- Indexes: `(clinic_id, status)`, `(clinic_id, animal_link_id)` WHERE animal_link_id IS NOT NULL

---

## L2 — Animal BC Modifications

**Animal aggregate changes (minimal):**

```php
// Add to Animal::updateIdentity():
if ($this->name !== $name) {
    $this->recordDomainEvent(new AnimalNameChanged(
        animalId: $this->id->toString(),
        clinicId: $this->clinicId->toString(),
        newName: $name,
    ));
}
$this->name = $name;
// (rest of updateIdentity unchanged)
```

**Integration bridge** (converts intra-BC domain event to cross-BC integration event):

```php
// src/Context/Animal/Infrastructure/Messaging/Consumer/AnimalNameChangedIntegrationBridge.php
// PATTERN: Infrastructure\Messaging\Consumer\ (matches ClientArchivedIntegrationEventConsumer)
// Bus: messenger.bus.event (domain events)
final readonly class AnimalNameChangedIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher) {}

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function __invoke(AnimalNameChanged $event): void
    {
        $this->integrationEventPublisher->publish(
            new AnimalNameChangedIntegrationEvent(
                animalId: $event->animalId,
                clinicId: $event->clinicId,
                newName:  $event->newName,
            )
        );
    }
}
```

**ChipNumber uniqueness:**
```sql
UNIQUE INDEX animal__animals_chip_unique (clinic_id, microchip_number)
WHERE microchip_number IS NOT NULL
```
Exception: reuse existing `MicrochipAlreadyUsedException` (already in Animal BC). Do NOT create `DuplicateChipInClinicException`.

**findByChip() on AnimalReadRepositoryInterface:**
```php
// src/Context/Animal/Application/Port/AnimalReadRepositoryInterface.php — ADD:
public function findByMicrochip(string $microchipNumber, string $clinicId): ?AnimalReadModel;
```

**@Version:** `AnimalEntity` gets `version INT NOT NULL DEFAULT 1` mapped with `#[ORM\Version]`.

---

## L3 — Admission Aggregate Spec

```php
// src/Context/Admission/Domain/Admission.php
final class Admission extends AggregateRoot
{
    private function __construct(
        private readonly AdmissionId         $id,
        private readonly ClinicId            $clinicId,
        private readonly string              $patientId,              // cross-BC: raw UUID
        private readonly bool                $isPatientIdentifiedAtOpening,
        private IntakeChannel                $intakeChannel,
        private TriageLevel                  $triageLevel,
        private ?Presenter                   $presenter,
        private LocationStatus               $locationStatus,         // VO: value + enteredAt
        private readonly ?string             $appointmentId,          // nullable cross-BC raw UUID, set on AppointmentBooked intake
        private ?string                      $physicalDescription,    // migrated from WRE.foundAnimalDescription — free-text animal description at arrival
        private AdmissionStatus              $status,                 // Active | Closed
        private ?ClosureReason               $closureReason,
        private ?string                      $triageNotes,            // free-text ASV notes (migrated from WaitingRoomEntry)
        private int                          $version,                // @Version
        private readonly \DateTimeImmutable  $openedAt,
        private ?\DateTimeImmutable          $closedAt,
        private readonly \DateTimeImmutable  $createdAt,
        private \DateTimeImmutable           $updatedAt,
    ) {}

    public static function open(
        AdmissionId $id, ClinicId $clinicId,
        string $patientId, bool $isPatientIdentified,
        IntakeChannel $channel, TriageLevel $triage,
        ?Presenter $presenter, \DateTimeImmutable $now,
    ): self
    // LocationStatus initialized to InWaitingRoom (enteredAt=$now) for walk-in/emergency
    // Emits: AdmissionOpened(admissionId, patientId, clinicId, isPatientIdentified, channel, triage, now)
    // If !isPatientIdentified: also emits AdmissionOpenedWithUnidentifiedPatient(admissionId, patientId, clinicId, now)

    public function escalateTriage(TriageLevel $newLevel, \DateTimeImmutable $now): void
    // Guard: newLevel.ordinal > current.ordinal (Standard=0, Priority=1, Emergency=2, VitalEmergency=3)
    // Emits: AdmissionTriageEscalated(admissionId, oldLevel, newLevel, now)

    public function deescalateTriage(TriageLevel $newLevel, \DateTimeImmutable $now): void
    // Guard: current === VitalEmergency AND newLevel === Emergency ONLY
    // Throws TriageDeescalationNotAllowedException otherwise
    // Emits: AdmissionTriageDeescalated(admissionId, oldLevel=VitalEmergency, newLevel=Emergency, now)

    public function updateLocationStatus(LocationStatus $newStatus, \DateTimeImmutable $now): void
    // Guard: status === Active
    // Emits: AdmissionLocationStatusUpdated(admissionId, newLocationValue, now)

    public function updateTriageNotes(?string $notes, \DateTimeImmutable $now): void
    // Guard: status === Active

    public function updatePresenter(Presenter $presenter, \DateTimeImmutable $now): void

    public function close(ClosureReason $reason, \DateTimeImmutable $now): void
    // Guard: status === Active
    // Sets status=Closed, closureReason=$reason, closedAt=$now
    // Emits: AdmissionClosed(admissionId, patientId, clinicId, reason=reason.value, closedAt)

    public function reopenInError(\DateTimeImmutable $now): void
    // Guard: closureReason === AdministrativeError ONLY
    // Sets status=Active, closureReason=null, closedAt=null
    // Emits: AdmissionReopenedInError(admissionId, patientId, clinicId, now)
}
```

**LocationStatus VO:**
```php
final class LocationStatus
{
    public function __construct(
        public readonly LocationStatusValue  $value,   // enum: InWaitingRoom|InConsultationRoom|InHospitalization|InSurgery|AtReception
        public readonly \DateTimeImmutable   $enteredAt,
    ) {}
}
```

**TriageLevel ordinals** (for escalation guard):
```
Standard=0, Priority=1, Emergency=2, VitalEmergency=3
```

**Presenter VO:**
```php
final class Presenter
{
    public function __construct(
        public readonly string       $name,
        public readonly ?PhoneNumber $phone,      // Shared\Domain\ValueObject\PhoneNumber
        public readonly PresenterRole $role,      // enum: Owner|ThirdParty|Authority|Association|Municipality|Unknown
    ) {}
}
```

**consultation.admission_id constraint:** NOT UNIQUE intentionally — one Admission can have N Consultations per T1 resolution. No `UNIQUE(admission_id)` on `consultation__consultations` table.

**Domain Exceptions:**
- `AdmissionNotFoundException(string $admissionId)` — thrown by repository when not found
- `AdmissionAlreadyClosedException(string $admissionId)`
- `AdmissionReopenNotAllowedException(string $admissionId, string $closureReason)`
- `TriageDeescalationNotAllowedException(string $from, string $to)`
- `AdmissionClinicMismatchException(string $admissionId, string $expectedClinicId)`

**Doctrine Persistence:**
- Table: `admission__admissions`
- Columns: `id BINARY(16)`, `clinic_id BINARY(16)`, `patient_id BINARY(16)`, `is_patient_identified_at_opening TINYINT(1)`, `intake_channel VARCHAR(32)`, `triage_level VARCHAR(32)`, `presenter_name VARCHAR(255) NULL`, `presenter_phone VARCHAR(32) NULL`, `presenter_role VARCHAR(32) NULL`, `location_status_value VARCHAR(32)`, `location_status_entered_at DATETIME`, `status VARCHAR(16)`, `closure_reason VARCHAR(32) NULL`, `version INT NOT NULL DEFAULT 1`, `opened_at DATETIME`, `closed_at DATETIME NULL`, `created_at DATETIME`, `updated_at DATETIME`
- Indexes: `(clinic_id, status)`, `(clinic_id, patient_id)`, `(clinic_id, status, location_status_value)` ← waiting room filter

---

## L4 — Regulatory BC Spec

**@Version on Regulatory aggregates:** `MairieNotificationEntity`, `StrayCustodyEntity`, and `ICADLookupEntity` each carry `version INT NOT NULL DEFAULT 1` with `#[ORM\Version]`. Concurrent `cancelOwnerFound` + `closeHandedToMunicipality` protected by optimistic lock.

### FrenchWorkingDayCalculator

```php
// src/Context/Regulatory/Domain/Service/FrenchWorkingDayCalculator.php
final class FrenchWorkingDayCalculator
{
    // Fixed jours fériés: Jan 1, May 1, May 8, Jul 14, Aug 15, Nov 1, Nov 11, Dec 25
    // Easter-based (Gregorian): Lundi de Pâques (+1), Ascension (+39), Lundi de Pentecôte (+50)
    // Ponts: NOT included — deliberate alpha simplification

    public function addWorkingDays(\DateTimeImmutable $from, int $days): \DateTimeImmutable;
    // No ClockInterface dependency — $from is always passed as parameter
    // Pure date arithmetic: skip Saturdays, Sundays, and jours fériés

    public function isWorkingDay(\DateTimeImmutable $date): bool;
}
```

### MairieNotification Aggregate

```php
// src/Context/Regulatory/Domain/MairieNotification.php
// Deadline: openedAt + 48 CALENDAR hours (not working hours — legal text)
// Trigger: AdmissionOpenedWithUnidentifiedPatient integration event
// States: Pending | Sent | Cancelled

public static function schedule(
    MairieNotificationId $id, string $admissionId, string $patientId,
    string $clinicId, \DateTimeImmutable $admissionOpenedAt,
): self
// deadline = $admissionOpenedAt->modify('+48 hours')
// Emits: MairieNotificationScheduled(notificationId, admissionId, deadline) [Audit Event]

public function markAsSent(\DateTimeImmutable $sentAt): void
// Emits: MairieNotificationSent(notificationId, sentAt) [Audit Event — legal proof of compliance]

public function cancel(string $reason, \DateTimeImmutable $now): void
// Note: owner identification does NOT cancel mairie notification
// Only cancelled if admission itself was an AdministrativeError
```

### StrayCustody Aggregate

```php
// src/Context/Regulatory/Domain/StrayCustody.php
// Deadline: openedAt + 8 WORKING DAYS (code rural L211-25)
// Trigger: AdmissionOpenedWithUnidentifiedPatient integration event
// States: Active | CancelledOwnerFound | ClosedHandedToMunicipality | Expired

public static function begin(
    StrayCustodyId $id, string $admissionId, string $patientId,
    string $clinicId, \DateTimeImmutable $admissionOpenedAt,
    FrenchWorkingDayCalculator $calculator,
): self
// deadline = $calculator->addWorkingDays($admissionOpenedAt, 8)

public function cancelOwnerFound(\DateTimeImmutable $now): void
// Trigger: PatientLinkedToAnimal integration event
// Invariant: status === Active

public function closeHandedToMunicipality(\DateTimeImmutable $now): void
// Trigger: AdmissionClosed(HandedToMunicipality) integration event
// Invariant: status === Active
```

### ICADLookup Aggregate

```php
// src/Context/Regulatory/Domain/ICADLookup.php
// Purpose: Audit trail for I-CAD chip queries (V1: manual entry by ASV)
// States: Pending | FoundInICad | NotFoundInICad | LookupFailed
// Three ChipLookupResult variants: ChipFound(icadData) | ChipNotFound | LookupFailed(error)

public static function initiate(
    ICADLookupId $id, string $chipNumber, string $clinicId, \DateTimeImmutable $now,
): self
// Emits: ICADLookupInitiated [Audit Event]

public function recordFound(string $icadAnimalData, \DateTimeImmutable $now): void
// Emits: ICADLookupFound(lookupId, chipNumber, icadData) [Integration Event → triggers patient link flow]

public function recordNotFound(\DateTimeImmutable $now): void
// Emits: ICADLookupNotFound(lookupId, chipNumber) [Audit Event]

public function recordFailed(string $errorMessage, \DateTimeImmutable $now): void
// Emits: ICADLookupFailed(lookupId, chipNumber, error) [Audit Event]
```

### RegulatoryTasksReadModel

```php
// src/Context/Regulatory/Application/Port/RegulatoryTasksReadRepositoryInterface.php
// Returns per-clinic projection of overdue/upcoming legal tasks:
// - MairieNotifications: Pending, grouped by urgency (overdue / due today / upcoming)
// - StrayCustodies: Active, with working-days countdown
// Consumed by dashboard view (Presentation layer)
```

---

## L5 — Application Services Spec

### PatientCreationService (`Patient\Application\Service\`)

```php
final class PatientCreationService
{
    public function createUnidentified(
        ClinicId $clinicId,
        string $provisionalLabel,
        ?string $observedSpecies,
        ?string $observedColor,
        \DateTimeImmutable $now,
    ): PatientId
    // No invariant check needed: no animalLink → no D3 conflict possible

    public function createFromAnimal(
        ClinicId $clinicId,
        string $animalId,
        string $animalName,
        \DateTimeImmutable $now,
    ): PatientId
    // 1. Check D3: patientReadRepo->existsActiveForAnimal($clinicId, $animalId)
    //    → throws PatientAlreadyExistsForAnimalException if true
    // 2. Patient::createFromAnimal(id, clinicId, animalLink, animalName, now)
    // 3. repo->save(patient)
    // 4. DomainEventPublisher::publish(patient)
}
```

### PatientIdentityResolutionService (`Patient\Application\Service\`)

```php
final class PatientIdentityResolutionService
{
    // Use-case A: owner identified → Animal created first by Animal BC, then link
    public function linkToNewlyCreatedAnimal(
        PatientId $patientId,
        string $animalId,
        string $animalName,        // fetched from Animal BC via QueryBus before this call
        \DateTimeImmutable $now,
    ): void
    // 1. Load Patient
    // 2. Check D3: patientReadRepo->existsActiveForAnimal($clinicId, $animalId) → throws if conflict
    // 3. patient.linkToAnimal(new AnimalLink($animalId))
    //    → records PatientLinkedToAnimal domain event
    // 4. patient.updateDisplayLabel(DisplayLabel::fromAnimal($animalName))
    // 5. repo->save(patient)
    // 6. DomainEventPublisher::publish(patient)

    // Use-case B: Animal pre-existed in clinic (edge case)
    public function linkToExistingAnimal(
        PatientId $patientId,
        string $animalId,
        string $animalName,
        \DateTimeImmutable $now,
    ): void
    // Identical to linkToNewlyCreatedAnimal — distinction is in the caller, not the domain

    // Use-case C: "this animal is already a known patient here"
    public function reconcileInto(
        PatientId $sourcePatientId,
        PatientId $targetPatientId,
        \DateTimeImmutable $now,
    ): void
    // 1. Load both patients, assert same clinicId
    // 2. sourcePatient.reconcileInto($targetPatientId->toString(), $now)
    //    → status=Archived, records PatientReconciledInto domain event
    // 3. repo->save(sourcePatient) ← single transaction, local to Patient BC
    // 4. IntegrationEventPublisher->publish(PatientReconciledIntoIntegration(source, target, clinicId))
    //    → async: Admission BC re-points admissions, Consultation BC re-points consultations
}
```

### EmergencyAdmissionService (`Admission\Application\Service\`)

```php
final class EmergencyAdmissionService
{
    // Called by EmergencyAdmissionController
    public function openEmergencyAdmission(
        ClinicId $clinicId,
        IntakeChannel $channel,
        TriageLevel $triage,
        ?Presenter $presenter,
        ?string $knownAnimalId,       // null = unidentified
        ?string $animalName,          // null if unidentified
        ?string $provisionalLabel,    // required if knownAnimalId === null
        ?string $observedSpecies,
        ?string $observedColor,
        \DateTimeImmutable $now,
    ): AdmissionId
    // 1. Create Patient via PatientCreationPort (cross-BC command dispatch to Patient BC)
    //    If $knownAnimalId: dispatch CreatePatientFromAnimal command
    //    Else: dispatch CreateUnidentifiedPatient command
    // 2. Receive PatientId back from command bus (CommandBusInterface returns string)
    // 3. Admission::open($admissionId, $clinicId, $patientId, $isIdentified, $channel, $triage, $presenter, $now)
    // 4. admissionRepo->save($admission)
    // 5. DomainEventPublisher::publish($admission)
    //    → AdmissionOpened event dispatched
    //    → AdmissionOpenedWithUnidentifiedPatient if !isIdentified (consumed by Regulatory BC)
}
```

### ChipLookupService (`Admission\Application\Service\`)

```php
final class ChipLookupService
{
    // V1: manual chip entry — ASV reads chip number, enters manually
    // Service checks local Animal table first (same clinic), then creates ICADLookup audit record
    public function lookup(string $chipNumber, string $clinicId, \DateTimeImmutable $now): ChipLookupResult
    // 1. Check local Animal by chip (AnimalReadRepository)
    // 2. If found locally: return ChipFound(animalId, animalName, ownerId)
    // 3. If not found locally: dispatch OpenICADLookup command to Regulatory BC (audit)
    //    → return ChipNotFound (ASV looks up I-CAD manually and enters result)
    // ChipLookupResult = sealed value: ChipFound(animalId, animalName) | ChipNotFound | LookupFailed(error)
}
```

**Atomicity note:** `EmergencyAdmissionService` creates Patient and Admission in two separate BC transactions (Patient BC first, then Admission BC). If Admission creation fails after Patient creation, Patient BC holds an orphaned Patient. For alpha: acceptable. For production: consider a saga or compensating transaction (out of scope).

---

## L6 — Domain & Integration Events Spec

### 3-Level Event Typology

| Type | Bus | Transport | Consumer | Purpose |
|------|-----|-----------|----------|---------|
| **Domain Event** | `EventBus` (sync) | in-process | Other aggregates/projections in same BC | Intra-BC business fact |
| **Integration Event** | `IntegrationEventBus` (async) | doctrine transport | Other BCs via message handlers | Cross-BC business fact |
| **Audit Event** | `IntegrationEventBus` (async) | doctrine transport | Persisted to audit log, no functional listener | Legal/compliance traceability |

### Patient BC Events

| Event | Type | Payload | Consumer |
|-------|------|---------|----------|
| `PatientCreated` | Domain | patientId, clinicId, isIdentified, displayLabelValue | Projections |
| `PatientLinkedToAnimal` | Domain | patientId, animalId, clinicId | Projections (DisplayLabel already updated by service before event publish) |
| `PatientDisplayLabelCustomized` | Domain | patientId, newValue | Projections |
| `PatientReconciledInto` | Domain + bridge to Integration | sourcePatientId, targetPatientId, clinicId | Integration bridge → Consultation BC, Admission BC (re-point patientId) |
| `PatientArchived` | Domain | patientId, clinicId | Projections |

### Animal BC Events

| Event | Type | Payload | Consumer |
|-------|------|---------|----------|
| `AnimalNameChanged` | Domain (intra) | animalId, clinicId, newName | Bridge → `AnimalNameChangedIntegrationEvent` → Patient BC `AnimalNameChangedHandler` (updates DisplayLabel if kind≠Custom) |
| `AnimalCreated` | Domain | animalId, clinicId, name, primaryOwnerClientId | Projections (existing) |
| `AnimalArchived` | Domain | animalId, clinicId | Projections (existing) |

### Admission BC Events

| Event | Type | Payload | Consumer |
|-------|------|---------|----------|
| `AdmissionOpened` | Domain | admissionId, patientId, clinicId, channel, triage, isPatientIdentified, openedAt | Projections |
| `AdmissionOpenedWithUnidentifiedPatient` | Integration | admissionId, patientId, clinicId, openedAt | Regulatory BC → create MairieNotification + StrayCustody |
| `AdmissionTriageEscalated` | Audit | admissionId, oldLevel, newLevel, occurredAt | Audit log |
| `AdmissionTriageDeescalated` | Audit | admissionId, oldLevel=VitalEmergency, newLevel=Emergency, occurredAt | Audit log |
| `AdmissionLocationStatusUpdated` | Domain | admissionId, newLocationValue, now | Projections (waiting room real-time update) |
| `AdmissionClosed` | Integration | admissionId, patientId, clinicId, reason, closedAt | Regulatory BC (HandedToMunicipality → close StrayCustody) |
| `AdmissionReopenedInError` | Audit | admissionId, patientId, clinicId, occurredAt | Audit log |

### Regulatory BC Events

| Event | Type | Payload | Consumer |
|-------|------|---------|----------|
| `MairieNotificationScheduled` | Audit | notificationId, admissionId, deadline | Audit log + RegulatoryTasks projection |
| `MairieNotificationSent` | Audit | notificationId, sentAt | Audit log (legal proof) |
| `StrayCustodyBegun` | Audit | custodyId, admissionId, deadline | RegulatoryTasks projection |
| `StrayCustodyCancelledOwnerFound` | Audit | custodyId, admissionId | RegulatoryTasks projection |
| `ICADLookupFound` | Integration | lookupId, chipNumber, icadAnimalData | (future: trigger patient link suggestion — V2) |
| `ICADLookupNotFound` | Audit | lookupId, chipNumber | Audit log |
| `ICADLookupFailed` | Audit | lookupId, chipNumber, error | Audit log |

**Total: ~20 events. All justified.**

**Retry strategy:** Symfony Messenger built-in: 3 attempts with exponential backoff, then `failed` queue. Dead-letter queue reviewed manually. No Redis — doctrine transport only in alpha.

---

## L7 — Emergency Admission Form (UX Spec)

### Route

`POST /clinic/{clinicId}/admissions/emergency`
Controller: `Admission\Presentation\Backoffice\EmergencyAdmissionController` (single `__invoke`)

### Form Fields

| Field | Type | Required | Condition |
|-------|------|----------|-----------|
| `intake_channel` | `IntakeChannel` enum select | Always | — |
| `triage_level` | `TriageLevel` enum select | Always | Default: `Emergency` |
| `presenter_name` | string max:255 | Conditional | Required if `intake_channel` ∈ {ThirdParty, Authority, Association, Municipality} |
| `presenter_phone` | E.164 string | Optional | — |
| `presenter_role` | `PresenterRole` enum | Conditional | Required if presenter_name provided |
| `known_animal_id` | UUID hidden | Optional | Pre-filled if navigating from Animal profile |
| `provisional_label` | string max:255 | Conditional | Required if `known_animal_id` === null |
| `observed_species` | string max:64 | Optional | — |
| `observed_color` | string max:64 | Optional | — |

### Behavior: owner absent

If `known_animal_id` is null: `provisional_label` field becomes visible (Stimulus controller toggles). Label: "Description provisoire de l'animal". On success: flash "Patient non identifié créé — obligations légales déclenchées."

### Symfony Form + Turbo

- Wrapped in `<turbo-frame id="emergency-admission-form">`
- Validation error (422): Turbo Frame partial response (form with errors, in-frame replacement)
- Success: Turbo Stream appends new row to `<turbo-frame id="waiting-room-list">` + success flash
- CSRF via `csrf_protection_controller.js`
- `intake_channel` + `triage_level` + `presenter_role` rendered as `ki-select`

### Server-side validation

- `PresenterRequiredForThirdPartyIntake` custom constraint
- `PhoneNumber` VO validates E.164 at construction, Symfony DataTransformer converts form string
- IDOR: controller asserts `$clinicId === $currentClinicContext->getClinicId()`

---

## L8 — Migration Strategy

### Current state

- `src/Context/Animal/` — mature, 100% coverage, **modify in place** (no move)
- `src/Context/Consultation/` — mature, 100% coverage, **refactor in place** (no move)
- `src/Context/Scheduling/` — contains `WaitingRoomEntry` (to delete)
- `src/Context/Patient/` — does not exist → create
- `src/Context/Admission/` — does not exist → create
- `src/Context/Regulatory/` — does not exist → create

### Commit order (DB reset = no incremental migration needed)

**Commit A — `feat(patient): add Patient BC with creation and identity services`**
- Create Patient BC (Tasks 1.1–1.19)
- Run `make ci` — must be green before commit

**Commit B — `feat(animal): add AnimalNameChanged event and @Version`**
- Modify Animal BC (Tasks 2.1–2.6)
- All existing Animal tests pass

**Commit C — `refactor(consultation): replace animalId/ownerId with patientId, add admissionId`**
- Refactor Consultation BC (Tasks 3.1–3.9)
- Note: Consultation tests will require updated fixtures (patientId, admissionId)

**Commit D — `feat(admission): add Admission BC, remove WaitingRoomEntry from Scheduling`**
- Create Admission BC (Tasks 5.1–5.16) + remove WaitingRoomEntry (Tasks 4.1–4.5)
- Update presentation layer waiting room view

**Commit E — `feat(regulatory): add Regulatory BC with legal deadline tracking`**
- Create Regulatory BC (Tasks 6.1–6.13)

**Commit F — `feat(presentation): emergency admission form + scenario fixtures`**
- Emergency admission controller + Twig template
- Foundry fixtures for 4 scenarios
- `make ci` — all green

### Foundry Fixtures — 4 Scenarios

```php
// src/DataFixtures/ — loaded in order

// Scenario 1: Standard (appointment, known animal, known owner)
// PatientEntityFactory::createOne([animalLink set, displayLabel::fromAnimal])
// AdmissionEntityFactory::createOne([intakeChannel=AppointmentBooked, triage=Standard, status=Active])
// ConsultationEntityFactory::createOne([patientId=above, admissionId=above, status=Open])

// Scenario 2: Unidentified — owner never found
// PatientEntityFactory::createOne([animalLink=null, displayLabel::provisional('Berger croisé, sans puce, urgence 09h')])
// AdmissionEntityFactory::createOne([intakeChannel=EmergencyByAuthority, triage=Emergency, status=Closed, closureReason=HandedToMunicipality])
// MairieNotificationEntityFactory::createOne([status=Sent])
// StrayCustodyEntityFactory::createOne([status=ClosedHandedToMunicipality])

// Scenario 3: Unidentified — owner found later
// PatientEntityFactory::createOne([animalLink set after creation, displayLabel::fromAnimal])
// AdmissionEntityFactory::createOne([intakeChannel=EmergencyByThirdParty, triage=Priority, status=Closed, closureReason=ReleasedToOwner])
// StrayCustodyEntityFactory::createOne([status=CancelledOwnerFound])
// MairieNotificationEntityFactory::createOne([status=Sent])

// Scenario 4: Reconciliation
// PatientEntityFactory::createOne([id=sourceId, status=Archived]) ← source patient (merged)
// PatientEntityFactory::createOne([id=targetId, animalLink set]) ← target patient (survives)
// AdmissionEntityFactory::createOne([patientId=targetId]) ← re-pointed after reconciliation
// ConsultationEntityFactory::createOne([patientId=targetId]) ← re-pointed
```

---

## L9 — Cross-Context Impacts

### Known Cross-BC Domain Coupling Violations (deferred)

The following violations are **accepted for this session** and tracked for a future dedicated PR spec (`pr-tech-spec-fix-cross-bc-domain-coupling.md`):

1. `Animal BC` domain layer imports `Clinic\Domain\ValueObject\ClinicId` directly (existing violation — F1)
2. `Patient BC` domain layer imports `Animal\Domain\ValueObject\{SpeciesCode, BreedCode, Sex}` (STRUCT-5 exception — F12)

Before extraction of (2) to `src/Shared/Domain/Catalog/`, the future spec must verify these VOs are **purely catalogue** (no Animal-specific business logic). If Animal behavior is detected, alternative design to be decided at that time.

### Impact on Clinic BC

`clinicId` referenced in Patient, Admission, Regulatory as raw UUID. Each BC defines its own local `ClinicId` VO (existing pattern from project-context.md — BC isolation). No Doctrine relation to Clinic BC.

### Impact on AccessControl BC

New RBAC permissions (pushed via events to AccessControl — never pulled):

| Resource | Actions | Roles |
|----------|---------|-------|
| Patient | create, view, link-to-animal, reconcile, archive | Veterinarian, ASV |
| Admission | open, close, update-triage, update-location | ASV, Veterinarian |
| Regulatory tasks | view, mark-notification-sent | ASV, Admin |
| ICADLookup | trigger | ASV, Veterinarian |

### Impact on Billing BC (future)

`Admission.id` will be the billing anchor. `PartiePayeuse` VO on future `Invoice` aggregate (not on Admission). Municipality billing path: `ClosureReason::HandedToMunicipality` signals facturation collectivité.

### Impact on Scheduling BC

`WaitingRoomEntry` removed entirely from `src/Context/Scheduling/`. Any Scheduling query that provided waiting room data must be replaced by a query to `Admission BC` (`AdmissionReadRepositoryInterface::findActiveByClinicAndLocationStatus($clinicId, InWaitingRoom)`).

### Impact on Translation BC

New translation keys (French UI — frozen vocabulary):

```yaml
# translations/admission+intl-icu.fr.yaml
intake_channel.appointment_booked: "Sur rendez-vous"
intake_channel.walk_in: "Sans rendez-vous"
intake_channel.emergency: "Urgence (propriétaire)"
intake_channel.emergency_by_third_party: "Urgence (tiers)"
intake_channel.emergency_by_authority: "Urgence (autorités)"
intake_channel.emergency_by_association: "Urgence (association)"
intake_channel.emergency_by_municipality: "Urgence (mairie / fourrière)"
intake_channel.transfer_from_other_clinic: "Transfert (autre clinique)"

triage_level.standard: "Standard"
triage_level.priority: "Prioritaire"
triage_level.emergency: "Urgence"
triage_level.vital_emergency: "Urgence vitale"

closure_reason.consultation_completed: "Consultation terminée"
closure_reason.released_to_owner: "Rendu au propriétaire"
closure_reason.handed_to_municipality: "Remis en mairie"
closure_reason.handed_to_association: "Confié à une association"
closure_reason.transferred_to_other_clinic: "Transféré en clinique"
closure_reason.animal_deceased: "Animal décédé"
closure_reason.euthanized: "Euthanasié"
closure_reason.adopted_by_staff: "Adopté par le personnel"
closure_reason.left_against_advice: "Parti contre avis médical"
closure_reason.administrative_error: "Erreur administrative"

# translations/patient+intl-icu.fr.yaml
patient.display_in_waiting_list: "{ label }"
# ASV sees DisplayLabel.value directly — no prefix
# "Patient non identifié" prefix appears only in detail views, never in queue list

location_status.in_waiting_room: "Salle d'attente"
location_status.in_consultation_room: "En consultation"
location_status.in_hospitalization: "Hospitalisé"
location_status.in_surgery: "En chirurgie"
location_status.at_reception: "À la réception"
```

---

## Acceptance Criteria

### Patient Aggregate

- **Given** emergency with no known owner, **when** `PatientCreationService::createUnidentified()` called, **then** Patient exists with `status=Active`, `animalLink=null`, `displayLabel.kind=Provisional`
- **Given** Active unidentified Patient, **when** `PatientIdentityResolutionService::linkToNewlyCreatedAnimal()` called, **then** `animalLink` set, `displayLabel.kind=FromAnimal`, event `PatientLinkedToAnimal` recorded
- **Given** Animal A with an Active linked Patient, **when** `PatientCreationService::createFromAnimal(animalA)` called, **then** `PatientAlreadyExistsForAnimalException` thrown (D3 enforced)
- **Given** `displayLabel.kind=Custom`, **when** `AnimalNameChangedIntegrationEvent` consumed, **then** `displayLabel.value` unchanged
- **Given** two Active Patients in same clinic, **when** `reconcileInto()` called on source, **then** source Patient becomes `Archived`, `PatientReconciledInto` integration event published

### Admission Aggregate

- **Given** any Admission closed with `AdministrativeError`, **when** `reopenInError()` called, **then** succeeds; **given** closed with any other reason, **when** `reopenInError()` called, **then** `AdmissionReopenNotAllowedException` thrown
- **Given** Admission with triage `Emergency`, **when** `escalateTriage(VitalEmergency)` called, **then** succeeds; **when** `deescalateTriage(Standard)` called, **then** `TriageDeescalationNotAllowedException` thrown
- **Given** Admission opened with unidentified patient, **then** `AdmissionOpenedWithUnidentifiedPatient` integration event published

### Regulatory

- **Given** `AdmissionOpenedWithUnidentifiedPatient` received, **then** `MairieNotification` created with `deadline = openedAt + 48h`, AND `StrayCustody` created with `deadline = openedAt + 8 working days`
- **Given** `PatientLinkedToAnimalIntegrationEvent` received while StrayCustody `Active`, **then** `StrayCustody::cancelOwnerFound()` called
- **Given** `AdmissionClosed(HandedToMunicipality)` received, **then** `StrayCustody::closeHandedToMunicipality()` called
- **Given** `FrenchWorkingDayCalculator::addWorkingDays(2026-04-30, 8)`, **then** result = **2026-05-15** (day-by-day: skip May 1 férié → days 1-4 = May 4-7; skip May 8 férié → days 5-7 = May 11-13; skip May 14 Ascension 2026 → day 8 = May 15)

---

## Additional Context

### Dependencies

- `src/Shared/Domain/ValueObject/PhoneNumber.php` — reuse for `Presenter.phone`
- `src/Shared/Domain/ValueObject/EmailAddress.php` — available if needed
- Symfony Messenger doctrine transport (already configured)

### Testing Strategy

- Patient aggregate: unit tests on all invariants + `pullDomainEvents()` after each method
- `PatientCreationService`: unit test with mocked `PatientReadRepositoryInterface` (D3 check)
- `PatientIdentityResolutionService`: unit tests for both link use-cases + reconcileInto
- `FrenchWorkingDayCalculator`: pure unit tests with fixed dates (Easter 2025–2030, May 1, Ascension)
- Regulatory event subscribers: unit tests with mocked repositories
- Doctrine repositories (Patient, Admission): integration tests with Foundry factories
- Mapper symmetry: unit test `toDomain(toEntity($aggregate))` for all new mappers
- Emergency admission form: `WebTestCase` with Turbo Frame header assertions

### Investigation Findings (Step 2 — critical additions to spec)

**[INV-1] WaitingRoomEntry.triageNotes must be preserved in Admission**

`WaitingRoomEntry` has a `triageNotes: ?string` field (free-text ASV notes). This is not in the Admission spec draft — it must be added. Add `triageNotes: ?string` to Admission aggregate and entity.

**[INV-2] Admission must carry ?appointmentId**

`WaitingRoomEntry.linkedAppointmentId` exists for the `AppointmentBooked` intake channel. Admission must carry `?appointmentId: string` (nullable, cross-BC raw UUID to Scheduling BC) to preserve the link. Unique constraint: `UNIQUE (linked_appointment_id) WHERE linked_appointment_id IS NOT NULL` — one Admission per Appointment.

**[INV-3] Scheduling BC deletion scope is larger than anticipated**

All of the following must be deleted as part of Phase 4 (Scheduling cleanup):

*Commands + handlers:* `CreateWaitingRoomEntryFromAppointment`, `CreateWaitingRoomWalkInEntry`, `CloseWaitingRoomEntry`, `StartServiceForWaitingRoomEntry`, `UpdateWaitingRoomTriage`

*Queries + handlers + DTOs:* `GetWaitingRoomEntryDetails`, `ListWaitingRoom`, `WaitingRoomEntryItem`, `WaitingRoomEntryDetailsDTO`

*Ports:* `WaitingRoomReadRepositoryInterface`

*Domain:* `WaitingRoomEntry.php` (aggregate), all `WaitingRoomEntry*` domain events (7 events), all WRE exceptions

*VOs:* `WaitingRoomEntryId`, `WaitingRoomEntryOrigin`, `WaitingRoomEntryStatus`, `WaitingRoomArrivalMode`

*Infrastructure:* `WaitingRoomEntryEntity`, `WaitingRoomEntryMapper`, `DoctrineWaitingRoomEntryRepository`, `DoctrineWaitingRoomReadRepository`

*Presentation (10+ controllers to replace/delete):* `WaitingRoomController`, `WaitingRoomQueueController`, `CreateWalkInController`, `UpdateTriageController`, `StartServiceController`, `StartServiceFromWaitingRoomController`, `WaitingRoomEntryDetailsController`, `CloseWaitingRoomEntryController`, `CloseEntryFromWaitingRoomController`, `CheckInAppointmentController` (creates WRE from appointment), `StartConsultationFromWaitingRoomController`

**[INV-4] SchedulingServiceCoordinatorInterface must be updated (Consultation BC)**

The existing `SchedulingServiceCoordinatorInterface` in `Consultation\Application\Port\` has `ensureWaitingRoomEntryInService(WaitingRoomEntryId, UserId)`. This must be replaced with an Admission-based coordination. New method: `updateAdmissionLocationStatus(AdmissionId $admissionId, string $newLocationStatus, UserId $triggeredBy)`. The command `StartConsultationFromWaitingRoomEntry` becomes `StartConsultationFromAdmission`.

**[INV-5] Event naming — must override BOUNDED_CONTEXT constant**

`AbstractEvent` auto-generates event name as `{BOUNDED_CONTEXT}.{aggregate}.{action}.v{VERSION}`. New events must override `protected const string BOUNDED_CONTEXT = 'patient';` (or `'admission'`, `'regulatory'`). `VERSION` defaults to 1, increment only for breaking changes.

**[INV-6] Doctrine ID pattern confirmed**

All entities use `UuidType::NAME` from `Symfony\Bridge\Doctrine\Types\UuidType`, stored as `Uuid` objects. Mappers convert: `Uuid::fromString($id->toString())` → entity, `$entity->getId()->toRfc4122()` → domain. All new entities must follow this pattern exactly.

**[INV-7] Admission spec additions (L3 update)**

Two fields added to Admission aggregate from INV-1 + INV-2:
```php
private ?string $triageNotes,           // free-text from ASV, from WRE migration
private readonly ?string $appointmentId, // nullable cross-BC ref, from WRE.linkedAppointmentId
```
`updateTriageNotes(string $notes): void` method added to Admission aggregate.

### Notes

- `Patient.clinicId` and `Admission.clinicId` are set at creation and never change — IDOR trivially verifiable
- `PatientReconciledInto` integration event is async — brief eventual consistency window on Admission and Consultation re-pointing is acceptable for alpha
- **Orphaned Patients (alpha accepted):** If `EmergencyAdmissionService` creates a Patient then fails on Admission creation, the Patient BC holds an orphaned Patient (Active, no Admission). No preventive mechanism in alpha. Detection via monitoring v1: query `Patient.status=Active AND no associated Admission after T+5min` — operational concern, not a code concern in this spec.
- `EmergencyAdmissionService` creates Patient then Admission in two separate BC transactions — orphaned Patient on Admission failure is acceptable for alpha; compensating saga is a future concern
- `SpeciesCode`, `BreedCode`, `Sex` VOs imported from Animal BC into Patient BC — acknowledged cross-BC VO coupling (STRUCT-5); document in Patient BC README for future extraction
- ChipLookupService V1 is fully manual — ASV reads chip number from scanner, types it in. No automatic API call to I-CAD. V2 automatic lookup is out of scope.
