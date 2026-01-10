<?php

declare(strict_types=1);

namespace App\Shared\Domain\Localization;

final class TimeZone
{
    /** @var array<string, true>|null */
    private static ?array $validTimezones = null;

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
            throw new \InvalidArgumentException('Timezone cannot be empty.');
        }

        if (null === self::$validTimezones) {
            self::$validTimezones = array_fill_keys(\DateTimeZone::listIdentifiers(), true);
        }

        if (!isset(self::$validTimezones[$value])) {
            throw new \InvalidArgumentException(\sprintf('Invalid timezone: "%s". Must be a valid IANA timezone.', $value));
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function toNative(): \DateTimeZone
    {
        return new \DateTimeZone($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
