<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\System\PharmaceuticalRegistry\Domain\Exception\InvalidGtinException;

final class Gtin
{
    private string $value;

    private function __construct(string $value)
    {
        if (1 !== preg_match('/^\d{8,14}$/', $value)) {
            throw new InvalidGtinException($value);
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
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
