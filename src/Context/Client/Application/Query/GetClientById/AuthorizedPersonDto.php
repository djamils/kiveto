<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientById;

final readonly class AuthorizedPersonDto
{
    public function __construct(
        public string $civility,
        public string $firstName,
        public string $lastName,
        public string $relationship,
        public ?string $phoneNumber,
        public string $authorizedAt,
    ) {
    }

    public function fullName(): string
    {
        return \sprintf('%s %s', $this->firstName, $this->lastName);
    }
}
