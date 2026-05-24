<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\ValueObject;

final class Gtin
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^\d{8,14}$/', $value)) {
            throw new \DomainException(\sprintf(
                'Invalid GTIN: "%s". Expected 8 to 14 digits.',
                $value,
            ));
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
