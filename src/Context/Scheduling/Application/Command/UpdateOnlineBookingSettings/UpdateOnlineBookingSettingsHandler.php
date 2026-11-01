<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\UpdateOnlineBookingSettings;

use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateOnlineBookingSettingsHandler
{
    public function __construct(private OnlineBookingSettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(UpdateOnlineBookingSettings $command): void
    {
        $this->settings->save(new OnlineBookingSettings(
            clinicId: ClinicId::fromString($command->clinicId),
            enabled: $command->enabled,
            gridStepMinutes: $command->gridStepMinutes,
            minLeadTimeMinutes: $command->minLeadTimeMinutes,
            minLeadTimeManualHours: $command->minLeadTimeManualHours,
            horizonDays: $command->horizonDays,
            cancellationWindowHours: $command->cancellationWindowHours,
            newClientBookingEnabled: $command->newClientBookingEnabled,
        ));
    }
}
