<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Port;

use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
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
}
