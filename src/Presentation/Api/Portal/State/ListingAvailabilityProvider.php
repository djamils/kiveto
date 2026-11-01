<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\GetOnlineAvailability;
use App\Context\Scheduling\Application\Query\GetOnlineAvailability\OnlineAvailability;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\InvalidAvailabilityRangeException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\ListingAvailability;
use App\Presentation\Api\Portal\Support\PublicListingRateLimit;
use App\Presentation\Api\Portal\Support\PublicListingResolver;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\AvailabilityDayView;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Always a new client: only the slots past the manual lead time, for a
 * motif open to new clients. Then Scheduling's refusals, as on
 * `/clinics/{c}/booking/availability`.
 *
 * @implements ProviderInterface<ListingAvailability>
 */
final readonly class ListingAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PublicListingResolver $listings,
        private PublicListingRateLimit $rateLimit,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ListingAvailability
    {
        $this->rateLimit->consume();
        $listingId = $uriVariables['listingId'] ?? null;
        \assert(\is_string($listingId));

        $clinicId = $this->listings->bookableClinicId($listingId);
        $query    = QueryString::of($context);

        try {
            $availability = $this->queryBus->ask(new GetOnlineAvailability(
                $clinicId,
                (string) $query->string('motifId'),
                (string) $query->string('practitionerId'),
                (string) $query->string('from'),
                (string) $query->string('to'),
                newClient: true,
            ));
        } catch (BookingNotOpenException) {
            throw PortalProblemMap::problem(PortalProblemMap::BOOKING_NOT_OPEN);
        } catch (OnlineBookingNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        } catch (InvalidAvailabilityRangeException $exception) {
            throw PortalProblemMap::violation($exception->field, $exception->getMessage(), $query->string($exception->field));
        }
        \assert($availability instanceof OnlineAvailability);

        return new ListingAvailability(array_map(AvailabilityDayView::of(...), $availability->days));
    }
}
