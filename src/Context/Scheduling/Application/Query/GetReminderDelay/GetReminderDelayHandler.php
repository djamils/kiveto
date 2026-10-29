<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetReminderDelay;

use App\Context\Scheduling\Domain\Notification\ReminderSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetReminderDelayHandler
{
    public function __construct(private ReminderSettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(GetReminderDelay $query): string
    {
        return $this->settings->reminderDelay(ClinicId::fromString($query->clinicId))->value;
    }
}
