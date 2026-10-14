<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\MarkAppointmentNoShow;

use App\Shared\Application\Bus\CommandInterface;

final readonly class MarkAppointmentNoShow implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
    ) {
    }
}
