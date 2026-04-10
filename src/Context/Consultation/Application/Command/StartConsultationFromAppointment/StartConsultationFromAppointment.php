<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\StartConsultationFromAppointment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class StartConsultationFromAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
        public string $startedByUserId,
    ) {
    }
}
