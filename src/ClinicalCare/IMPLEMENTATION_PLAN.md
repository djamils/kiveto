# BC ClinicalCare - Implementation Guide Complete

## 📦 Vue d'ensemble

Le BC **ClinicalCare** gère les consultations vétérinaires avec intégration automatique au BC Scheduling.

**Statut** : 🟡 En cours d'implémentation (Value Objects ✅ créés)

---

## 🎯 Prochaines étapes d'implémentation

Étant donné l'ampleur du travail (~100 fichiers comme Scheduling), voici l'ordre de priorité :

### Phase 1 : Domain Core (Essentiel)
1. ✅ Value Objects (fait - 12 fichiers)
2. ⏳ Domain Events (8 events)
3. ⏳ Consultation Aggregate (le cœur)
4. ⏳ Repository Interface

### Phase 2 : Application (Use Cases)
5. Ports (interfaces anti-corruption - 4 ports)
6. Commands critiques + handlers :
   - StartConsultationFromAppointment
   - StartConsultationFromWaitingRoomEntry
   - AddClinicalNote
   - CloseConsultation
7. Queries + handlers (4 queries)

### Phase 3 : Infrastructure
8. Doctrine Entities (ConsultationEntity, NoteEntity, ActEntity)
9. Mappers (3 mappers)
10. Repositories (Write + Read DBAL)
11. Adapters (4 adapters vers autres BCs)
12. Migration SQL (3 tables)

### Phase 4 : Tests & Config
13. Tests unitaires Domain
14. Tests unitaires Application
15. Fixtures
16. Configuration Symfony (doctrine.yaml, services.yaml, Makefile)

---

## 📋 Structure complète du BC

```
src/ClinicalCare/
├── Domain/
│   ├── Consultation.php                          ⏳ PRIORITÉ 1
│   ├── Repository/
│   │   └── ConsultationRepositoryInterface.php   ⏳
│   ├── ValueObject/
│   │   ├── ConsultationId.php                    ✅
│   │   ├── ConsultationStatus.php                ✅
│   │   ├── ClinicId.php                          ✅
│   │   ├── UserId.php                            ✅
│   │   ├── AppointmentId.php                     ✅
│   │   ├── WaitingRoomEntryId.php                ✅
│   │   ├── OwnerId.php                           ✅
│   │   ├── AnimalId.php                          ✅
│   │   ├── Vitals.php                            ✅
│   │   ├── NoteType.php                          ✅
│   │   ├── ClinicalNoteRecord.php                ✅
│   │   └── PerformedActRecord.php                ✅
│   └── Event/
│       ├── ConsultationStartedFromAppointment.php     ⏳
│       ├── ConsultationStartedFromWaitingRoomEntry.php ⏳
│       ├── ConsultationPatientIdentityAttached.php    ⏳
│       ├── ConsultationChiefComplaintRecorded.php     ⏳
│       ├── ConsultationVitalsRecorded.php             ⏳
│       ├── ConsultationClinicalNoteAdded.php          ⏳
│       ├── ConsultationPerformedActAdded.php          ⏳
│       └── ConsultationClosed.php                     ⏳
│
├── Application/
│   ├── Command/
│   │   ├── StartConsultationFromAppointment/           ⏳ PRIORITÉ 2
│   │   │   ├── StartConsultationFromAppointment.php
│   │   │   └── StartConsultationFromAppointmentHandler.php
│   │   ├── StartConsultationFromWaitingRoomEntry/      ⏳
│   │   ├── AttachPatientIdentityToConsultation/
│   │   ├── RecordChiefComplaint/
│   │   ├── AddClinicalNote/                            ⏳ PRIORITÉ 2
│   │   ├── RecordVitals/
│   │   ├── AddPerformedAct/
│   │   └── CloseConsultation/                          ⏳ PRIORITÉ 2
│   ├── Query/
│   │   ├── GetConsultationDetails/                     ⏳
│   │   ├── ListConsultationsForAnimal/
│   │   ├── GetOpenConsultationsForClinic/
│   │   └── GetConsultationByAppointment/
│   └── Port/
│       ├── PractitionerEligibilityCheckerInterface.php    ⏳ PRIORITÉ 3
│       ├── SchedulingAppointmentContextProviderInterface.php ⏳
│       ├── SchedulingServiceCoordinatorInterface.php      ⏳
│       ├── OwnerExistenceCheckerInterface.php
│       └── AnimalExistenceCheckerInterface.php
│
├── Infrastructure/
│   ├── Persistence/
│   │   └── Doctrine/
│   │       ├── Entity/
│   │       │   ├── ConsultationEntity.php         ⏳ PRIORITÉ 4
│   │       │   ├── ClinicalNoteEntity.php         ⏳
│   │       │   └── PerformedActEntity.php         ⏳
│   │       ├── Mapper/
│   │       │   ├── ConsultationMapper.php         ⏳
│   │       │   ├── ClinicalNoteMapper.php
│   │       │   └── PerformedActMapper.php
│   │       └── Repository/
│   │           ├── DoctrineConsultationRepository.php      ⏳
│   │           └── DoctrineConsultationReadRepository.php  ⏳
│   └── Adapter/
│       ├── AccessControl/
│       │   └── DbalPractitionerEligibilityChecker.php     ⏳
│       └── Scheduling/
│           ├── DbalSchedulingAppointmentContextProvider.php ⏳
│           └── MessengerSchedulingServiceCoordinator.php   ⏳
│
└── README.md                                       ⏳

migrations/ClinicalCare/
└── Version20260201120000.php                       ⏳ PRIORITÉ 5

tests/Unit/ClinicalCare/
├── Domain/
│   └── ConsultationTest.php                        ⏳
└── Application/
    └── Command/
        └── StartConsultationFromAppointmentHandlerTest.php ⏳

fixtures/ClinicalCare/
├── ConsultationFactory.php                         ⏳
└── Story/
    └── ClinicalCareStory.php                       ⏳
```

