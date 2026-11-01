<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOnlineAvailability;

use App\Context\Scheduling\Application\Service\OnlineSlotChecker;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\DaySlots;
use App\Context\Scheduling\Domain\Booking\InvalidAvailabilityRangeException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GetOnlineAvailabilityHandler
{
    public function __construct(
        private OnlineSlotChecker $slots,
    ) {
    }

    /**
     * @throws BookingNotOpenException
     * @throws OnlineBookingNotFoundException
     * @throws InvalidAvailabilityRangeException
     */
    public function __invoke(GetOnlineAvailability $query): OnlineAvailability
    {
        $clinicId = ClinicId::fromString($query->clinicId);

        // A malformed id names nothing: same answer as an unknown one
        if (!Uuid::isValid($query->motifId)) {
            throw OnlineBookingNotFoundException::motif($query->motifId);
        }

        if (!Uuid::isValid($query->practitionerId)) {
            throw OnlineBookingNotFoundException::practitioner($query->practitionerId);
        }

        $days = $this->slots->availability(
            $clinicId,
            BookingMotifId::fromString($query->motifId),
            UserId::fromString($query->practitionerId),
            $query->fromLocalDate,
            $query->toLocalDate,
            $query->newClient,
        );

        return new OnlineAvailability(array_map(
            static fn (DaySlots $day): OnlineAvailabilityDay => new OnlineAvailabilityDay($day->date, $day->open, $day->slots),
            $days,
        ));
    }
}
