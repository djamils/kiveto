<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\UpdateOnlineBookingSettings;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Paramètres › Réservation en ligne. Out-of-bounds values are refused with
 * `InvalidBookingConfigurationException` (French message, field name).
 */
final readonly class UpdateOnlineBookingSettings implements CommandInterface
{
    public function __construct(
        public string $clinicId,
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
