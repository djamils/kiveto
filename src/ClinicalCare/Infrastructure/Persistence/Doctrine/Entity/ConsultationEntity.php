<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_clinic_started', columns: ['clinic_id', 'started_at_utc'])]
#[ORM\Index(name: 'idx_animal', columns: ['animal_id'])]
#[ORM\Index(name: 'idx_appointment', columns: ['appointment_id'])]
#[ORM\Index(name: 'idx_waiting_entry', columns: ['waiting_room_entry_id'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'unique_appointment', columns: ['appointment_id'])]
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

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): string
    {
        return $this->clinicId;
    }

    public function setClinicId(string $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getAppointmentId(): ?string
    {
        return $this->appointmentId;
    }

    public function setAppointmentId(?string $appointmentId): void
    {
        $this->appointmentId = $appointmentId;
    }

    public function getWaitingRoomEntryId(): ?string
    {
        return $this->waitingRoomEntryId;
    }

    public function setWaitingRoomEntryId(?string $waitingRoomEntryId): void
    {
        $this->waitingRoomEntryId = $waitingRoomEntryId;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getAnimalId(): ?string
    {
        return $this->animalId;
    }

    public function setAnimalId(?string $animalId): void
    {
        $this->animalId = $animalId;
    }

    public function getPractitionerUserId(): string
    {
        return $this->practitionerUserId;
    }

    public function setPractitionerUserId(string $practitionerUserId): void
    {
        $this->practitionerUserId = $practitionerUserId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getChiefComplaint(): ?string
    {
        return $this->chiefComplaint;
    }

    public function setChiefComplaint(?string $chiefComplaint): void
    {
        $this->chiefComplaint = $chiefComplaint;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): void
    {
        $this->summary = $summary;
    }

    public function getWeightKg(): ?string
    {
        return $this->weightKg;
    }

    public function setWeightKg(?string $weightKg): void
    {
        $this->weightKg = $weightKg;
    }

    public function getTemperatureC(): ?string
    {
        return $this->temperatureC;
    }

    public function setTemperatureC(?string $temperatureC): void
    {
        $this->temperatureC = $temperatureC;
    }

    public function getStartedAtUtc(): \DateTimeImmutable
    {
        return $this->startedAtUtc;
    }

    public function setStartedAtUtc(\DateTimeImmutable $startedAtUtc): void
    {
        $this->startedAtUtc = $startedAtUtc;
    }

    public function getClosedAtUtc(): ?\DateTimeImmutable
    {
        return $this->closedAtUtc;
    }

    public function setClosedAtUtc(?\DateTimeImmutable $closedAtUtc): void
    {
        $this->closedAtUtc = $closedAtUtc;
    }

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function setCreatedAtUtc(\DateTimeImmutable $createdAtUtc): void
    {
        $this->createdAtUtc = $createdAtUtc;
    }

    public function getUpdatedAtUtc(): \DateTimeImmutable
    {
        return $this->updatedAtUtc;
    }

    public function setUpdatedAtUtc(\DateTimeImmutable $updatedAtUtc): void
    {
        $this->updatedAtUtc = $updatedAtUtc;
    }
}
