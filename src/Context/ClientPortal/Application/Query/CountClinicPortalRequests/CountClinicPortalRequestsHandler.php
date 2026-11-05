<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\CountClinicPortalRequests;

use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestReadModelInterface;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The requests the queue lists as waiting, counted as it lists them: the
 * submitted pet changes and renewals but those whose pet is archived or
 * deceased since (ListClinicPortalRequests), one « Rattachement » request
 * per account with links pending (ListClinicLinkRequests), and every
 * pending « Nouveau client » request (ListClinicBookingRequests).
 */
#[AsMessageHandler]
final readonly class CountClinicPortalRequestsHandler
{
    public function __construct(
        private PortalRequestReadModelInterface $requests,
        private PetDirectoryInterface $pets,
        private ClientLinkRepositoryInterface $links,
        private BookingRequestRepositoryInterface $bookings,
    ) {
    }

    public function __invoke(CountClinicPortalRequests $query): int
    {
        $petIds = $this->requests->submittedPetIdsOf($query->clinicId);
        $states = $this->pets->statesOf($query->clinicId, $petIds);
        $open   = array_filter($petIds, static fn (string $petId): bool => null === ($states[$petId] ?? null)?->closureState());

        $accounts = array_unique(array_map(static fn (ClientLink $link): string => $link->userId(), $this->links->findPendingByClinic($query->clinicId)));

        return \count($open) + \count($accounts) + \count($this->bookings->findPendingByClinic($query->clinicId));
    }
}
