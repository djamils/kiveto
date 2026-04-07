<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class WaitingRoomEntryCalled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $waitingRoomEntryId,
        private string $clinicId,
        private string $calledAtUtc,
        private ?string $calledByUserId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->waitingRoomEntryId;
    }

    public function payload(): array
    {
        return [
            'waitingRoomEntryId' => $this->waitingRoomEntryId,
            'clinicId'           => $this->clinicId,
            'calledAtUtc'        => $this->calledAtUtc,
            'calledByUserId'     => $this->calledByUserId,
        ];
    }
}
