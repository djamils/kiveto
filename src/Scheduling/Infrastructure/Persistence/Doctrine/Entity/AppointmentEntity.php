<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use App\Scheduling\Domain\ValueObject\AppointmentStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'scheduling__appointments')]
#[ORM\Index(name: 'idx_clinic_starts', columns: ['clinic_id', 'starts_at_utc'])]
#[ORM\Index(name: 'idx_clinic_practitioner_starts', columns: ['clinic_id', 'practitioner_user_id', 'starts_at_utc'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
class AppointmentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'owner_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $ownerId;

    #[ORM\Column(name: 'animal_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $animalId;

    #[ORM\Column(name: 'practitioner_user_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $practitionerUserId;

    #[ORM\Column(name: 'starts_at_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAtUtc;

    #[ORM\Column(name: 'duration_minutes', type: 'integer')]
    private int $durationMinutes;

    #[ORM\Column(type: 'string', length: 20, enumType: AppointmentStatus::class)]
    private AppointmentStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    #[ORM\Column(name: 'service_started_at_utc', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $serviceStartedAt;

    #[ORM\Column(name: 'created_at_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

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

    public function getPractitionerUserId(): ?Uuid
    {
        return $this->practitionerUserId;
    }

    public function setPractitionerUserId(?Uuid $practitionerUserId): void
    {
        $this->practitionerUserId = $practitionerUserId;
    }

    public function getStartsAtUtc(): \DateTimeImmutable
    {
        return $this->startsAtUtc;
    }

    public function setStartsAtUtc(\DateTimeImmutable $startsAtUtc): void
    {
        $this->startsAtUtc = $startsAtUtc;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): void
    {
        $this->durationMinutes = $durationMinutes;
    }

    public function getStatus(): AppointmentStatus
    {
        return $this->status;
    }

    public function setStatus(AppointmentStatus $status): void
    {
        $this->status = $status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getServiceStartedAt(): ?\DateTimeImmutable
    {
        return $this->serviceStartedAt;
    }

    public function setServiceStartedAt(?\DateTimeImmutable $serviceStartedAt): void
    {
        $this->serviceStartedAt = $serviceStartedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
