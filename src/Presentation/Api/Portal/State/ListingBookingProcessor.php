<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\RequestListingBooking\RequestListingBooking;
use App\Context\ClientPortal\Application\Query\GetOwnerBookingRequest\GetOwnerBookingRequest;
use App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests\OwnerBookingRequest;
use App\Context\ClientPortal\Domain\Exception\AlreadyLinkedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestLimitException;
use App\Context\ClientPortal\Domain\Exception\InvalidNewPetException;
use App\Context\ClientPortal\Domain\Exception\ListingBookingNotOpenException;
use App\Context\ClientPortal\Domain\Exception\ListingBookingRateLimitedException;
use App\Context\ClientPortal\Domain\Exception\ListingNotFoundException;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\ListingBooking;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\AppointmentResponder;
use App\Presentation\Api\Portal\View\BookingRequestView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Idempotent by its key. The checks and their order are the command's
 * (S7d); this maps them to the Portal's problems and answers the request
 * as `GET /me/booking-requests` shows it.
 *
 * @implements ProcessorInterface<ListingBooking, BookingRequestView>
 */
final readonly class ListingBookingProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BookingRequestView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $listingId = $uriVariables['listingId'] ?? null;
        \assert(\is_string($listingId));

        try {
            $requestId = $this->commandBus->dispatch(new RequestListingBooking(
                $user->id(),
                $listingId,
                $data->newPet->name,
                $data->newPet->species,
                $data->motifId,
                $data->practitionerId,
                AppointmentResponder::instant($data->startAt),
            ));
        } catch (ListingNotFoundException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND, previous: $exception);
        } catch (OnlineBookingNotFoundException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND, previous: $exception);
        } catch (AlreadyLinkedException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::ALREADY_LINKED, previous: $exception);
        } catch (BookingRequestLimitException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::BOOKING_REQUEST_LIMIT, previous: $exception);
        } catch (ListingBookingRateLimitedException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::RATE_LIMITED, ['Retry-After' => (string) $exception->retryAfterSeconds], previous: $exception);
        } catch (ListingBookingNotOpenException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::BOOKING_NOT_OPEN, previous: $exception);
        } catch (BookingNotOpenException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::BOOKING_NOT_OPEN, previous: $exception);
        } catch (SlotTakenException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::SLOT_TAKEN, previous: $exception);
        } catch (InvalidNewPetException $exception) {
            throw PortalProblemMap::violation('newPet.' . $exception->field, $exception->getMessage());
        }
        \assert(\is_string($requestId));

        $request = $this->queryBus->ask(new GetOwnerBookingRequest($user->id(), $requestId));
        \assert($request instanceof OwnerBookingRequest);

        return BookingRequestView::of($request);
    }
}
