<?php

declare(strict_types=1);

namespace App\Shared\Domain\UnitOfMeasure\ValueObject;

use App\Shared\Domain\UnitOfMeasure\Exception\InvalidUnitOfMeasureException;

final class UnitOfMeasure
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $code): self
    {
        if (1 !== preg_match('/^[A-Z][A-Z_]{0,16}$/', $code)) {
            throw new InvalidUnitOfMeasureException($code);
        }

        return new self($code);
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
