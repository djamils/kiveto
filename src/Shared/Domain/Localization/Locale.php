<?php

declare(strict_types=1);

namespace App\Shared\Domain\Localization;

final class Locale
{
    private const string PATTERN = '/^[a-z]{2}-[A-Z]{2}$/';

    private function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $value = mb_trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('Locale cannot be empty.');
        }

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid locale: "%s". Expected BCP47 "xx-YY" like "fr-FR" or "en-US".', $value));
        }

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
