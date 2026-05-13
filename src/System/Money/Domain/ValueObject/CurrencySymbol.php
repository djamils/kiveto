<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

final class CurrencySymbol
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('CurrencySymbol cannot be empty.');
        }

        if (mb_strlen($trimmed) > 8) {
            throw new \InvalidArgumentException(\sprintf('CurrencySymbol cannot exceed 8 characters, got "%s".', $trimmed));
        }

        return new self($trimmed);
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
