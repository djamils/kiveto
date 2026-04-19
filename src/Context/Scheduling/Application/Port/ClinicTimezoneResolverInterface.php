<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;

interface ClinicTimezoneResolverInterface
{
    public function resolveTimezone(ClinicId $clinicId): \DateTimeZone;
}
