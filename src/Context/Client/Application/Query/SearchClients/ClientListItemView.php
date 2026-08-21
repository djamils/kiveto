<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\SearchClients;

final readonly class ClientListItemView
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $status,
        public ?string $primaryPhone,
        public ?string $primaryEmail,
        public string $createdAt,
        public ?string $city = null,
    ) {
    }

    public function fullName(): string
    {
        return trim(\sprintf('%s %s', $this->firstName, $this->lastName));
    }
}
