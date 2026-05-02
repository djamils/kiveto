# Regulatory Bounded Context

The **Regulatory BC** tracks legal obligations triggered by emergency intake of an unidentified animal. It is a purely reactive BC — all aggregates are created by integration event handlers; no direct user interaction.

The BC is **jurisdiction-aware**: deadlines, working-day calendars, and registry validations are resolved through `RegulatoryPolicyInterface`, `WorkingDayCalculatorInterface`, and `JurisdictionResolverInterface`. France-specific implementations live under `Jurisdiction/France/`. V1 ships with the French jurisdiction only.

## Responsibilities

- Create and track **AuthorityNotification** — the legal obligation to notify the competent local authority within a jurisdiction-defined deadline (FR: mairie within 48 calendar hours, Code Rural L211-25)
- Create and track **StrayCustody** — the mandatory holding period before the animal can be handed to the authority (FR: 8 working days)
- Record **MicrochipRegistryLookup** audit entries — chip number queries against a national registry (FR: I-CAD; V1: manual entry by ASV)
- Expose **RegulatoryTasksReadRepository** — returns overdue/upcoming legal tasks per clinic for the dashboard

## Ubiquitous Language

| Term | Definition |
|------|-----------|
| **AuthorityNotification** | Legal obligation: inform the competent authority within a jurisdiction-defined deadline of an unidentified stray animal intake |
| **StrayCustody** | Legal holding period during which the veterinarian holds the animal before possible handover to the authority |
| **MicrochipRegistryLookup** | Audit record of a chip number query against the national registry (V1: manual entry by ASV) |
| **RegulatoryPolicyInterface** | Jurisdiction policy: returns the authority-notification and stray-custody deadlines for an admission opened at a given time |
| **WorkingDayCalculatorInterface** | Pure domain service: computes working days according to a jurisdiction's calendar |
| **JurisdictionResolverInterface** | Resolves `clinicId → ISO 3166-1 alpha-2 country code` (read from `clinic__clinics.country_code`) |
| **FranceRegulatoryPolicy** | French implementation: 48 calendar hours for `AuthorityNotification`, 8 French working days for `StrayCustody` |
| **FrenchWorkingDayCalculator** | French implementation: excludes weekends and jours fériés (fixed + Easter-based) |
| **Jours fériés (FR)** | Fixed: 1 Jan, 1 May, 8 May, 14 Jul, 15 Aug, 1 Nov, 11 Nov, 25 Dec. Easter-based: Lundi de Pâques (+1), Ascension (+39), Lundi de Pentecôte (+50) |

## Business Invariants

- **AuthorityNotification deadline** = `policy.getAuthorityNotificationDeadline(admissionOpenedAt)` (FR: `+48 calendar hours` — NOT working hours, legal text)
- **StrayCustody deadline** = `policy.getStrayCustodyDeadline(admissionOpenedAt)` (FR: 8 French working days, Code Rural L211-25)
- **Owner identification cancels StrayCustody** but does NOT cancel AuthorityNotification
- **Concurrent updates** (e.g. `cancelOwnerFound` + `closeHandedToAuthority` racing) are protected by `@Version` optimistic locking on all 3 aggregates

## Trigger Conditions

| Integration Event | Triggers |
|------------------|---------|
| `AdmissionOpenedWithUnidentifiedPatient` | Create `AuthorityNotification` + `StrayCustody` |
| `PatientLinkedToAnimalIntegrationEvent` | `StrayCustody::cancelOwnerFound()` |
| `AdmissionClosedIntegrationEvent` (reason=HandedToAuthority) | `StrayCustody::closeHandedToAuthority()` |
| `OpenMicrochipRegistryLookup` command | Create `MicrochipRegistryLookup` audit entry |

## FrenchWorkingDayCalculator — Acceptance Criterion

```
addWorkingDays(2026-04-30, 8) = 2026-05-15
```

Day-by-day: May 1 (Fête du Travail) → skip, May 2-3 (weekend) → skip, May 4=day1, May 5=day2, May 6=day3, May 7=day4, May 8 (Victoire 1945) → skip, May 9-10 (weekend) → skip, May 11=day5, May 12=day6, May 13=day7, May 14=Ascension 2026 → skip, May 15=day8 ✓

## Domain Events (all are Audit Events)

