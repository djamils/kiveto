<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

use App\Context\Consultation\Domain\ValueObject\AppointmentId;

interface SchedulingAppointmentContextProviderInterface
{
    public function getAppointmentContext(AppointmentId $appointmentId): AppointmentContextDTO;
}
