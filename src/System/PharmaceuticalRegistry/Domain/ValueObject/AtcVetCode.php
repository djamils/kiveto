<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\System\PharmaceuticalRegistry\Domain\Exception\InvalidAtcVetCodeException;

final class AtcVetCode
{
    private string $value;

    private function __construct(string $value)
    {
        if (1 !== preg_match('/^Q[A-Z]\d{2}[A-Z\d]{0,4}$/', $value)) {
            throw new InvalidAtcVetCodeException($value);
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

    public function mainGroup(): string
    {
        return mb_substr($this->value, 0, 2);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
