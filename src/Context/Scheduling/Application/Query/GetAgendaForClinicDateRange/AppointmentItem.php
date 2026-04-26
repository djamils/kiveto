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
        );
    }
}
