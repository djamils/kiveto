<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\GetListingSettings;

final readonly class ListingSettingsView
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public bool $listed,
        public bool $acceptsNewClients,
        public array $tags,
    ) {
    }
}
