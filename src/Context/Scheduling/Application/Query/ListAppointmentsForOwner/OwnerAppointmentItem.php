<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForOwner;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;

final readonly class OwnerAppointmentItem
{
    /** Holds its slot (planned or pending): busy on the agenda, counted as to come. */
    public bool $occupiesSlot;

    /** Booked online, waiting for the clinic's confirmation (« à confirmer »). */
    public bool $isPending;

    /** Cancelled or declined: shown as cancelled, never counted. */
    public bool $isWithdrawn;

    public function __construct(
        public string $id,
        public string $practitionerUserId,
        public string $startsAtUtc,
        public int $durationMinutes,
        public string $status,
        public ?string $reason,
        public ?string $animalId,
        public ?string $animalLabel,
        public ?string $notes = null,
    ) {
        $appointmentStatus  = AppointmentStatus::from($status);
        $this->occupiesSlot = $appointmentStatus->occupiesSlot();
        $this->isPending    = $appointmentStatus->isPending();
        $this->isWithdrawn  = $appointmentStatus->isWithdrawn();
    }
}
