<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final class CountryCode
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[A-Z]{2}$/', $value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid country code: "%s". Expected ISO 3166-1 alpha-2 (e.g. "FR", "DE").', $value));
        }

        return new self($value);
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
