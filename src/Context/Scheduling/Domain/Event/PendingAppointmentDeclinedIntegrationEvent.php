<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * A pending online booking was not confirmed (declined, expired, or cancelled
 * by the clinic while pending): ClientPortal tells a new client, who has no
 * owner record to be messaged through (slice 7).
 * Published only for portal bookings that were pending (S4j, README D1).
 */
final readonly class PendingAppointmentDeclinedIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'scheduling';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $appointmentId,
        public string $clinicId,
        public ?string $ownerId,
        public ?string $animalId,
        public string $startsAtUtc,
        // declined | expired | clinic_cancelled
        public string $reason,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->appointmentId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'appointmentId' => $this->appointmentId,
            'clinicId'      => $this->clinicId,
            'ownerId'       => $this->ownerId,
            'animalId'      => $this->animalId,
            'startsAtUtc'   => $this->startsAtUtc,
            'reason'        => $this->reason,
        ];
    }
}
