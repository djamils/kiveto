<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\WaitingRoomEntryId;

interface SchedulingServiceCoordinatorInterface
{
    /**
     * Ensure appointment is in service state
     * (idempotent - no error if already in service).
     */
    public function ensureAppointmentInService(
        AppointmentId $appointmentId,
        UserId $triggeredByUserId,
    ): void;

    /**
     * Ensure waiting room entry is in service state
     * (idempotent - no error if already in service).
     */
    public function ensureWaitingRoomEntryInService(
        WaitingRoomEntryId $entryId,
        UserId $triggeredByUserId,
    ): void;

    /**
     * Mark appointment as completed.
     */
    public function completeAppointment(
        AppointmentId $appointmentId,
        UserId $triggeredByUserId,
    ): void;
}
