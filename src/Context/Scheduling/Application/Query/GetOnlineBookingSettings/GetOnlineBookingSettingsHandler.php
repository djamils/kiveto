<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOnlineBookingSettings;

use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetOnlineBookingSettingsHandler
{
    public function __construct(private OnlineBookingSettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(GetOnlineBookingSettings $query): OnlineBookingSettingsView
    {
        $settings = $this->settings->forClinic(ClinicId::fromString($query->clinicId));

        return new OnlineBookingSettingsView(
            enabled: $settings->isEnabled(),
            gridStepMinutes: $settings->gridStepMinutes(),
            minLeadTimeMinutes: $settings->minLeadTimeMinutes(),
            minLeadTimeManualHours: $settings->minLeadTimeManualHours(),
            horizonDays: $settings->horizonDays(),
            cancellationWindowHours: $settings->cancellationWindowHours(),
            newClientBookingEnabled: $settings->isNewClientBookingEnabled(),
        );
    }
}
