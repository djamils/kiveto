<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ConfirmAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The clinic confirms a pending online request (VetApp « Confirmer »).
 */
final readonly class ConfirmAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
        public string $clinicId,
    ) {
    }
}
