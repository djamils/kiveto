<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\DeclineAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The clinic declines a pending online request (VetApp « Refuser »).
 */
final readonly class DeclineAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
        public string $clinicId,
    ) {
    }
}
