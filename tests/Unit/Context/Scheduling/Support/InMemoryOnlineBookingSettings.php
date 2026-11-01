<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

final class InMemoryOnlineBookingSettings implements OnlineBookingSettingsRepositoryInterface
{
    /** @var array<string, OnlineBookingSettings> */
    public array $settings = [];

    public function forClinic(ClinicId $clinicId): OnlineBookingSettings
    {
        return $this->settings[$clinicId->toString()] ?? OnlineBookingSettings::defaults($clinicId);
    }

    public function save(OnlineBookingSettings $settings): void
    {
        $this->settings[$settings->clinicId()->toString()] = $settings;
    }
}
