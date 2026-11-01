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
use App\Presentation\Api\Portal\Resource\BookingAvailability;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\AvailabilityDayView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Scheduling refuses in its own order: `booking_not_open`, then `not_found`
 * (motif, practitioner), then the range (`validation_failed` on `from` or
 * `to`, with Scheduling's French message).
 *
 * @implements ProviderInterface<BookingAvailability>
 */
final readonly class BookingAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BookingAvailability
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);

        $query = QueryString::of($context);

        try {
            $availability = $this->queryBus->ask(new GetOnlineAvailability(
                $clinicId,
                (string) $query->string('motifId'),
                (string) $query->string('practitionerId'),
                (string) $query->string('from'),
                (string) $query->string('to'),
            ));
        } catch (BookingNotOpenException) {
            throw PortalProblemMap::problem(PortalProblemMap::BOOKING_NOT_OPEN);
        } catch (OnlineBookingNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        } catch (InvalidAvailabilityRangeException $exception) {
            throw PortalProblemMap::violation($exception->field, $exception->getMessage(), $query->string($exception->field));
        }
        \assert($availability instanceof OnlineAvailability);

        return new BookingAvailability(array_map(AvailabilityDayView::of(...), $availability->days));
    }
}
