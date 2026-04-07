<?php

declare(strict_types=1);

namespace App\Client\Application\Command\UpdateClientPostalAddress;

final readonly class UpdateClientPostalAddress
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public ?PostalAddressDto $postalAddress = null,
    ) {
    }
}
