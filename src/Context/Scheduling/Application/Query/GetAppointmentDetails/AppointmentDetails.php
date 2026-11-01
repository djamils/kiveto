<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAppointmentDetails;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;

final readonly class AppointmentDetails
{
    /** Holds its slot (planned or pending): busy on the agenda, counted as to come. */
    public bool $occupiesSlot;

    /** Booked online, waiting for the clinic's confirmation (« à confirmer »). */
    public bool $isPending;

    /** Cancelled or declined: shown as cancelled, never counted. */
    public bool $isWithdrawn;

    public function __construct(
        public string $id,
        public string $clinicId,
        public ?string $linkedAdmissionId,
        public string $practitionerUserId,
        public string $startsAtUtc,
        public int $durationMinutes,
        public string $status,
        public ?string $reason,
        public ?string $notes,
        public ?string $serviceStartedAtUtc,
        public string $createdAtUtc,
        public string $updatedAtUtc,
        public ?string $ownerId = null,
        public ?string $animalId = null,
        public ?string $ownerLabel = null,
        public ?string $animalLabel = null,
    ) {
        $appointmentStatus  = AppointmentStatus::from($status);
        $this->occupiesSlot = $appointmentStatus->occupiesSlot();
        $this->isPending    = $appointmentStatus->isPending();
        $this->isWithdrawn  = $appointmentStatus->isWithdrawn();
    }
}
