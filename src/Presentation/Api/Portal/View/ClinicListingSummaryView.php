<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\ClinicListingSummary;

/** A clinic of the directory as a search lists it; `id` is the listing id, never the clinic's. */
final readonly class ClinicListingSummaryView
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?ListingAddressView $address,
        public ?string $phone,
        /** Whole metres from the given point; null without `lat`/`lng`. */
        public ?int $distanceM,
        public bool $onKiveto,
        /** Open to new clients online: booking enabled, new clients accepted, a motif open to them. */
        public bool $onlineBooking,
        public array $tags,
        /** Always null in v1. */
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $nextAvailableAt,
    ) {
    }

    public static function of(ClinicListingSummary $listing): self
    {
        return new self(
            $listing->id,
            $listing->name,
            ListingAddressView::of($listing->address),
            $listing->phone,
            $listing->distanceM,
            $listing->onKiveto,
            $listing->onlineBooking,
            $listing->tags,
            $listing->nextAvailableAt,
        );
    }
}
