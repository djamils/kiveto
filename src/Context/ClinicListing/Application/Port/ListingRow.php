<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Port;

/**
 * A listed, active clinic as the directory's read model answers it, with its
 * distance in metres when the search was around a point.
 */
final readonly class ListingRow
{
    /**
     * @param list<array{day: int, opensAt: string, closesAt: string}> $openingHours
     * @param list<string>                                             $tags
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $name,
        public ?string $addressLine,
        public ?string $postalCode,
        public ?string $city,
        public ?string $phone,
        public string $timezone,
        public array $openingHours,
        public bool $acceptsNewClients,
        public array $tags,
        public ?int $distanceM = null,
    ) {
    }
}
