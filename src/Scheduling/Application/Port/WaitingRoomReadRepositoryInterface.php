<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Port;

use App\Scheduling\Application\Query\GetWaitingRoomEntryDetails\WaitingRoomEntryDetailsDTO;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;

interface WaitingRoomReadRepositoryInterface
{
    public function hasActiveEntryForAppointment(
        ClinicId $clinicId,
        AppointmentId $appointmentId,
    ): bool;

    /**
     * @return list<WaitingRoomEntryStatus>
     */
    public function getActiveStatuses(): array;

    /**
     * Finds a waiting room entry by its ID and returns detailed information.
     *
     * @throws \DomainException if entry not found
     */
    public function findById(WaitingRoomEntryId $waitingRoomEntryId): WaitingRoomEntryDetailsDTO;
}
