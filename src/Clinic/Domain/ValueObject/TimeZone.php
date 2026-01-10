<?php

declare(strict_types=1);

namespace App\Clinic\Domain\ValueObject;

final class TimeZone
{
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
            throw new \InvalidArgumentException('Timezone cannot be empty.');
        }

        $validTimezones = \DateTimeZone::listIdentifiers();

        if (!\in_array($value, $validTimezones, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid timezone: "%s". Must be a valid IANA timezone.', $value));
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
