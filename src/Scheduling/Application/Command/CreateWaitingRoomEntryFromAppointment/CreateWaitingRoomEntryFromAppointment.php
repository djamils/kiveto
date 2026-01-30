<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CreateWaitingRoomEntryFromAppointment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateWaitingRoomEntryFromAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
        public string $arrivalMode = 'STANDARD', // STANDARD | EMERGENCY
        public int $priority = 0,
    ) {
    }
}
