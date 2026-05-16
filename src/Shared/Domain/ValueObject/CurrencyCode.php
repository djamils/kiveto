<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final class CurrencyCode
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[A-Z]{3}$/', $value)) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid currency code: "%s". Expected ISO 4217 (e.g. "EUR", "USD").', $value),
            );
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
