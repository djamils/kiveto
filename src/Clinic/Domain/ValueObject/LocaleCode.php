<?php

declare(strict_types=1);

namespace App\Clinic\Domain\ValueObject;

final class LocaleCode
{
    private const string PATTERN = '/^[a-z]{2}(_[A-Z]{2})?$/';

    private function __construct(
        private readonly string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $value = mb_trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('Locale code cannot be empty.');
        }

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid locale code format: "%s". Must match pattern %s (e.g. "fr", "en_US")', $value, self::PATTERN));
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
