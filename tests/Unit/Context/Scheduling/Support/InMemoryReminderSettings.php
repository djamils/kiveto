<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Domain\Notification\ReminderDelay;
use App\Context\Scheduling\Domain\Notification\ReminderSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

final class InMemoryReminderSettings implements ReminderSettingsRepositoryInterface
{
    /** @var array<string, ReminderDelay> */
    public array $delays = [];

    public function reminderDelay(ClinicId $clinicId): ReminderDelay
    {
        return $this->delays[$clinicId->toString()] ?? ReminderDelay::default();
    }

    public function saveReminderDelay(ClinicId $clinicId, ReminderDelay $delay): void
    {
        $this->delays[$clinicId->toString()] = $delay;
    }
}
