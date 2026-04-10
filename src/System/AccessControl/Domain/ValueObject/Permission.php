<?php

declare(strict_types=1);

namespace App\System\AccessControl\Domain\ValueObject;

final readonly class Permission
{
    private function __construct(
        private string $value,
    ) {
        if ('' === mb_trim($value)) {
            throw new \InvalidArgumentException('Permission cannot be empty.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
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