---

## 💡 Fichiers clés à créer en priorité

### 1. Consultation Aggregate (cœur du système)

**Fichier** : `src/ClinicalCare/Domain/Consultation.php`

Points critiques :
- Extends AggregateRoot
- Méthodes factory : `startFromAppointment()`, `startFromWaitingRoomEntry()`
- Invariants : status transitions, modifications uniquement si OPEN
- Collections : notes[], acts[]
- Events raised à chaque mutation

### 2. Domain Events (8 events)

Pattern identique à Scheduling :
```php
final readonly class ConsultationStartedFromAppointment implements DomainEventInterface
{
    public function __construct(
        public ConsultationId $consultationId,
        public ClinicId $clinicId,
        public AppointmentId $appointmentId,
        public UserId $practitionerUserId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
```

### 3. Commands critiques + Handlers

**StartConsultationFromAppointment** :
- Check eligibility via PractitionerEligibilityChecker
- Get appointment context via SchedulingAppointmentContextProvider
- Ensure service started via SchedulingServiceCoordinator
- Create Consultation aggregate
- Persist via repository

**Pattern handler** :
```php
#[AsMessageHandler]
final readonly class StartConsultationFromAppointmentHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private PractitionerEligibilityCheckerInterface $eligibilityChecker,
        private SchedulingAppointmentContextProviderInterface $appointmentContextProvider,
        private SchedulingServiceCoordinatorInterface $schedulingCoordinator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(StartConsultationFromAppointment $command): string
    {
        // 1. Check eligibility
        // 2. Get appointment context
        // 3. Ensure service started
        // 4. Create consultation
        // 5. Persist
        // 6. Return consultationId
    }
}
```

### 4. Ports (anti-corruption interfaces)

**SchedulingServiceCoordinatorInterface** :
```php
interface SchedulingServiceCoordinatorInterface
{
    public function ensureAppointmentInService(
        AppointmentId $appointmentId,
        UserId $triggeredByUserId,
    ): void;

    public function ensureWaitingRoomEntryInService(
        WaitingRoomEntryId $entryId,
        UserId $triggeredByUserId,
    ): void;

    public function completeAppointment(
        AppointmentId $appointmentId,
        UserId $triggeredByUserId,
    ): void;
}
```

### 5. Adapter Scheduling (implémentation port)

**MessengerSchedulingServiceCoordinator** :
```php
final readonly class MessengerSchedulingServiceCoordinator implements SchedulingServiceCoordinatorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function ensureAppointmentInService(
        AppointmentId $appointmentId,
        UserId $triggeredByUserId,
    ): void {
        try {
            $this->commandBus->dispatch(new \App\Scheduling\Application\Command\StartServiceForAppointment\StartServiceForAppointment(
                appointmentId: $appointmentId->toString(),
                serviceStartedByUserId: $triggeredByUserId->toString(),
            ));
        } catch (\Exception $e) {
            // Already in service or completed = OK, ignore
        }
    }
}
```

---

## 🗄️ Schéma Database

### Table : `clinical_care__consultations`

