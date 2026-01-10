<?php

declare(strict_types=1);

namespace App\Clinic\Domain\ValueObject;

final class ClinicSlug
{
    private const string PATTERN = '/^[a-z0-9-]+$/';

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
            throw new \InvalidArgumentException('Clinic slug cannot be empty.');
        }

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid clinic slug format: "%s". Must match pattern %s',
                $value,
                self::PATTERN
            ));
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
