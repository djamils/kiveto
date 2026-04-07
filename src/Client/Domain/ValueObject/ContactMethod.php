<?php

declare(strict_types=1);

namespace App\Client\Domain\ValueObject;

use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;

final readonly class ContactMethod
{
    private function __construct(
        public ContactMethodType $type,
        public ContactLabel $label,
        public string $value,
        public bool $isPrimary,
    ) {
    }

    public static function phone(
        PhoneNumber $phoneNumber,
        ContactLabel $label,
        bool $isPrimary = false,
    ): self {
        return new self(
            ContactMethodType::PHONE,
            $label,
            $phoneNumber->toString(),
            $isPrimary,
        );
    }

    public static function email(
        EmailAddress $emailAddress,
        ContactLabel $label,
        bool $isPrimary = false,
    ): self {
        return new self(
            ContactMethodType::EMAIL,
            $label,
            $emailAddress->toString(),
            $isPrimary,
        );
    }

    public function isPhone(): bool
    {
        return ContactMethodType::PHONE === $this->type;
    }

    public function isEmail(): bool
    {
        return ContactMethodType::EMAIL === $this->type;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->value === $other->value;
    }
}
