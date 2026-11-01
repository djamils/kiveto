<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;

interface OnlineBookingSettingsRepositoryInterface
{
    /**
     * The clinic's settings, or the defaults when it never saved any.
     */
    public function forClinic(ClinicId $clinicId): OnlineBookingSettings;

    public function save(OnlineBookingSettings $settings): void;
}
