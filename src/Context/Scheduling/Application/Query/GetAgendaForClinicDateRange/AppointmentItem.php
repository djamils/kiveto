<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange;

final readonly class AppointmentItem
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public ?string $linkedAdmissionId,
        public string $practitionerUserId,
        public string $startsAtUtc,
        public int $durationMinutes,
        public string $status,
        public ?string $reason,
        public ?string $notes,
        public ?string $practitionerLabel,
        public bool $isOrphaned = false,
        public ?string $ownerId = null,
        public ?string $ownerLabel = null,
        public ?string $ownerPhone = null,
        public ?string $animalId = null,
        public ?string $animalLabel = null,
        public ?string $animalSpecies = null,
        public ?string $animalBirthDate = null,
        public ?string $animalBreed = null,
    ) {
    }

    public function withIsOrphaned(bool $isOrphaned): self
    {
        return new self(
            id: $this->id,
            clinicId: $this->clinicId,
            linkedAdmissionId: $this->linkedAdmissionId,
            practitionerUserId: $this->practitionerUserId,
            startsAtUtc: $this->startsAtUtc,
            durationMinutes: $this->durationMinutes,
            status: $this->status,
            reason: $this->reason,
            notes: $this->notes,
            practitionerLabel: $this->practitionerLabel,
            isOrphaned: $isOrphaned,
            ownerId: $this->ownerId,
            ownerLabel: $this->ownerLabel,
            ownerPhone: $this->ownerPhone,
            animalId: $this->animalId,
            animalLabel: $this->animalLabel,
            animalSpecies: $this->animalSpecies,
            animalBirthDate: $this->animalBirthDate,
            animalBreed: $this->animalBreed,
        );
    }
}
