<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class AppointmentCancelled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $appointmentId,
        private string $clinicId,
        // false when the client must not be told (death of the animal, D3)
        private bool $notifyClient = true,
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

    public function notifyClient(): bool
    {
        return $this->notifyClient;
    }

    public function payload(): array
    {
        return [
            'appointmentId' => $this->appointmentId,
            'clinicId'      => $this->clinicId,
            'notifyClient'  => $this->notifyClient,
        ];
    }
}
