<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class PermanentIdentifier
{
    private string $value;

    private function __construct(string $value)
    {
        if (1 !== preg_match('/^\d{12}$/', $value)) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid permanent identifier: "%s". Must be exactly 12 digits.', $value),
            );
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
