<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class AppointmentScheduled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $appointmentId,
        private string $clinicId,
        private ?string $ownerId,
        private ?string $animalId,
        private ?string $practitionerUserId,
        private string $startsAtUtc,
        private int $durationMinutes,
        private ?string $reason,
        private ?string $notes,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->appointmentId;
    }

    public function payload(): array
    {
        return [
            'appointmentId'      => $this->appointmentId,
            'clinicId'           => $this->clinicId,
            'ownerId'            => $this->ownerId,
            'animalId'           => $this->animalId,
            'practitionerUserId' => $this->practitionerUserId,
            'startsAtUtc'        => $this->startsAtUtc,
            'durationMinutes'    => $this->durationMinutes,
            'reason'             => $this->reason,
            'notes'              => $this->notes,
        ];
    }
}
