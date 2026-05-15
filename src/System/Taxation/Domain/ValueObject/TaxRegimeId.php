<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

final class TaxRegimeId
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $id): self
    {
        if (1 !== preg_match('/^[A-Z]{2}(-[A-Z0-9]{1,8})?$/', $id)) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid tax regime ID: "%s". Expected: ^[A-Z]{2}(-[A-Z0-9]{1,8})?$',
                $id
            ));
        }

        return new self($id);
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
