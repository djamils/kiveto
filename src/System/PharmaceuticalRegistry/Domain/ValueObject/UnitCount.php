<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class UnitCount
{
    private string $value;

    private function __construct(string $value)
    {
        $value = mb_trim($value);

        if (mb_strlen($value) > 20) {
            throw new \InvalidArgumentException('Unit count must not exceed 20 characters.');
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
