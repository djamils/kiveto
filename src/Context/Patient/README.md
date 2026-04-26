# Patient Bounded Context

The **Patient BC** manages the stable medical identity of an animal within a clinic. A Patient can exist without a known Animal (emergency intake), and remains the same identity throughout the animal's life at the clinic, independent of ownership changes.

## Responsibilities

- Create and maintain the **Patient** aggregate — the permanent medical identity used in all clinical workflows
- Manage the **DisplayLabel** shown in waiting-room lists and clinical records
- Handle **identity resolution**: linking an unidentified Patient to a known Animal once the owner is identified
- Handle **reconciliation**: merging a duplicate provisional Patient into the canonical one when an already-known animal re-presents

## Ubiquitous Language

| Term | Definition |
|------|-----------|
| **Patient** | The medical identity of an animal within a clinic; stable for the animal's lifetime at this clinic |
| **Unidentified Patient** | A Patient with no linked Animal — created at emergency intake when owner is unknown |
| **Identified Patient** | A Patient linked to a known Animal in the clinic's registry |
| **DisplayLabel** | The label shown in UI lists: provisional description, animal name (from Animal BC), or a custom ASV-defined label |
| **DisplayLabelKind** | `Provisional` (auto-generated at intake) \| `FromAnimal` (synced from Animal name) \| `Custom` (ASV-set, never auto-updated) |
| **AnimalLink** | A cross-BC reference (raw UUID) to an Animal aggregate — set once, immutable |
| **Reconciliation** | Archiving a source Patient and re-pointing all its Admissions and Consultations to a target Patient |

## Business Invariants

- **D3**: At most one **Active** Patient can be linked to a given Animal in a given clinic — enforced by `PatientCreationService` before creation or linking.
- **AnimalLink is set once**: `linkToAnimal()` throws `PatientAlreadyLinkedException` if already linked.
- **Mutation requires Active status**: All mutation methods throw `PatientNotActiveException` if `status !== Active`.
- **Custom DisplayLabel is never overwritten**: When `kind === Custom`, `updateDisplayLabel()` is a no-op — the ASV's custom label takes precedence over Animal name changes.

## Key Use Cases

1. **Create unidentified Patient** at emergency intake (no owner, no chip known)
2. **Create identified Patient** for a known Animal (standard appointment check-in)
3. **Link to animal** once owner is identified after emergency intake
4. **Customize display label** when the ASV wants a specific description in the queue
5. **Reconcile** when an unidentified Patient turns out to be an already-known animal at the clinic

## Domain Events

| Event | When | Consumer |
|-------|------|---------|
| `PatientCreated` | On `createUnidentified()` / `createFromAnimal()` | Projections |
| `PatientLinkedToAnimal` | On `linkToAnimal()` | Projections; bridge → `PatientLinkedToAnimalIntegrationEvent` (Regulatory BC: cancels StrayCustody) |
| `PatientDisplayLabelChanged` | When label updates (kind ≠ Custom) | Projections |
| `PatientDisplayLabelCustomized` | On `customizeDisplayLabel()` | Projections |
| `PatientReconciledInto` | On `reconcileInto()` | Bridge → integration event (Admission + Consultation BC re-point patientId) |
| `PatientArchived` | On `archive()` | Projections |

## Cross-BC Communication

- **Patient publishes** `PatientLinkedToAnimalIntegrationEvent` → consumed by **Regulatory BC** (cancels StrayCustody)
- **Patient publishes** `PatientReconciledIntoIntegrationEvent` → consumed by **Admission BC** and **Consultation BC** (re-point patientId)
- **Patient consumes** `AnimalNameChangedIntegrationEvent` from Animal BC → updates `DisplayLabel` if kind ≠ Custom

## Architecture

```
src/Context/Patient/
├── Domain/
│   ├── Patient.php                               (aggregate)
│   ├── Event/                                    (6 domain + 2 integration events)
│   ├── Exception/                                (5 domain exceptions)
│   ├── Repository/PatientRepositoryInterface.php (save, get, findById, findActiveByAnimalId)
│   └── ValueObject/                              (PatientId, ClinicId, DisplayLabel, DisplayLabelKind, AnimalLink, PatientStatus)
├── Application/
│   ├── Command/                                  (CreateUnidentifiedPatient, CreateIdentifiedPatient)
│   ├── Port/                                     (PatientReadRepositoryInterface, AnimalNameProviderInterface)
│   ├── Service/                                  (PatientCreationService, PatientIdentityResolutionService)
│   └── EventSubscriber/AnimalNameChangedHandler.php
└── Infrastructure/
    ├── Adapter/Animal/DoctrineAnimalNameProvider.php
    └── Persistence/Doctrine/                     (PatientEntity, PatientMapper, DoctrinePatientRepository, DoctrinePatientReadRepository)
```

## Database Schema

**Table: `patient__patients`**

| Column | Type | Notes |
|--------|------|-------|
| id | BINARY(16) | UUID PK |
| clinic_id | BINARY(16) | Non-nullable, never changes |
| status | VARCHAR(16) | `active` \| `archived` |
| display_label_kind | VARCHAR(16) | `provisional` \| `from_animal` \| `custom` |
| display_label_value | VARCHAR(255) | The displayed label |
| animal_link_id | BINARY(16) NULL | Set once via `linkToAnimal()` |
| observed_species | VARCHAR(64) NULL | Free-text at intake (e.g. "chat") |
| observed_color | VARCHAR(64) NULL | Free-text at intake (e.g. "roux") |
| version | INT | Optimistic lock (`@Version`) |
| created_at | DATETIME | |
| updated_at | DATETIME | |

## Fixture Examples

```php
// Unidentified Patient — emergency intake
PatientEntityFactory::new()
    ->withStatus(PatientStatus::Active)
    ->withDisplayLabel(DisplayLabelKind::Provisional, 'Berger croisé, sans puce')
    ->createOne();

// Identified Patient — linked to Animal BC
PatientEntityFactory::new()
    ->withAnimalLinkId($animalId)
    ->withDisplayLabel(DisplayLabelKind::FromAnimal, 'Rex')
    ->createOne();

// Archived Patient — reconciled into another
PatientEntityFactory::new()
    ->withStatus(PatientStatus::Archived)
    ->createOne();
```
