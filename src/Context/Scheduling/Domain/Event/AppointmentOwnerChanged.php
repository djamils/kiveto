<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * The client an appointment is booked for changed (set later, corrected or
 * removed): the client messages are synced again (D2).
 */
readonly class AppointmentOwnerChanged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $appointmentId,
        private string $clinicId,
        private ?string $oldOwnerId,
        private ?string $newOwnerId,
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

    public function payload(): array
    {
        return [
            'appointmentId' => $this->appointmentId,
            'clinicId'      => $this->clinicId,
            'oldOwnerId'    => $this->oldOwnerId,
            'newOwnerId'    => $this->newOwnerId,
        ];
    }
}
