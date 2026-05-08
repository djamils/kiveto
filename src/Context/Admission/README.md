# Admission Bounded Context

The **Admission BC** manages the intake episode of a patient at the clinic. It replaces the old `WaitingRoomEntry` aggregate and covers all intake channels, the full lifecycle from opening to closure, triage escalation, and location tracking.

## Responsibilities

- Create and manage **Admission** aggregates — one per care episode (hospitalization = 1 Admission, multiple Consultations)
- Track **location status** within the clinic (waiting room → consultation room → hospitalization, etc.)
- Manage **triage escalation** and de-escalation
- Track the **presenter** (person who brought the animal: owner, third party, authority, etc.)
- Trigger cross-BC obligations when an unidentified patient is admitted (→ Regulatory BC)
- Trigger cross-BC billing anchor on closure (future Billing BC)

## Ubiquitous Language

| Term | Definition |
|------|-----------|
| **Admission** | A discrete care episode — opened at intake, closed at discharge/transfer/death |
| **IntakeChannel** | How the patient arrived: `AppointmentBooked`, `WalkIn`, `Emergency`, `EmergencyByThirdParty`, `EmergencyByAuthority`, `EmergencyByAssociation`, `EmergencyByMunicipality`, `TransferFromOtherClinic` |
| **TriageLevel** | Clinical urgency: `Standard` (0) → `Priority` (1) → `Emergency` (2) → `VitalEmergency` (3) |
| **LocationStatus** | Current physical location in the clinic: `InWaitingRoom`, `InConsultationRoom`, `InHospitalization`, `InSurgery`, `AtReception` |
| **Presenter** | The person who physically presented the animal: name, phone, role |
| **PresenterRole** | `Owner`, `ThirdParty`, `Authority`, `Association`, `Municipality`, `Unknown` |
| **ClosureReason** | Why the admission ended: `ConsultationCompleted`, `ReleasedToOwner`, `HandedToMunicipality`, `HandedToAssociation`, `TransferredToOtherClinic`, `AnimalDeceased`, `Euthanized`, `AdoptedByStaff`, `LeftAgainstAdvice`, `AdministrativeError` |

## Business Invariants

- **Triage escalation is monotonic**: `escalateTriage()` requires new level ordinal > current ordinal
- **De-escalation is restricted**: `deescalateTriage()` only allowed from `VitalEmergency` → `Emergency`
- **Reopen only on administrative error**: `reopenInError()` throws unless `closureReason === AdministrativeError`
- **One Admission per Appointment**: `UNIQUE(appointment_id) WHERE appointment_id IS NOT NULL`
- **Admission is the billing anchor**: `admissionId` is non-nullable on `Consultation` — every consultation belongs to an admission episode

## Key Use Cases

1. **Open emergency admission** for an unidentified animal (no owner, no chip) → triggers Regulatory BC obligations
2. **Open admission from appointment** (standard check-in)
3. **Escalate triage** when clinical condition worsens
4. **Update location status** as patient moves through the clinic
5. **Close admission** with a reason (discharge, transfer, death, etc.)
6. **Reopen in error** when an administrative mistake was made

## Domain Events

| Event | Type | When | Consumer |
|-------|------|------|---------|
| `AdmissionOpened` | Domain | On `open()` | Projections |
| `AdmissionOpenedWithUnidentifiedPatient` | Integration | On `open()` when unidentified | Regulatory BC: create MairieNotification + StrayCustody |
| `AdmissionTriageEscalated` | Domain | On `escalateTriage()` | Audit log |
| `AdmissionTriageDeescalated` | Domain | On `deescalateTriage()` | Audit log |
| `AdmissionLocationStatusUpdated` | Domain | On `updateLocationStatus()` | Projections (waiting room live update) |
| `AdmissionClosed` | Domain | On `close()` | Bridge → `AdmissionClosedIntegrationEvent` → Regulatory BC |
| `AdmissionReopenedInError` | Domain | On `reopenInError()` | Audit log |

