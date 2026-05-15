<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

final class TaxRateId
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $id): self
    {
        if (1 !== preg_match('/^[a-z]{2,8}\.[a-z_]+(\.[a-z0-9]+)*$/', $id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid tax rate ID: "%s".', $id));
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
