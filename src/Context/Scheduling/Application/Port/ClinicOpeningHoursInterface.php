<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;

/**
 * The clinic's opening hours and status, read from Clinic (S4c).
 */
interface ClinicOpeningHoursInterface
{
    /** Null when the clinic does not exist. */
    public function of(ClinicId $clinicId): ?ClinicOpeningHours;
}
