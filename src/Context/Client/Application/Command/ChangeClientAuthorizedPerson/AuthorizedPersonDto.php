<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\ChangeClientAuthorizedPerson;

final readonly class AuthorizedPersonDto
{
    public function __construct(
        public string $civility,
        public string $firstName,
        public string $lastName,
        public string $relationship,
        public ?string $phoneNumber = null,
    ) {
    }
}
