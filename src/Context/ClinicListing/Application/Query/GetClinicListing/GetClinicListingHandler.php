<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\GetClinicListing;

use App\Context\ClinicListing\Application\Port\ClinicListingReadModelInterface;
use App\Context\ClinicListing\Application\Port\OnlineBookingStatusInterface;
use App\Context\ClinicListing\Application\Query\Shared\ClinicListingAddressView;
use App\Context\ClinicListing\Application\Query\Shared\OnlineBookingResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicListingHandler
{
    public function __construct(
        private ClinicListingReadModelInterface $listings,
        private OnlineBookingStatusInterface $onlineBooking,
    ) {
    }

    public function __invoke(GetClinicListing $query): ?ClinicListingDetail
    {
        $row = $this->listings->findListed($query->listingId);

        if (null === $row) {
            return null;
        }

        return new ClinicListingDetail(
            id: $row->id,
            clinicId: $row->clinicId,
            name: $row->name,
            address: ClinicListingAddressView::of($row->addressLine, $row->postalCode, $row->city),
            phone: $row->phone,
            timezone: $row->timezone,
            openingHours: array_map(
                static fn (array $slot): OpeningHoursView => new OpeningHoursView($slot['day'], $slot['opensAt'], $slot['closesAt']),
                $row->openingHours,
            ),
            acceptsNewClients: $row->acceptsNewClients,
            onlineBooking: [] !== OnlineBookingResolver::openAmong($this->onlineBooking, [$row]),
            tags: $row->tags,
        );
    }
}
