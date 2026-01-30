<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class AppointmentPractitionerAssigneeUnassigned extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $appointmentId,
        private string $clinicId,
        private string $previousPractitionerUserId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->appointmentId;
    }

    public function payload(): array
    {
        return [
            'appointmentId'               => $this->appointmentId,
            'clinicId'                    => $this->clinicId,
            'previousPractitionerUserId'  => $this->previousPractitionerUserId,
        ];
    }
}
