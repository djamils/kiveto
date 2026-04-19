<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

use Symfony\Component\Uid\Uuid;

final class StaffProfileId
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
            throw new \InvalidArgumentException('StaffProfileId cannot be empty.');
        }

        try {
            Uuid::fromString($trimmed);
        } catch (\Throwable) {
            throw new \InvalidArgumentException(\sprintf('Invalid StaffProfileId: "%s". Expected a valid UUID.', $value));
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
