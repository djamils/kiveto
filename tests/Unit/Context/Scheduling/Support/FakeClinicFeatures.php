<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\ClinicFeaturesInterface;

/**
 * The features Kiveto switched on for the clinics, as FeatureCatalog would
 * answer them; records the clinics asked about.
 */
final class FakeClinicFeatures implements ClinicFeaturesInterface
{
    /** @var list<string> */
    public array $asked = [];

    public function __construct(
        public bool $onlineBooking = true,
        public bool $smsReminders = true,
    ) {
    }

    public function onlineBookingOn(string $clinicId): bool
    {
        $this->asked[] = $clinicId;

        return $this->onlineBooking;
    }

    public function smsRemindersOn(string $clinicId): bool
    {
        $this->asked[] = $clinicId;

        return $this->smsReminders;
    }
}
