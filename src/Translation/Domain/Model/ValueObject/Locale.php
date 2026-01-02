<?php

declare(strict_types=1);

namespace App\Translation\Domain\Model\ValueObject;

final class Locale
{
    private const int MAX_LENGTH = 16;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException('Locale cannot be empty.');
        }

        if (\strlen($normalized) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Locale is too long.');
        }

        $normalized = self::normalize($normalized);

        if (1 !== preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $normalized)) {
            throw new \InvalidArgumentException(\sprintf('Invalid locale format "%s".', $value));
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    private static function normalize(string $value): string
    {
        $parts = explode('_', $value);

        if (1 === \count($parts)) {
            return mb_strtolower($parts[0]);
        }

        return \sprintf('%s_%s', mb_strtolower($parts[0]), mb_strtoupper($parts[1]));
    }
}
