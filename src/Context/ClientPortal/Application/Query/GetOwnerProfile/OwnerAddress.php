<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerProfile;

final readonly class OwnerAddress
{
    /**
     * @param string $country ISO 3166-1 alpha-2 (« FR »)
     */
    public function __construct(
        public string $line1,
        public ?string $line2,
        public ?string $postalCode,
        public string $city,
        public string $country,
    ) {
    }
}
