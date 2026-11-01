<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\BookingClientMatch;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\BookingMatchPet;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ClinicBookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;

/**
 * The « Nouveau client » requests of a clinic, with the files staff may
 * confirm them for: the clinic's non-archived files whose `mobile` is the
 * account's phone (Client's `ListClientsByMobilePhone`), each with its
 * animals, a mismatch flag when its last name differs from the Portal
 * profile's and a flag when another account already has a confirmed link
 * to it (D17: flags, never exclusions). Only a pending request is matched.
 */
final readonly class ClinicBookingRequests
{
    public function __construct(
        private BookingRequestRepositoryInterface $requests,
        private ClientDirectoryInterface $clients,
        private PetDirectoryInterface $pets,
        private ClientLinkRepositoryInterface $links,
        private ClinicProfileInterface $clinics,
    ) {
    }

    /**
     * Oldest first.
     *
     * @return list<ClinicBookingRequest>
     */
    public function pendingOf(string $clinicId): array
    {
        $pending = $this->requests->findPendingByClinic($clinicId);

        if ([] === $pending) {
            return [];
        }

        $zone = new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone);

        return array_map(fn (BookingRequest $request): ClinicBookingRequest => $this->view($request, $zone), $pending);
    }

    public function one(string $clinicId, string $requestId): ?ClinicBookingRequest
    {
        $request = $this->requests->findInClinic($clinicId, $requestId);

        return null === $request ? null : $this->view($request, new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone));
    }

    private function view(BookingRequest $request, \DateTimeZone $zone): ClinicBookingRequest
    {
        return new ClinicBookingRequest(
            id: $request->id()->toString(),
            userId: $request->userId(),
            requesterFirstName: $request->requesterFirstName(),
            requesterLastName: $request->requesterLastName(),
            requesterPhone: $request->requesterPhone(),
            petName: $request->newPetName(),
            petSpecies: $request->newPetSpecies(),
            motif: $request->motifLabel(),
            startAt: $request->startAt()->setTimezone($zone),
            createdAt: $request->createdAt()->setTimezone($zone),
            status: $request->status()->value,
            declineReason: $request->declineReason()?->value,
            attachedClientId: $request->clientId(),
            appointmentId: $request->appointmentId(),
            matches: $this->matches($request),
        );
    }

    /**
     * @return list<BookingClientMatch>
     */
    private function matches(BookingRequest $request): array
    {
        $phone = $request->requesterPhone();

        if (!$request->isPending() || null === $phone) {
            return [];
        }

        $clinicId = $request->clinicId();
        $files    = array_values(array_filter($this->clients->mobileMatches($phone), static fn (MobileFileMatch $match): bool => $match->clinicId === $clinicId));
        $pets     = [] === $files ? [] : $this->pets->visiblePets($clinicId, array_map(static fn (MobileFileMatch $match): string => $match->clientId, $files));

        return array_map(fn (MobileFileMatch $file): BookingClientMatch => new BookingClientMatch(
            $file->clientId,
            $file->firstName,
            $file->lastName,
            array_values(array_map(
                static fn (PortalPet $pet): BookingMatchPet => new BookingMatchPet($pet->id, $pet->name, $pet->species),
                array_filter($pets, static fn (PortalPet $pet): bool => $pet->primaryOwnerClientId === $file->clientId),
            )),
            null === $request->requesterLastName() || !PersonName::sameLastName($request->requesterLastName(), $file->lastName),
            [] !== array_filter(
                $this->links->findByClient($clinicId, $file->clientId),
                static fn (ClientLink $link): bool => $link->isConfirmed() && $link->userId() !== $request->userId(),
            ),
        ), $files);
    }
}
