# BC ClinicalCare - Guide complet d'implémentation

## 📊 Résumé de l'état actuel

### ✅ Complété : 28 fichiers (Phase 1 - Domain Core)

**Domain Layer complet :**
- 12 Value Objects ✅
- 8 Domain Events ✅
- 1 Consultation Aggregate ✅
- 1 Repository Interface ✅
- 6 Ports (interfaces anti-corruption) ✅

**Le cœur du BC est fonctionnel** ! Il reste principalement :
- Application Layer (Commands/Queries + Handlers)
- Infrastructure (Entities, Repositories, Adapters)
- Configuration & Tests

---

## 🎯 Fichiers restants prioritaires (~70 fichiers)

### Application - Commands (8 commandes × 2 fichiers = 16 fichiers)

#### 1. StartConsultationFromAppointment ⭐ CRITIQUE

**Command** : `src/ClinicalCare/Application/Command/StartConsultationFromAppointment/StartConsultationFromAppointment.php`

```php
<?php
declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\StartConsultationFromAppointment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class StartConsultationFromAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
        public string $startedByUserId,
    ) {
    }
}
```

**Handler** : `...Handler.php`

```php
<?php
declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\StartConsultationFromAppointment;

use App\ClinicalCare\Application\Port\PractitionerEligibilityCheckerInterface;
use App\ClinicalCare\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\ClinicalCare\Application\Port\SchedulingServiceCoordinatorInterface;
use App\ClinicalCare\Domain\Consultation;
use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\AnimalId;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\ClinicalCare\Domain\ValueObject\ClinicId;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\OwnerId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
        $appointmentId = AppointmentId::fromString($command->appointmentId);
        $startedByUserId = UserId::fromString($command->startedByUserId);
        $now = $this->clock->now();

        // 1. Get appointment context
        $appointmentContext = $this->appointmentContextProvider->getAppointmentContext($appointmentId);
        $clinicId = ClinicId::fromString($appointmentContext->clinicId);

        // 2. Check eligibility (VETERINARY role required)
        if (!$this->eligibilityChecker->isEligibleForClinicAt(
            $startedByUserId,
            $clinicId,
            $now,
            ['VETERINARY'],
        )) {
            throw new \DomainException('User is not eligible as practitioner for this clinic');
        }

        // 3. Check intake requirement (unless EMERGENCY bypass)
        $isEmergency = $appointmentContext->arrivalMode === 'EMERGENCY';
        if (!$isEmergency && null === $appointmentContext->linkedWaitingRoomEntryId) {
            throw new \DomainException('Appointment must be checked-in before starting consultation (waiting room entry required)');
        }

        // 4. Ensure appointment is in service
        $this->schedulingCoordinator->ensureAppointmentInService($appointmentId, $startedByUserId);

        // 5. Create consultation
        $consultationId = ConsultationId::generate();
        $ownerId = $appointmentContext->ownerId ? OwnerId::fromString($appointmentContext->ownerId) : null;
        $animalId = $appointmentContext->animalId ? AnimalId::fromString($appointmentContext->animalId) : null;

        $consultation = Consultation::startFromAppointment(
            $consultationId,
            $clinicId,
            $appointmentId,
            $startedByUserId,
            $ownerId,
            $animalId,
            $now,
        );

        // 6. Persist
        $this->consultations->save($consultation);

        return $consultationId->toString();
    }
}
```

#### 2. StartConsultationFromWaitingRoomEntry ⭐ CRITIQUE

Même pattern, remplacer `appointmentId` par `waitingRoomEntryId`.

#### 3-8. Autres Commands (patterns similaires)

- **RecordChiefComplaint** : Simple, juste `consultationId + chiefComplaint`
- **AddClinicalNote** : `consultationId + noteType + content + createdByUserId`
- **RecordVitals** : `consultationId + weightKg + temperatureC?`
- **AddPerformedAct** : `consultationId + label + quantity + performedAt`
- **AttachPatientIdentity** : `consultationId + ownerId? + animalId?`
- **CloseConsultation** : `consultationId + closedByUserId + summary?` + appeler `schedulingCoordinator->completeAppointment()`

