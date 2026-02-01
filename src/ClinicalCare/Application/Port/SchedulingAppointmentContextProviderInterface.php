<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Port;

use App\ClinicalCare\Domain\ValueObject\AppointmentId;

interface SchedulingAppointmentContextProviderInterface
{
    public function getAppointmentContext(AppointmentId $appointmentId): AppointmentContextDTO;
}