## Cross-BC Communication

- **Admission publishes** `AdmissionOpenedWithUnidentifiedPatient` integration event → **Regulatory BC** creates legal obligations
- **Admission publishes** `AdmissionClosed` domain event → bridge converts to `AdmissionClosedIntegrationEvent` → **Regulatory BC** closes StrayCustody on `HandedToMunicipality`
- **Admission consumes** `PatientReconciledIntoIntegrationEvent` from Patient BC → re-points `patient_id` on affected admissions
- **Consultation BC** references `admissionId` (non-nullable, cross-BC raw UUID)

## Architecture

```
src/Context/Admission/
├── Domain/
│   ├── Admission.php                              (aggregate)
│   ├── Event/                                     (7 events: 5 domain + 2 integration)
│   ├── Exception/                                 (5 domain exceptions)
│   ├── Repository/AdmissionRepositoryInterface.php
│   └── ValueObject/                               (AdmissionId, ClinicId, IntakeChannel, TriageLevel, ClosureReason, LocationStatus, LocationStatusValue, Presenter, PresenterRole, AdmissionStatus)
├── Application/
│   ├── Command/                                   (OpenEmergencyAdmission, CloseAdmission, UpdateAdmissionTriage)
│   ├── Port/                                      (AdmissionReadRepositoryInterface, PatientCreationPort, AnimalReadRepositoryPort, PatientReadRepositoryPort, MicrochipRegistryLookupPort, ChipLookupResult variants, WaitingRoomItemDto, AdmissionContextDto)
│   ├── Service/                                   (EmergencyAdmissionService, ChipLookupService)
│   └── EventSubscriber/PatientReconciledIntoHandler.php
└── Infrastructure/
    ├── Adapter/                                   (Animal, Patient, Regulatory adapters)
    ├── Messaging/Consumer/AdmissionClosedIntegrationBridge.php
    └── Persistence/Doctrine/                      (AdmissionEntity, AdmissionMapper, DoctrineAdmissionRepository, DoctrineAdmissionReadRepository)
```

## Database Schema

**Table: `admission__admissions`**

| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| clinic_id | UUID | Non-nullable |
| patient_id | UUID | Cross-BC ref (non-nullable) |
| is_patient_identified_at_opening | TINYINT(1) | Legal relevance |
| intake_channel | VARCHAR(32) | Backed enum |
| triage_level | VARCHAR(32) | Backed enum |
| presenter_name / phone / role | VARCHAR NULL | Presenter VO (3 columns) |
| location_status_value | VARCHAR(32) | Backed enum |
| location_status_entered_at | DATETIME | |
| status | VARCHAR(16) | `active` \| `closed` |
| closure_reason | VARCHAR(32) NULL | |
| appointment_id | UUID NULL | Unique (1 admission per appointment) |
| physical_description | TEXT NULL | Free-text from WRE migration |
| triage_notes | TEXT NULL | ASV free-text notes |
| version | INT | `@Version` optimistic lock |
| opened_at | DATETIME | |
| closed_at | DATETIME NULL | |
| created_at / updated_at | DATETIME | |

**Indexes:** `(clinic_id, status)`, `(clinic_id, patient_id)`, `(clinic_id, status, location_status_value)` ← waiting room filter

## Fixture Examples

```php
// Scenario 1 — Standard appointment
AdmissionEntityFactory::new()
    ->withIntakeChannel(IntakeChannel::AppointmentBooked)
    ->withTriage(TriageLevel::Standard)
    ->active()
    ->createOne();

// Scenario 2 — Emergency unidentified, handed to municipality
AdmissionEntityFactory::new()
    ->withIntakeChannel(IntakeChannel::EmergencyByAuthority)
    ->withTriage(TriageLevel::Emergency)
    ->closed(ClosureReason::HandedToMunicipality)
    ->createOne();
```
