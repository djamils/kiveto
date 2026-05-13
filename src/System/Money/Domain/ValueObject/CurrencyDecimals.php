<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

final class CurrencyDecimals
{
    private int $value;

    private function __construct(int $value)
    {
        $this->value = $value;
    }

    public static function of(int $value): self
    {
        if ($value < 0 || $value > 4) {
            throw new \InvalidArgumentException(\sprintf('CurrencyDecimals must be between 0 and 4, got %d.', $value));
        }

        return new self($value);
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
