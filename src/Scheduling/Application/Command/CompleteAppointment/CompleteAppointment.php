<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CompleteAppointment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CompleteAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
    ) {
    }
}
