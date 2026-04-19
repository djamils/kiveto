<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

final class DisplayName
{
    private function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Display name cannot be empty.');
        }

        if (mb_strlen($trimmed) > 60) {
            throw new \InvalidArgumentException(\sprintf('Display name cannot exceed 60 characters (got %d).', mb_strlen($trimmed)));
        }

        return new self($trimmed);
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
