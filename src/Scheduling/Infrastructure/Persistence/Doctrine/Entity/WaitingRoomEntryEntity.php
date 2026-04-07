<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryOrigin;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_clinic_status', columns: ['clinic_id', 'status'])]
#[ORM\Index(name: 'idx_linked_appointment', columns: ['linked_appointment_id'])]
#[ORM\UniqueConstraint(name: 'uniq_linked_appointment', columns: ['linked_appointment_id'])]
class WaitingRoomEntryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 20, enumType: WaitingRoomEntryOrigin::class)]
    private WaitingRoomEntryOrigin $origin;

    #[ORM\Column(name: 'arrival_mode', type: 'string', length: 20, enumType: WaitingRoomArrivalMode::class)]
    private WaitingRoomArrivalMode $arrivalMode;

    #[ORM\Column(name: 'linked_appointment_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $linkedAppointmentId;

    #[ORM\Column(name: 'owner_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $ownerId;

    #[ORM\Column(name: 'animal_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $animalId;

    #[ORM\Column(name: 'found_animal_description', type: 'text', nullable: true)]
    private ?string $foundAnimalDescription;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $priority;

    #[ORM\Column(name: 'triage_notes', type: 'text', nullable: true)]
    private ?string $triageNotes;

    #[ORM\Column(type: 'string', length: 20, enumType: WaitingRoomEntryStatus::class)]
    private WaitingRoomEntryStatus $status;

    #[ORM\Column(name: 'arrived_at_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $arrivedAtUtc;

    #[ORM\Column(name: 'called_at_utc', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $calledAtUtc;

    #[ORM\Column(name: 'service_started_at_utc', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $serviceStartedAtUtc;

    #[ORM\Column(name: 'closed_at_utc', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAtUtc;

    #[ORM\Column(name: 'called_by_user_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $calledByUserId;

    #[ORM\Column(name: 'service_started_by_user_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $serviceStartedByUserId;

    #[ORM\Column(name: 'closed_by_user_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $closedByUserId;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getOrigin(): WaitingRoomEntryOrigin
    {
        return $this->origin;
    }

    public function setOrigin(WaitingRoomEntryOrigin $origin): void
    {
        $this->origin = $origin;
    }

    public function getArrivalMode(): WaitingRoomArrivalMode
    {
        return $this->arrivalMode;
    }

    public function setArrivalMode(WaitingRoomArrivalMode $arrivalMode): void
    {
        $this->arrivalMode = $arrivalMode;
    }

    public function getLinkedAppointmentId(): ?Uuid
    {
        return $this->linkedAppointmentId;
    }

    public function setLinkedAppointmentId(?Uuid $linkedAppointmentId): void
    {
        $this->linkedAppointmentId = $linkedAppointmentId;
    }

    public function getOwnerId(): ?Uuid
    {
        return $this->ownerId;
    }

    public function setOwnerId(?Uuid $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getAnimalId(): ?Uuid
    {
        return $this->animalId;
    }

    public function setAnimalId(?Uuid $animalId): void
    {
        $this->animalId = $animalId;
    }

    public function getFoundAnimalDescription(): ?string
    {
        return $this->foundAnimalDescription;
    }

    public function setFoundAnimalDescription(?string $foundAnimalDescription): void
    {
        $this->foundAnimalDescription = $foundAnimalDescription;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getTriageNotes(): ?string
    {
        return $this->triageNotes;
    }

    public function setTriageNotes(?string $triageNotes): void
    {
        $this->triageNotes = $triageNotes;
    }

    public function getStatus(): WaitingRoomEntryStatus
    {
        return $this->status;
    }

    public function setStatus(WaitingRoomEntryStatus $status): void
    {
        $this->status = $status;
    }

    public function getArrivedAtUtc(): \DateTimeImmutable
    {
        return $this->arrivedAtUtc;
    }

    public function setArrivedAtUtc(\DateTimeImmutable $arrivedAtUtc): void
    {
        $this->arrivedAtUtc = $arrivedAtUtc;
    }

    public function getCalledAtUtc(): ?\DateTimeImmutable
    {
        return $this->calledAtUtc;
    }

    public function setCalledAtUtc(?\DateTimeImmutable $calledAtUtc): void
    {
        $this->calledAtUtc = $calledAtUtc;
    }

    public function getServiceStartedAtUtc(): ?\DateTimeImmutable
    {
        return $this->serviceStartedAtUtc;
    }

    public function setServiceStartedAtUtc(?\DateTimeImmutable $serviceStartedAtUtc): void
    {
        $this->serviceStartedAtUtc = $serviceStartedAtUtc;
    }

    public function getClosedAtUtc(): ?\DateTimeImmutable
    {
        return $this->closedAtUtc;
    }

    public function setClosedAtUtc(?\DateTimeImmutable $closedAtUtc): void
    {
        $this->closedAtUtc = $closedAtUtc;
    }

    public function getCalledByUserId(): ?Uuid
    {
        return $this->calledByUserId;
    }

    public function setCalledByUserId(?Uuid $calledByUserId): void
    {
        $this->calledByUserId = $calledByUserId;
    }

    public function getServiceStartedByUserId(): ?Uuid
    {
        return $this->serviceStartedByUserId;
    }

    public function setServiceStartedByUserId(?Uuid $serviceStartedByUserId): void
    {
        $this->serviceStartedByUserId = $serviceStartedByUserId;
    }

    public function getClosedByUserId(): ?Uuid
    {
        return $this->closedByUserId;
    }

    public function setClosedByUserId(?Uuid $closedByUserId): void
    {
        $this->closedByUserId = $closedByUserId;
    }
}
