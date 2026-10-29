<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Port\ClinicTimeZoneInterface;

final readonly class FixedClinicTimeZone implements ClinicTimeZoneInterface
{
    public function __construct(private string $zone = 'Europe/Paris')
    {
    }

    public function of(string $clinicId): \DateTimeZone
    {
        return new \DateTimeZone($this->zone);
    }
}
