<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Application\Port;

use App\Context\ClinicalCare\Domain\ValueObject\AppointmentId;

interface SchedulingAppointmentContextProviderInterface
{
    public function getAppointmentContext(AppointmentId $appointmentId): AppointmentContextDTO;
}
