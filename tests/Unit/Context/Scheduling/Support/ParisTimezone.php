<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

final class ParisTimezone implements ClinicTimezoneResolverInterface
{
    public function resolveTimezone(ClinicId $clinicId): \DateTimeZone
    {
        return new \DateTimeZone('Europe/Paris');
    }
}
