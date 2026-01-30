<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class WaitingRoomWalkInEntryCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $waitingRoomEntryId,
        private string $clinicId,
        private ?string $ownerId,
        private ?string $animalId,
        private ?string $foundAnimalDescription,
        private string $arrivalMode,
        private int $priority,
        private ?string $triageNotes,
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
            'waitingRoomEntryId'      => $this->waitingRoomEntryId,
            'clinicId'                => $this->clinicId,
            'ownerId'                 => $this->ownerId,
            'animalId'                => $this->animalId,
            'foundAnimalDescription'  => $this->foundAnimalDescription,
            'arrivalMode'             => $this->arrivalMode,
            'priority'                => $this->priority,
            'triageNotes'             => $this->triageNotes,
            'arrivedAtUtc'            => $this->arrivedAtUtc,
        ];
    }
}
