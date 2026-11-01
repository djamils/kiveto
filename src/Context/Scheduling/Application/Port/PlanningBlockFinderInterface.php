<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

interface PlanningBlockFinderInterface
{
    /**
     * Current read of the blocks and of their occupying appointments
     * (without $excludeAppointmentId): call it under the practitioner-day lock.
     */
    public function findActiveBlockFor(
        ClinicId $clinicId,
        UserId $staffUserId,
        string $localDate,
        string $localStartTime,
        string $localEndTime,
        ?AppointmentId $excludeAppointmentId = null,
    ): ?PlanningBlockReadModel;
}
