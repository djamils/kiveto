<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\System\PharmaceuticalRegistry\Domain\Exception\InvalidJurisdictionCodeException;

final class JurisdictionCode
{
    private string $value;

    private function __construct(string $value)
    {
        if (1 !== preg_match('/^[A-Z]{2,3}$/', $value)) {
            throw new InvalidJurisdictionCodeException($value);
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
