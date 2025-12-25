<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identifier;

abstract readonly class AbstractUuidId
{
    public function __construct(protected string $value)
    {
        $value = \trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('Identifier cannot be empty.');
        }

        $this->value = $value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class && $this->value === $other->value;
    }
}

