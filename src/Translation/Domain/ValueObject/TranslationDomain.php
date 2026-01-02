<?php

declare(strict_types=1);

namespace App\Translation\Domain\ValueObject;

final class TranslationDomain
{
    private const int MAX_LENGTH = 64;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = mb_strtolower(trim($value));

        if ('' === $normalized) {
            throw new \InvalidArgumentException('Translation domain cannot be empty.');
        }

        if (\strlen($normalized) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Translation domain is too long.');
        }

        if (1 !== preg_match('/^[a-z0-9_.-]+$/', $normalized)) {
            throw new \InvalidArgumentException(\sprintf('Invalid translation domain "%s".', $value));
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
