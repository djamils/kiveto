<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class WithdrawalPeriod
{
    private int $value;
    private WithdrawalPeriodUnit $unit;

    private function __construct(int $value, WithdrawalPeriodUnit $unit)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Withdrawal period value must be greater than 0.');
        }

        $this->value = $value;
        $this->unit  = $unit;
    }

    public static function days(int $value): self
    {
        return new self($value, WithdrawalPeriodUnit::DAY);
    }

    public static function hours(int $value): self
    {
        return new self($value, WithdrawalPeriodUnit::HOUR);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function unit(): WithdrawalPeriodUnit
    {
        return $this->unit;
    }

    /** Returns technical representation: "28:DAY" or "12:HOUR". French rendering belongs in Twig. */
    public function toString(): string
    {
        return $this->value . ':' . $this->unit->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value && $this->unit === $other->unit;
    }
}
