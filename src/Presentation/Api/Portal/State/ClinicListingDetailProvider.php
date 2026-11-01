<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Presentation\Api\Portal\Resource\ClinicListingDetail;
use App\Presentation\Api\Portal\Support\PublicListingRateLimit;
use App\Presentation\Api\Portal\Support\PublicListingResolver;
use App\Presentation\Api\Portal\View\ListingAddressView;
use App\Presentation\Api\Portal\View\OpeningHoursView;

/**
 * @implements ProviderInterface<ClinicListingDetail>
 */
final readonly class ClinicListingDetailProvider implements ProviderInterface
{
    public function __construct(
        private PublicListingResolver $listings,
        private PublicListingRateLimit $rateLimit,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ClinicListingDetail
    {
        $this->rateLimit->consume();
        $listingId = $uriVariables['listingId'] ?? null;
        \assert(\is_string($listingId));

        $listing = $this->listings->listing($listingId);

        // The clinic id never leaves the server
        return new ClinicListingDetail(
            $listing->id,
            $listing->name,
            ListingAddressView::of($listing->address),
            $listing->phone,
            $listing->timezone,
            array_map(OpeningHoursView::of(...), $listing->openingHours),
            $listing->acceptsNewClients,
            $listing->onlineBooking,
            $listing->tags,
        );
    }
}
