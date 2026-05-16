<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class HolderLaboratory
{
    private string $value;

    private function __construct(string $value)
    {
        $value = mb_trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('Holder laboratory cannot be empty.');
        }

        if (mb_strlen($value) > 255) {
            throw new \InvalidArgumentException('Holder laboratory must not exceed 255 characters.');
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
