<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

final class RegionCode
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $code): self
    {
        if (1 !== preg_match('/^[A-Z0-9-]{1,16}$/', $code)) {
            throw new \InvalidArgumentException(\sprintf('Invalid region code: "%s".', $code));
        }

        return new self($code);
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