---

### Application - Queries (4 queries × 3 fichiers = 12 fichiers)

#### Pattern Query

**Query** : `GetConsultationDetails.php`
```php
final readonly class GetConsultationDetails implements QueryInterface
{
    public function __construct(
        public string $consultationId,
    ) {
    }
}
```

**DTO** : `ConsultationDetailsDTO.php`
```php
final readonly class ConsultationDetailsDTO
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $status,
        // ... tous les champs
        public array $notes,  // Array<NoteItemDTO>
        public array $acts,   // Array<ActItemDTO>
    ) {
    }
}
```

**Handler** : Utilise `DoctrineConsultationReadRepository` (DBAL)

---

### Infrastructure - Entities (3 fichiers)

#### ConsultationEntity.php

```php
<?php
declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'clinical_care__consultations')]
class ConsultationEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16)]
    private string $id;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $clinicId;

    #[ORM\Column(type: 'binary', length: 16, nullable: true)]
    private ?string $appointmentId = null;

    #[ORM\Column(type: 'binary', length: 16, nullable: true)]
    private ?string $waitingRoomEntryId = null;

    #[ORM\Column(type: 'binary', length: 16, nullable: true)]
    private ?string $ownerId = null;

    #[ORM\Column(type: 'binary', length: 16, nullable: true)]
    private ?string $animalId = null;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $practitionerUserId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $chiefComplaint = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 3, nullable: true)]
    private ?string $weightKg = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, nullable: true)]
    private ?string $temperatureC = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAtUtc;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAtUtc = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAtUtc;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAtUtc;

    // Getters/Setters...
}
```

#### ClinicalNoteEntity.php & PerformedActEntity.php

Pattern similaire, tables séparées.

---

### Infrastructure - Mappers (3 fichiers)

#### ConsultationMapper.php

```php
<?php
declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper;

use App\ClinicalCare\Domain\Consultation;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ConsultationMapper
{
    public function __construct(
        private ClinicalNoteMapper $noteMapper,
        private PerformedActMapper $actMapper,
    ) {
    }

    public function toEntity(Consultation $consultation): ConsultationEntity
    {
        $entity = new ConsultationEntity();
        $entity->setId(Uuid::fromString($consultation->getId()->toString())->toBinary());
        // ... map tous les champs
        return $entity;
    }

    public function toDomain(ConsultationEntity $entity, array $noteEntities, array $actEntities): Consultation
    {
        // Reconstitute aggregate
        $notes = array_map($this->noteMapper->toDomain(...), $noteEntities);
        $acts = array_map($this->actMapper->toDomain(...), $actEntities);

        return Consultation::reconstitute(
            // ... all parameters
            $notes,
            $acts,
        );
    }
}
```

---

### Infrastructure - Repositories (2 fichiers)

#### DoctrineConsultationRepository.php (Write)

```php
<?php
declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicalCare\Domain\Consultation;
use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper\ConsultationMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineConsultationRepository implements ConsultationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConsultationMapper $mapper,
    ) {
    }

    public function save(Consultation $consultation): void
    {
        $entity = $this->mapper->toEntity($consultation);
        $this->em->persist($entity);
        $this->em->flush();

        // Persist notes & acts entities separately
        // ...
    }

    public function findById(ConsultationId $id): ?Consultation
    {
        // Find entity + notes + acts, map to domain
        // ...
    }
}
```

#### DoctrineConsultationReadRepository.php (DBAL)

Queries DBAL optimisées pour les 4 queries.

---

### Infrastructure - Adapters (3 fichiers critiques)

#### MessengerSchedulingServiceCoordinator.php ⭐ CRITIQUE

