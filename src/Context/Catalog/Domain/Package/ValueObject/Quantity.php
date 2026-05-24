<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\ValueObject;

final class Quantity
{
    private function __construct(private readonly int $value)
    {
    }

    public static function of(int $n): self
    {
        if ($n <= 0) {
            throw new \DomainException(\sprintf('Quantity must be greater than 0, got %d.', $n));
        }

        return new self($n);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
