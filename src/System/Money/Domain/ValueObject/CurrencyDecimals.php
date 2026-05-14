<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

/**
 * Number of decimal places for an ISO 4217 currency (0 to 4).
 *
 * Examples: EUR = 2, JPY = 0, KWD = 3. Used to convert between
 * minor units and decimal amounts in both directions.
 */
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
