<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAppointmentClinicId;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetAppointmentClinicId implements QueryInterface
{
    public function __construct(
        public string $appointmentId,
    ) {
    }
}
