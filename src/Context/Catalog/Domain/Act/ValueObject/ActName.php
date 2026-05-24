<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\ValueObject;

use App\Context\Catalog\Domain\Act\Exception\InvalidActNameException;

final class ActName
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new InvalidActNameException('Act name cannot be empty.');
        }

        if (mb_strlen($value) > 150) {
            throw new InvalidActNameException('Act name cannot exceed 150 characters.');
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
