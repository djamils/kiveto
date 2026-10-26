<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Connection;

/**
 * Which approved platform a connection goes through (« simulator » in T1).
 */
final class PlatformCode
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid platform code "%s".', $value));
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
