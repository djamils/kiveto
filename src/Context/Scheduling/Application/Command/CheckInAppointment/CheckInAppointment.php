<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CheckInAppointment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CheckInAppointment implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $appointmentId,
        public string $admissionId,
    ) {
    }
}
