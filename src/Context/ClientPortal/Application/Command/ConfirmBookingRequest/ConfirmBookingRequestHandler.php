<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ConfirmBookingRequest;

use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClientRegistrationInterface;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PetRegistrationInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Exception\BookingRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestNotFoundException;
use App\Context\ClientPortal\Domain\Exception\InvalidBookingChoiceException;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * One transaction (S7d): the request read `FOR UPDATE` and still pending;
 * the client file (a non-archived file of the clinic whose mobile is the
 * account's phone, or a new file without animals named after the Portal
 * profile, the phone as its mobile — or the file a sibling request was
 * already confirmed for, AC 268); the animal (one of that client's, or the
 * new pet registered through Animal, S4g); the client link confirmed or
 * reactivated; the appointment given its owner and animal and confirmed in
 * Scheduling (its refusals pass through: already handled, expired); the
 * request `confirmed` by the staff member; the account's other pending
 * requests at the clinic attached to the same file.
 */
#[AsMessageHandler]
final readonly class ConfirmBookingRequestHandler
{
    public function __construct(
        private BookingRequestRepositoryInterface $requests,
        private ClientDirectoryInterface $clients,
        private ClientRegistrationInterface $registration,
        private PetDirectoryInterface $pets,
        private PetRegistrationInterface $petRegistration,
        private ClientLinkRepositoryInterface $links,
        private OwnerProfileRepositoryInterface $profiles,
        private OnlineBookingInterface $booking,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private DomainEventPublisher $eventPublisher,
    ) {
    }

    /**
     * @throws BookingRequestNotFoundException
     * @throws BookingRequestAlreadyResolvedException
     * @throws InvalidBookingChoiceException
     */
    public function __invoke(ConfirmBookingRequest $command): void
    {
        $request = $this->requests->findInClinicForUpdate($command->clinicId, $command->requestId)
            ?? throw new BookingRequestNotFoundException($command->requestId);

        if (!$request->isPending()) {
            throw new BookingRequestAlreadyResolvedException($command->requestId);
        }

        $now      = $this->clock->now();
        $clientId = $this->client($request, $command);
        $animalId = $this->animal($request, $command, $clientId);
        $link     = $this->links->findForUpdate($request->userId(), $request->clinicId(), $clientId)
            ?? ClientLink::confirm(ClientLinkId::fromString($this->uuids->generate()), $request->userId(), $request->clinicId(), $clientId, $now);

        $link->reactivate($now);
        $this->links->save($link);
        $this->eventPublisher->publish($link);

        $this->booking->confirmNewClient($request->clinicId(), $request->appointmentId(), $clientId, $animalId);

        $request->confirm($clientId, $now, $command->actorUserId, $command->actorName);
        $this->requests->save($request);

        foreach ($this->requests->findPendingByUserForUpdate($request->userId()) as $sibling) {
            if ($sibling->clinicId() === $request->clinicId()) {
                $sibling->attachClient($clientId);
                $this->requests->save($sibling);
            }
        }
    }

    private function client(BookingRequest $request, ConfirmBookingRequest $command): string
    {
        $phone = $request->requesterPhone() ?? throw new \LogicException('A pending request keeps its phone.');

        if (ConfirmBookingRequest::EXISTING === $command->clientChoice) {
            $matches = array_map(
                static fn (MobileFileMatch $match): string => $match->clientId,
                array_filter($this->clients->mobileMatches($phone), static fn (MobileFileMatch $match): bool => $match->clinicId === $request->clinicId()),
            );

            if (null === $command->clientId || !\in_array($command->clientId, $matches, true)) {
                throw new InvalidBookingChoiceException(InvalidBookingChoiceException::CLIENT);
            }

            return $command->clientId;
        }

        if (ConfirmBookingRequest::NEW !== $command->clientChoice) {
            throw new \InvalidArgumentException(\sprintf('Unknown client choice "%s".', $command->clientChoice));
        }

        // A sibling request was confirmed first: same file, no second client (AC 268)
        if (null !== $request->clientId()) {
            return $request->clientId();
        }

        $profile = $this->profiles->findByUserId($request->userId());

        return $this->registration->register(
            $request->clinicId(),
            $profile?->firstName() ?? (string) $request->requesterFirstName(),
            $profile?->lastName() ?? (string) $request->requesterLastName(),
            $phone,
            $command->actorUserId,
        );
    }

    private function animal(BookingRequest $request, ConfirmBookingRequest $command, string $clientId): string
    {
        if (ConfirmBookingRequest::EXISTING === $command->animalChoice) {
            $pets = array_map(static fn (PortalPet $pet): string => $pet->id, $this->pets->visiblePets($request->clinicId(), [$clientId]));

            if (null === $command->animalId || !\in_array($command->animalId, $pets, true)) {
                throw new InvalidBookingChoiceException(InvalidBookingChoiceException::ANIMAL);
            }

            return $command->animalId;
        }

        if (ConfirmBookingRequest::NEW !== $command->animalChoice) {
            throw new \InvalidArgumentException(\sprintf('Unknown animal choice "%s".', $command->animalChoice));
        }

        return $this->petRegistration->register(
            $request->clinicId(),
            $clientId,
            $request->newPetName() ?? throw new \LogicException('A pending request keeps its new pet.'),
            $request->newPetSpecies() ?? throw new \LogicException('A pending request keeps its new pet.'),
        );
    }
}
