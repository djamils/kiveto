<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RequestListingBooking;

use App\Context\ClientPortal\Application\Command\BookPortalAppointmentWithNewPet\BookPortalAppointmentWithNewPetHandler;
use App\Context\ClientPortal\Application\Port\BookingMotifsInterface;
use App\Context\ClientPortal\Application\Port\ClinicListingsInterface;
use App\Context\ClientPortal\Application\Port\ListingBookingQuotaInterface;
use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\ClientPortal\Application\Port\OnlineMotif;
use App\Context\ClientPortal\Application\Port\OwnerAccountsInterface;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestId;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Exception\AlreadyLinkedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestLimitException;
use App\Context\ClientPortal\Domain\Exception\InvalidNewPetException;
use App\Context\ClientPortal\Domain\Exception\ListingBookingNotOpenException;
use App\Context\ClientPortal\Domain\Exception\ListingNotFoundException;
use App\Context\ClientPortal\Domain\Exception\OwnerProfileNotFoundException;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S7d, checks in this order: the listing (`ListingNotFoundException`, 404),
 * no confirmed link to the clinic (`AlreadyLinkedException`), at most one
 * pending request at the clinic and three in all (counted under `FOR
 * UPDATE` locks, `BookingRequestLimitException`), the daily quota
 * (`ListingBookingRateLimitedException`), then the availability: the
 * listing open to new clients (`ListingBookingNotOpenException`) and
 * Scheduling's new-client mode (its refusals pass through: `booking_not_open`,
 * `not_found`, `slot_taken`). The request is inserted in the transaction
 * that holds the slot; no client link is created.
 */
#[AsMessageHandler]
final readonly class RequestListingBookingHandler
{
    /** D14: pending requests per clinic and in all. */
    public const int MAX_PENDING_PER_CLINIC = 1;
    public const int MAX_PENDING            = 3;

    public function __construct(
        private ClinicListingsInterface $listings,
        private ClientLinkRepositoryInterface $links,
        private BookingRequestRepositoryInterface $requests,
        private ListingBookingQuotaInterface $quota,
        private OnlineBookingInterface $booking,
        private BookingMotifsInterface $motifs,
        private OwnerProfileRepositoryInterface $profiles,
        private OwnerAccountsInterface $accounts,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestListingBooking $command): string
    {
        $name = trim($command->petName);

        if ('' === $name || mb_strlen($name) > BookPortalAppointmentWithNewPetHandler::NAME_MAX_LENGTH) {
            throw InvalidNewPetException::name(BookPortalAppointmentWithNewPetHandler::NAME_MAX_LENGTH);
        }

        if (!\in_array($command->petSpecies, BookPortalAppointmentWithNewPetHandler::SPECIES, true)) {
            throw InvalidNewPetException::species();
        }

        $listing  = $this->listings->listing($command->listingId) ?? throw new ListingNotFoundException($command->listingId);
        $clinicId = $listing->clinicId;

        if ($this->links->hasConfirmed($command->userId, $clinicId)) {
            throw new AlreadyLinkedException($clinicId);
        }

        $pending = $this->requests->findPendingByUserForUpdate($command->userId);
        $here    = array_filter($pending, static fn (BookingRequest $request): bool => $request->clinicId() === $clinicId);

        if (\count($here) >= self::MAX_PENDING_PER_CLINIC) {
            throw new BookingRequestLimitException(BookingRequestLimitException::CLINIC);
        }

        if (\count($pending) >= self::MAX_PENDING) {
            throw new BookingRequestLimitException(BookingRequestLimitException::OVERALL);
        }

        $this->quota->consume($command->userId);

        if (!$listing->onlineBooking) {
            throw new ListingBookingNotOpenException($command->listingId);
        }

        $profile = $this->profiles->findByUserId($command->userId) ?? throw new OwnerProfileNotFoundException($command->userId);
        $account = $this->accounts->accountOf($command->userId) ?? throw new \LogicException('Only an active owner account books.');

        $appointmentId = $this->booking->requestNewClientSlot($clinicId, $command->motifId, $command->practitionerId, $command->startsAt);
        $motifs        = array_filter($this->motifs->onlineMotifs($clinicId), static fn (OnlineMotif $motif): bool => $motif->id === $command->motifId);
        $requestId     = BookingRequestId::fromString($this->uuids->generate());

        $this->requests->save(BookingRequest::request(
            id: $requestId,
            userId: $command->userId,
            clinicId: $clinicId,
            listingId: $listing->listingId,
            appointmentId: $appointmentId,
            motifLabel: [] === $motifs ? 'Rendez-vous' : array_values($motifs)[0]->label,
            startAt: $command->startsAt->setTimezone(new \DateTimeZone('UTC')),
            newPetName: $name,
            newPetSpecies: $command->petSpecies,
            requesterFirstName: $profile->firstName(),
            requesterLastName: $profile->lastName(),
            requesterPhone: $account->phone,
            now: $this->clock->now(),
        ));

        return $requestId->toString();
    }
}
