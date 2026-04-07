<?php

declare(strict_types=1);

namespace App\Client\Domain\ValueObject;

final readonly class ClientIdentity
{
    public function __construct(
        public string $firstName,
        public string $lastName,
    ) {
        if ('' === trim($firstName)) {
            throw new \InvalidArgumentException('First name cannot be empty.');
        }

        if ('' === trim($lastName)) {
            throw new \InvalidArgumentException('Last name cannot be empty.');
        }
    }

    public function fullName(): string
    {
        return trim(\sprintf('%s %s', $this->firstName, $this->lastName));
    }
}
