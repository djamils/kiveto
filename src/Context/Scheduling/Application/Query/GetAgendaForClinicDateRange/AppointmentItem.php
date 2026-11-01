<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;

final readonly class AppointmentItem
{
    /** Holds its slot (planned or pending): busy on the agenda, counted as to come. */
    public bool $occupiesSlot;

    /** Booked online, waiting for the clinic's confirmation (« à confirmer »). */
    public bool $isPending;

    /** Cancelled or declined: shown as cancelled, never counted. */
    public bool $isWithdrawn;

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
        // When a pending request is auto-declined (UTC, `Y-m-d H:i:s`); null otherwise.
        public ?string $pendingExpiresAtUtc = null,
    ) {
        $appointmentStatus  = AppointmentStatus::from($status);
        $this->occupiesSlot = $appointmentStatus->occupiesSlot();
        $this->isPending    = $appointmentStatus->isPending();
        $this->isWithdrawn  = $appointmentStatus->isWithdrawn();
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
            pendingExpiresAtUtc: $this->pendingExpiresAtUtc,
        );
    }
}
