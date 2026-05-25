<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\ValueObject;

final readonly class LotNumber
{
    private const int MAX_LENGTH = 64;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('Lot number cannot be empty.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Lot number cannot exceed %d characters, got %d.', self::MAX_LENGTH, mb_strlen($value)));
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
