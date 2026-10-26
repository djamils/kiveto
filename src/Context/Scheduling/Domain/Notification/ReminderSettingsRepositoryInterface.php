<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Notification;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;

/**
 * The reminder delay of each clinic (Paramètres › Agenda); « 24 h avant »
 * until the clinic chooses another.
 */
interface ReminderSettingsRepositoryInterface
{
    public function reminderDelay(ClinicId $clinicId): ReminderDelay;

    public function saveReminderDelay(ClinicId $clinicId, ReminderDelay $delay): void;
}
