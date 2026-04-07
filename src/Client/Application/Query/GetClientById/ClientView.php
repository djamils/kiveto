<?php

declare(strict_types=1);

namespace App\Client\Application\Query\GetClientById;

final readonly class ClientView
{
    /**
     * @param list<ContactMethodDto> $contactMethods
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $firstName,
        public string $lastName,
        public string $status,
        public array $contactMethods,
        public ?PostalAddressDto $postalAddress,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public function fullName(): string
    {
        return trim(\sprintf('%s %s', $this->firstName, $this->lastName));
    }
}
