<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOnlineBookingSettings;

final readonly class OnlineBookingSettingsView
{
    public function __construct(
        public bool $enabled,
        public int $gridStepMinutes,
        public int $minLeadTimeMinutes,
        public int $minLeadTimeManualHours,
        public int $horizonDays,
        public int $cancellationWindowHours,
        public bool $newClientBookingEnabled,
    ) {
    }
}
