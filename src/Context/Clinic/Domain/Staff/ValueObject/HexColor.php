<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

final class HexColor
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
        $normalized = strtolower(trim($value));

        if (!preg_match('/^#[0-9a-f]{6}$/', $normalized)) {
            throw new \InvalidArgumentException(\sprintf('Invalid hex color: "%s". Expected format #rrggbb (e.g. #1a2b3c).', $value));
        }

        return new self($normalized);
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
