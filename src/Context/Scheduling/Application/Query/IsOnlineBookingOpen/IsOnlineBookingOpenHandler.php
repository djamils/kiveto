<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\IsOnlineBookingOpen;

use App\Context\Scheduling\Application\Port\ClinicOpeningHoursInterface;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IsOnlineBookingOpenHandler
{
    public function __construct(
        private OnlineBookingSettingsRepositoryInterface $settings,
        private ClinicOpeningHoursInterface $clinics,
    ) {
    }

    public function __invoke(IsOnlineBookingOpen $query): bool
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $clinic   = $this->clinics->of($clinicId);

        return null !== $clinic && $this->settings->forClinic($clinicId)->isOpenForBooking($clinic->active);
    }
}