| Event | Payload | Purpose |
|-------|---------|---------|
| `AuthorityNotificationScheduled` | notificationId, admissionId, deadline | Legal audit trail |
| `AuthorityNotificationSent` | notificationId, sentAt | Proof of compliance |
| `AuthorityNotificationCancelled` | notificationId, reason | Audit |
| `StrayCustodyBegun` | custodyId, admissionId, deadline | Legal audit trail |
| `StrayCustodyCancelledOwnerFound` | custodyId, admissionId | Owner resolution audit |
| `StrayCustodyClosedHandedToAuthority` | custodyId | Closure audit |
| `MicrochipRegistryLookupInitiated` | lookupId, chipNumber | Audit |
| `MicrochipRegistryLookupFound` / `NotFound` / `Failed` | lookupId, chipNumber, … | Audit |

## Architecture

```
src/Context/Regulatory/
├── Domain/
│   ├── AuthorityNotification.php           (aggregate: Pending → Sent | Cancelled)
│   ├── StrayCustody.php                    (aggregate: Active → CancelledOwnerFound | ClosedHandedToAuthority | Expired)
│   ├── MicrochipRegistryLookup.php         (aggregate: Pending → FoundInICad | NotFoundInICad | LookupFailed)
│   ├── JurisdictionResolverInterface.php   (clinicId → country code)
│   ├── Policy/RegulatoryPolicyInterface.php
│   ├── Service/WorkingDayCalculatorInterface.php
│   ├── Event/                              (10 domain/audit events)
│   ├── Exception/                          (3 domain exceptions)
│   ├── Repository/                         (3 repository interfaces)
│   └── ValueObject/                        (3 ID types, 3 status enums, local ClinicId)
├── Application/
│   ├── Command/OpenMicrochipRegistryLookup/ (command + handler)
│   ├── Port/RegulatoryTasksReadRepositoryInterface.php
│   └── EventSubscriber/                   (3 integration event consumers)
├── Jurisdiction/
│   └── France/
│       ├── FranceRegulatoryPolicy.php       (implements RegulatoryPolicyInterface)
│       ├── FrenchWorkingDayCalculator.php   (implements WorkingDayCalculatorInterface)
│       └── SireNumberValidator.php          (FR-specific registry-number validation)
└── Infrastructure/
    ├── Persistence/Doctrine/               (3 entities, 3 mappers, 4 repositories)
    └── Resolver/ClinicJurisdictionResolver.php (DBAL, reads clinic__clinics.country_code)
```

## Database Schema

**Table: `regulatory__authority_notifications`**

| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| admission_id | UUID | Cross-BC ref |
| patient_id | UUID | Cross-BC ref |
| clinic_id | UUID | |
| status | VARCHAR(16) | `pending` \| `sent` \| `cancelled` |
| deadline | DATETIME | resolved by `RegulatoryPolicyInterface` (FR: `admissionOpenedAt + 48h`) |
| version | INT | `@Version` |
| created_at / updated_at | DATETIME | |

**Table: `regulatory__stray_custodies`** — same columns, status: `active` \| `cancelled_owner_found` \| `closed_handed_to_authority` \| `expired`

**Table: `microchip_registry_lookups`** — id, chip_number, clinic_id, status, icad_animal_data (TEXT NULL), error_message (TEXT NULL), initiated_at, version, created_at, updated_at

## V1 Limitations (deliberate alpha scope)

- **Single jurisdiction wired** — `RegulatoryPolicyInterface` is bound directly to `FranceRegulatoryPolicy` in `config/services.yaml`. Bascule to a tagged iterator + `JurisdictionResolverInterface`-driven selection when a 2nd jurisdiction is added.
- **No automatic registry API call** — V1 is manual chip entry by ASV; `MicrochipRegistryLookup` is audit-only
- **No jours fériés ponts** — deliberately excluded from `FrenchWorkingDayCalculator`
- **No dashboard view yet** — `RegulatoryTasksReadRepositoryInterface` is a stub

## Fixture Examples

```php
// Scenario 2: unidentified → handed to authority
AuthorityNotificationEntityFactory::new()->withStatus(AuthorityNotificationStatus::Sent)->createOne();
StrayCustodyEntityFactory::new()->withStatus(StrayCustodyStatus::ClosedHandedToAuthority)->createOne();

// Scenario 3: owner found
StrayCustodyEntityFactory::new()->withStatus(StrayCustodyStatus::CancelledOwnerFound)->createOne();
AuthorityNotificationEntityFactory::new()->withStatus(AuthorityNotificationStatus::Sent)->createOne();
```
