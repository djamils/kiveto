<?php

declare(strict_types=1);

namespace App\Client\Application\Query\GetClientById;

final readonly class PostalAddressDto
{
    public function __construct(
        public string $streetLine1,
        public string $city,
        public string $countryCode,
        public ?string $streetLine2 = null,
        public ?string $postalCode = null,
        public ?string $region = null,
    ) {
    }
}
