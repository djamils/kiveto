<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class AppointmentScheduled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $appointmentId,
        private string $clinicId,
        private ?string $linkedAdmissionId,
        private string $practitionerUserId,
        private string $startsAtUtc,
        private int $durationMinutes,
        private ?string $reason,
        private ?string $notes,
        private ?string $ownerId = null,
        private ?string $animalId = null,
        // The member who booked it (null for a booking made by the system)
        private ?string $actorUserId = null,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->appointmentId;
    }

    public function appointmentId(): string
    {
        return $this->appointmentId;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function practitionerUserId(): string
    {
        return $this->practitionerUserId;
    }

    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    public function animalId(): ?string
    {
        return $this->animalId;
    }

    public function actorUserId(): ?string
    {
        return $this->actorUserId;
    }

    public function payload(): array
    {
        return [
            'appointmentId'      => $this->appointmentId,
            'clinicId'           => $this->clinicId,
            'linkedAdmissionId'  => $this->linkedAdmissionId,
            'practitionerUserId' => $this->practitionerUserId,
            'startsAtUtc'        => $this->startsAtUtc,
            'durationMinutes'    => $this->durationMinutes,
            'reason'             => $this->reason,
            'notes'              => $this->notes,
            'ownerId'            => $this->ownerId,
            'animalId'           => $this->animalId,
            'actorUserId'        => $this->actorUserId,
        ];
    }
}
