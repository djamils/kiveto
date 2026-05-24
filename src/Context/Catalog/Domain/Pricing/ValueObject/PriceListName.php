<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

final class PriceListName
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new \DomainException('Price list name cannot be empty.');
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
