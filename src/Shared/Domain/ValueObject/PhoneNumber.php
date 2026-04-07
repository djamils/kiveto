<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final class PhoneNumber
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $cleaned = preg_replace('/\s+/', '', trim($value));

        if (null === $cleaned || '' === $cleaned) {
            throw new \InvalidArgumentException('Phone number cannot be empty.');
        }

        if (!preg_match('/^[+]?[0-9]{6,20}$/', $cleaned)) {
            throw new \InvalidArgumentException(\sprintf('Invalid phone number: "%s".', $value));
        }

        return new self($cleaned);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
