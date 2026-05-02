# Regulatory Bounded Context

The **Regulatory BC** tracks French legal obligations triggered by emergency intake of an unidentified animal. It is a purely reactive BC — all aggregates are created by integration event handlers; no direct user interaction.

## Responsibilities

- Create and track **MairieNotification** — the legal obligation to notify the local mairie within 48 calendar hours (Code Rural L211-25)
- Create and track **StrayCustody** — the 8 working-day mandatory holding period before the animal can be handed to the municipality
- Record **MicrochipRegistryLookup** audit entries — chip number queries for unidentified animals (V1: manual entry by ASV)
- Expose **RegulatoryTasksReadRepository** — returns overdue/upcoming legal tasks per clinic for the dashboard

## Ubiquitous Language

| Term | Definition |
|------|-----------|
| **MairieNotification** | Legal obligation: inform the town hall within 48 calendar hours of an unidentified stray animal intake |
| **StrayCustody** | Legal holding period: 8 working days during which the veterinarian holds the animal before possible handover to the municipality |
| **MicrochipRegistryLookup** | Audit record of a chip number query against the national I-CAD registry (V1: manual entry by ASV) |
| **FrenchWorkingDayCalculator** | Pure domain service: computes French working days excluding weekends and jours fériés (fixed + Easter-based) |
| **Jours fériés** | Fixed: 1 Jan, 1 May, 8 May, 14 Jul, 15 Aug, 1 Nov, 11 Nov, 25 Dec. Easter-based: Lundi de Pâques (+1), Ascension (+39), Lundi de Pentecôte (+50) |

## Business Invariants

- **MairieNotification deadline** = `admissionOpenedAt + 48 calendar hours` (NOT working hours — legal text)
- **StrayCustody deadline** = `FrenchWorkingDayCalculator.addWorkingDays(admissionOpenedAt, 8)` (Code Rural L211-25)
- **Owner identification cancels StrayCustody** but does NOT cancel MairieNotification
- **Concurrent updates** (e.g. `cancelOwnerFound` + `closeHandedToMunicipality` racing) are protected by `@Version` optimistic locking on all 3 aggregates

## Trigger Conditions

| Integration Event | Triggers |
|------------------|---------|
| `AdmissionOpenedWithUnidentifiedPatient` | Create `MairieNotification` + `StrayCustody` |
| `PatientLinkedToAnimalIntegrationEvent` | `StrayCustody::cancelOwnerFound()` |
| `AdmissionClosedIntegrationEvent` (reason=HandedToMunicipality) | `StrayCustody::closeHandedToMunicipality()` |
| `OpenMicrochipRegistryLookup` command | Create `MicrochipRegistryLookup` audit entry |

## FrenchWorkingDayCalculator — Acceptance Criterion

```
addWorkingDays(2026-04-30, 8) = 2026-05-15
```

Day-by-day: May 1 (Fête du Travail) → skip, May 2-3 (weekend) → skip, May 4=day1, May 5=day2, May 6=day3, May 7=day4, May 8 (Victoire 1945) → skip, May 9-10 (weekend) → skip, May 11=day5, May 12=day6, May 13=day7, May 14=Ascension 2026 → skip, May 15=day8 ✓

## Domain Events (all are Audit Events)

| Event | Payload | Purpose |
|-------|---------|---------|
| `MairieNotificationScheduled` | notificationId, admissionId, deadline | Legal audit trail |
| `MairieNotificationSent` | notificationId, sentAt | Proof of compliance |
| `MairieNotificationCancelled` | notificationId, reason | Audit |
| `StrayCustodyBegun` | custodyId, admissionId, deadline | Legal audit trail |
| `StrayCustodyCancelledOwnerFound` | custodyId, admissionId | Owner resolution audit |
| `StrayCustodyClosedHandedToMunicipality` | custodyId | Closure audit |
| `MicrochipRegistryLookupInitiated` | lookupId, chipNumber | Audit |
| `MicrochipRegistryLookupFound` / `NotFound` / `Failed` | lookupId, chipNumber, … | Audit |

## Architecture

```
src/Context/Regulatory/
├── Domain/
│   ├── MairieNotification.php              (aggregate: Pending → Sent | Cancelled)
│   ├── StrayCustody.php                    (aggregate: Active → CancelledOwnerFound | ClosedHandedToMunicipality | Expired)
│   ├── MicrochipRegistryLookup.php         (aggregate: Pending → FoundInICad | NotFoundInICad | LookupFailed)
│   ├── Event/                              (10 domain/audit events)
│   ├── Exception/                          (3 domain exceptions)
│   ├── Repository/                         (3 repository interfaces)
│   ├── Service/FrenchWorkingDayCalculator.php
│   └── ValueObject/                        (3 ID types, 3 status enums, local ClinicId)
├── Application/
│   ├── Command/OpenMicrochipRegistryLookup/ (command + handler)
│   ├── Port/RegulatoryTasksReadRepositoryInterface.php
│   └── EventSubscriber/                   (3 integration event consumers)
└── Infrastructure/
    └── Persistence/Doctrine/               (3 entities, 3 mappers, 4 repositories)
```

## Database Schema

**Table: `regulatory__mairie_notifications`**

| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| admission_id | UUID | Cross-BC ref |
| patient_id | UUID | Cross-BC ref |
| clinic_id | UUID | |
| status | VARCHAR(16) | `pending` \| `sent` \| `cancelled` |
| deadline | DATETIME | `admissionOpenedAt + 48h` |
| version | INT | `@Version` |
| created_at / updated_at | DATETIME | |

**Table: `regulatory__stray_custodies`** — same columns, status: `active` \| `cancelled_owner_found` \| `closed_handed_to_municipality` \| `expired`

**Table: `microchip_registry_lookups`** — id, chip_number, clinic_id, status, icad_animal_data (TEXT NULL), error_message (TEXT NULL), initiated_at, version, created_at, updated_at

## V1 Limitations (deliberate alpha scope)

- **No automatic I-CAD API call** — V1 is manual chip entry by ASV; `MicrochipRegistryLookup` is audit-only
- **No jours fériés ponts** — deliberately excluded from `FrenchWorkingDayCalculator`
- **No dashboard view yet** — `RegulatoryTasksReadRepositoryInterface` is a stub

## Fixture Examples

```php
// Scenario 2: unidentified → handed to municipality
MairieNotificationEntityFactory::new()->withStatus(MairieNotificationStatus::Sent)->createOne();
StrayCustodyEntityFactory::new()->withStatus(StrayCustodyStatus::ClosedHandedToMunicipality)->createOne();

// Scenario 3: owner found
StrayCustodyEntityFactory::new()->withStatus(StrayCustodyStatus::CancelledOwnerFound)->createOne();
MairieNotificationEntityFactory::new()->withStatus(MairieNotificationStatus::Sent)->createOne();
```
