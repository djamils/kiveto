<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

final class SignatureImageKey
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
            throw new \InvalidArgumentException('Signature image key cannot be empty.');
        }

        if (mb_strlen($trimmed) > 255) {
            throw new \InvalidArgumentException(\sprintf('Signature image key cannot exceed 255 characters (got %d).', mb_strlen($trimmed)));
        }

        if (!preg_match('/^[A-Za-z0-9_\-\.\/]+$/', $trimmed)) {
            throw new \InvalidArgumentException(\sprintf('Signature image key contains invalid characters: "%s". Only ASCII alphanumeric, _, -, ., / are allowed.', $value));
        }

        if (str_contains($trimmed, '..')) {
            throw new \InvalidArgumentException(\sprintf('Signature image key must not contain ".." (path traversal): "%s".', $value));
        }

        if (str_starts_with($trimmed, '/')) {
            throw new \InvalidArgumentException(\sprintf('Signature image key must not start with a leading slash: "%s".', $value));
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
