<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange;

final readonly class AppointmentItem
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public ?string $ownerId,
        public ?string $animalId,
        public string $practitionerUserId,
        public string $startsAtUtc,
        public int $durationMinutes,
        public string $status,
        public ?string $reason,
        public ?string $notes,
        public ?string $ownerLabel,
        public ?string $ownerPhone,
        public ?string $animalLabel,
        public ?string $animalSpecies,
        public ?string $practitionerLabel,
        public bool $isOrphaned = false,
    ) {
    }

    public function withIsOrphaned(bool $isOrphaned): self
    {
        return new self(
            id: $this->id,
            clinicId: $this->clinicId,
            ownerId: $this->ownerId,
            animalId: $this->animalId,
            practitionerUserId: $this->practitionerUserId,
            startsAtUtc: $this->startsAtUtc,
            durationMinutes: $this->durationMinutes,
            status: $this->status,
            reason: $this->reason,
            notes: $this->notes,
            ownerLabel: $this->ownerLabel,
            ownerPhone: $this->ownerPhone,
            animalLabel: $this->animalLabel,
            animalSpecies: $this->animalSpecies,
            practitionerLabel: $this->practitionerLabel,
            isOrphaned: $isOrphaned,
        );
    }
}
