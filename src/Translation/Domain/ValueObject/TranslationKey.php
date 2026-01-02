<?php

declare(strict_types=1);

namespace App\Translation\Domain\ValueObject;

final class TranslationKey
{
    private const int MAX_LENGTH = 190;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException('Translation key cannot be empty.');
        }

        if (\strlen($normalized) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Translation key is too long.');
        }

        if (1 !== preg_match('/^[A-Za-z0-9_.-]+$/', $normalized)) {
            throw new \InvalidArgumentException(\sprintf('Invalid translation key "%s".', $value));
        }

        $normalized = mb_strtolower($normalized);

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