```php
<?php
declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Adapter\Scheduling;

use App\ClinicalCare\Application\Port\SchedulingServiceCoordinatorInterface;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
use App\Shared\Application\Bus\CommandBusInterface;

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
            $this->commandBus->dispatch(
                new \App\Scheduling\Application\Command\StartServiceForAppointment\StartServiceForAppointment(
                    appointmentId: $appointmentId->toString(),
                    serviceStartedByUserId: $triggeredByUserId->toString(),
                )
            );
        } catch (\Exception) {
            // Already in service or completed = OK, ignore
        }
    }

    public function ensureWaitingRoomEntryInService(
        WaitingRoomEntryId $entryId,
        UserId $triggeredByUserId,
    ): void {
        try {
            $this->commandBus->dispatch(
                new \App\Scheduling\Application\Command\StartServiceForWaitingRoomEntry\StartServiceForWaitingRoomEntry(
                    waitingRoomEntryId: $entryId->toString(),
                    serviceStartedByUserId: $triggeredByUserId->toString(),
                )
            );
        } catch (\Exception) {
            // Ignore
        }
    }

    public function completeAppointment(
        AppointmentId $appointmentId,
        UserId $triggeredByUserId,
    ): void {
        try {
            $this->commandBus->dispatch(
                new \App\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment(
                    appointmentId: $appointmentId->toString(),
                    completedByUserId: $triggeredByUserId->toString(),
                )
            );
        } catch (\Exception) {
            // Ignore
        }
    }
}
```

#### DbalPractitionerEligibilityChecker.php

Copie de `DbalMembershipEligibilityChecker` du BC Scheduling.

#### DbalSchedulingAppointmentContextProvider.php

Query DBAL sur `scheduling__appointments` + join `scheduling__waiting_room_entries`.

---

### Migration SQL

**`migrations/ClinicalCare/Version20260201120000.php`**

```php
public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE clinical_care__consultations (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $this->addSql('CREATE TABLE clinical_care__consultation_notes (...)');
    $this->addSql('CREATE TABLE clinical_care__performed_acts (...)');
}
```

---

### Configuration Symfony

#### `config/packages/doctrine.yaml`

```yaml
ClinicalCare:
    type: attribute
    is_bundle: false
    dir: '%kernel.project_dir%/src/ClinicalCare/Infrastructure/Persistence/Doctrine/Entity'
    prefix: 'App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity'
    alias: ClinicalCare
```

#### `config/packages/doctrine_migrations.yaml`

```yaml
'DoctrineMigrations\ClinicalCare': '%kernel.project_dir%/migrations/ClinicalCare'
```

#### `config/services.yaml`

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
App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper\ClinicalNoteMapper: ~
App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper\PerformedActMapper: ~
```

#### `Makefile`

```makefile
clinical-care-migrations:
	@$(call step,Generating migrations for ClinicalCare...)
	$(Q)$(call run_live,$(SYMFONY) doctrine:migrations:diff --no-interaction --allow-empty-diff --formatted --namespace='DoctrineMigrations\ClinicalCare' --filter-expression='/^clinical_care__/')
	@$(call ok,ClinicalCare migrations generated)

# Ajouter dans target migrations:
migrations: ... clinical-care-migrations ...
```

---

## 🎯 Résumé final

### Déjà créé : 28 fichiers ✅

Le **Domain Layer** est complet et solide !

### Reste à créer : ~70 fichiers

- 16 Commands + Handlers (8 commands)
- 12 Queries + DTOs + Handlers (4 queries)
- 3 Entities Doctrine
- 3 Mappers
- 2 Repositories
- 3 Adapters
- 1 Migration
- Tests (optionnel MVP)
- Fixtures (optionnel MVP)
- README.md

**Avec ces templates, vous pouvez créer tous les fichiers restants** en suivant les patterns de Scheduling.

---

**Le BC ClinicalCare aura ~100 fichiers au total, similaire à Scheduling.**

Tous les patterns et templates sont fournis ci-dessus ! 🚀
