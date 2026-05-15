<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\Exception\InvalidTaxCategoryFormatException;

final class TaxCategoryCode
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $code): self
    {
        if (1 !== preg_match('/^[a-z]+(\.[a-z_]+)+$/', $code)) {
            throw new InvalidTaxCategoryFormatException($code);
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
