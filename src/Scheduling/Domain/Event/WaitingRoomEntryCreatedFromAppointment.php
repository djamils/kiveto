<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class WaitingRoomEntryCreatedFromAppointment extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $waitingRoomEntryId,
        private string $clinicId,
        private string $linkedAppointmentId,
        private ?string $ownerId,
        private ?string $animalId,
        private string $arrivalMode,
        private int $priority,
        private string $arrivedAtUtc,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->waitingRoomEntryId;
    }

    public function payload(): array
    {
        return [
            'waitingRoomEntryId'  => $this->waitingRoomEntryId,
            'clinicId'            => $this->clinicId,
            'linkedAppointmentId' => $this->linkedAppointmentId,
            'ownerId'             => $this->ownerId,
            'animalId'            => $this->animalId,
            'arrivalMode'         => $this->arrivalMode,
            'priority'            => $this->priority,
            'arrivedAtUtc'        => $this->arrivedAtUtc,
        ];
    }
}
