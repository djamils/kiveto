<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\Shared\ValueObject;

use App\Context\Inventory\Domain\Shared\Exception\InvalidSupplierIdException;

final readonly class SupplierId
{
    private const string UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::UUID_REGEX, $value)) {
            throw new InvalidSupplierIdException($value);
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
