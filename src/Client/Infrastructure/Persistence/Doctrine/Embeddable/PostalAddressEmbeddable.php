<?php

declare(strict_types=1);

namespace App\Client\Infrastructure\Persistence\Doctrine\Embeddable;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class PostalAddressEmbeddable
{
    public function __construct(
        #[ORM\Column(name: 'postal_address_street_line_1', type: 'string', length: 255, nullable: true)]
        public ?string $streetLine1 = null,
        #[ORM\Column(name: 'postal_address_street_line_2', type: 'string', length: 255, nullable: true)]
        public ?string $streetLine2 = null,
        #[ORM\Column(name: 'postal_address_postal_code', type: 'string', length: 20, nullable: true)]
        public ?string $postalCode = null,
        #[ORM\Column(name: 'postal_address_city', type: 'string', length: 255, nullable: true)]
        public ?string $city = null,
        #[ORM\Column(name: 'postal_address_region', type: 'string', length: 255, nullable: true)]
        public ?string $region = null,
        #[ORM\Column(name: 'postal_address_country_code', type: 'string', length: 2, nullable: true)]
        public ?string $countryCode = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->streetLine1
            && null === $this->streetLine2
            && null === $this->postalCode
            && null === $this->city
            && null === $this->region
            && null === $this->countryCode;
    }
}
