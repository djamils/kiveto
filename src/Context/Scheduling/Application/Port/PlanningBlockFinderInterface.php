<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

interface PlanningBlockFinderInterface
{
    public function findActiveBlockFor(
        ClinicId $clinicId,
        UserId $staffUserId,
        string $localDate,
        string $localStartTime,
        string $localEndTime,
    ): ?PlanningBlockReadModel;
}
