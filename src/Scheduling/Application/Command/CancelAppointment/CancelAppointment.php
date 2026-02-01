<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CancelAppointment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CancelAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
    ) {
    }
}
