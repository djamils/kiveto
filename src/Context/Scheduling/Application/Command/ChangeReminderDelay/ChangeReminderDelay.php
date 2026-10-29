<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ChangeReminderDelay;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Paramètres › Agenda: when the SMS reminder of an appointment is sent —
 * `H24` (24 h avant), `H48` (48 h avant) or `SAME_DAY_8` (le matin même à 8 h).
 */
final readonly class ChangeReminderDelay implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $reminderDelay,
    ) {
    }
}
