<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\SearchClinicListings;

final readonly class ClinicListingPage
{
    /**
     * @param list<ClinicListingSummary> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
