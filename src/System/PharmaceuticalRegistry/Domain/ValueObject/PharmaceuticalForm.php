<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class PharmaceuticalForm
{
    private string $value;

    private function __construct(string $value)
    {
        $value = mb_trim($value);

        if (mb_strlen($value) > 128) {
            throw new \InvalidArgumentException('Pharmaceutical form must not exceed 128 characters.');
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