```sql
CREATE TABLE clinical_care__consultations (
    id BINARY(16) NOT NULL PRIMARY KEY,
    clinic_id BINARY(16) NOT NULL,
    appointment_id BINARY(16) NULL,
    waiting_room_entry_id BINARY(16) NULL,
    owner_id BINARY(16) NULL,
    animal_id BINARY(16) NULL,
    practitioner_user_id BINARY(16) NOT NULL,
    status VARCHAR(20) NOT NULL,
    chief_complaint TEXT NULL,
    summary TEXT NULL,
    weight_kg DECIMAL(6,3) NULL,
    temperature_c DECIMAL(4,2) NULL,
    started_at_utc DATETIME(6) NOT NULL,
    closed_at_utc DATETIME(6) NULL,
    created_at_utc DATETIME(6) NOT NULL,
    updated_at_utc DATETIME(6) NOT NULL,
    INDEX idx_clinic_started (clinic_id, started_at_utc),
    INDEX idx_animal (animal_id),
    INDEX idx_appointment (appointment_id),
    INDEX idx_waiting_entry (waiting_room_entry_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_appointment (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table : `clinical_care__consultation_notes`

```sql
CREATE TABLE clinical_care__consultation_notes (
    id BINARY(16) NOT NULL PRIMARY KEY,
    consultation_id BINARY(16) NOT NULL,
    note_type VARCHAR(30) NOT NULL,
    content TEXT NOT NULL,
    created_at_utc DATETIME(6) NOT NULL,
    created_by_user_id BINARY(16) NOT NULL,
    INDEX idx_consultation_created (consultation_id, created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table : `clinical_care__performed_acts`

```sql
CREATE TABLE clinical_care__performed_acts (
    id BINARY(16) NOT NULL PRIMARY KEY,
    consultation_id BINARY(16) NOT NULL,
    label VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    performed_at_utc DATETIME(6) NOT NULL,
    created_at_utc DATETIME(6) NOT NULL,
    created_by_user_id BINARY(16) NOT NULL,
    INDEX idx_consultation_performed (consultation_id, performed_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## ⚙️ Configuration Symfony

### `config/packages/doctrine.yaml`

Ajouter le mapping :
```yaml
ClinicalCare:
    type: attribute
    is_bundle: false
    dir: '%kernel.project_dir%/src/ClinicalCare/Infrastructure/Persistence/Doctrine/Entity'
    prefix: 'App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity'
    alias: ClinicalCare
```

### `config/packages/doctrine_migrations.yaml`

Ajouter le path :
```yaml
'DoctrineMigrations\ClinicalCare': '%kernel.project_dir%/migrations/ClinicalCare'
```

### `config/services.yaml`

Ajouter les services :
```yaml
# ============================================================================
# BOUNDED CONTEXT: CLINICAL CARE
# ============================================================================

App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface:
    class: App\ClinicalCare\Infrastructure\Persistence\Doctrine\Repository\DoctrineConsultationRepository

App\ClinicalCare\Application\Port\PractitionerEligibilityCheckerInterface:
    class: App\ClinicalCare\Infrastructure\Adapter\AccessControl\DbalPractitionerEligibilityChecker

App\ClinicalCare\Application\Port\SchedulingAppointmentContextProviderInterface:
    class: App\ClinicalCare\Infrastructure\Adapter\Scheduling\DbalSchedulingAppointmentContextProvider

App\ClinicalCare\Application\Port\SchedulingServiceCoordinatorInterface:
    class: App\ClinicalCare\Infrastructure\Adapter\Scheduling\MessengerSchedulingServiceCoordinator

App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper\ConsultationMapper: ~
```

### `Makefile`

Ajouter target :
```makefile
clinical-care-migrations:
	@$(call step,Generating migrations for ClinicalCare...)
	$(Q)$(call run_live,$(SYMFONY) doctrine:migrations:diff --no-interaction --allow-empty-diff --formatted --namespace='DoctrineMigrations\ClinicalCare' --filter-expression='/^clinical_care__/')
	@$(call ok,ClinicalCare migrations generated)
```

---

## 📚 Documentation à créer

1. `README.md` - Vue d'ensemble du BC
2. `INTEGRATION_GUIDE.md` - Comment intégrer avec Scheduling
3. `POLICIES.md` - Détail des policies P1, P2, P3

---

## 🎯 Estimation

- **Temps d'implémentation complète** : ~8-12 heures
- **Fichiers à créer** : ~90-100 fichiers
- **Lignes de code** : ~7,000-8,000 LOC

---

## 🚀 Prochaine action recommandée

**Option 1** : Je crée les fichiers prioritaires (Phase 1 + Phase 2) = ~40 fichiers essentiels

**Option 2** : Vous me dites quel(s) fichier(s) spécifique(s) vous voulez que je crée maintenant

**Option 3** : Je crée un script de génération automatique basé sur ce template

Que préférez-vous ? 🤔
