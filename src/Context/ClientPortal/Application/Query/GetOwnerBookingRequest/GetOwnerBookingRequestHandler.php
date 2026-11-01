<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerBookingRequest;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests\OwnerBookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetOwnerBookingRequestHandler
{
    public function __construct(
        private BookingRequestRepositoryInterface $requests,
        private ClinicProfileInterface $clinics,
    ) {
    }

    public function __invoke(GetOwnerBookingRequest $query): ?OwnerBookingRequest
    {
        $request = $this->requests->findOfUser($query->userId, $query->requestId);

        return null === $request ? null : OwnerBookingRequest::of($request, $this->clinics->clinicOf($request->clinicId()));
    }
}
