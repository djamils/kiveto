<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

use App\Shared\Domain\ValueObject\PhoneNumber;

/**
 * The person a client trusts to drop off and pick up the animals of the file.
 */
final readonly class AuthorizedPerson
{
    public string $firstName;
    public string $lastName;

    public function __construct(
        public Civility $civility,
        string $firstName,
        string $lastName,
        public AuthorizedPersonRelationship $relationship,
        public ?PhoneNumber $phoneNumber,
        public \DateTimeImmutable $authorizedAt,
    ) {
        $firstName = trim($firstName);
        $lastName  = trim($lastName);

        if ('' === $firstName) {
            throw new \InvalidArgumentException('Authorized person first name cannot be empty.');
        }

        if ('' === $lastName) {
            throw new \InvalidArgumentException('Authorized person last name cannot be empty.');
        }

        $this->firstName = $firstName;
        $this->lastName  = $lastName;
    }

    public function fullName(): string
    {
        return \sprintf('%s %s', $this->firstName, $this->lastName);
    }

    public function isSamePersonAs(self $other): bool
    {
        return mb_strtolower($this->fullName()) === mb_strtolower($other->fullName());
    }
}
