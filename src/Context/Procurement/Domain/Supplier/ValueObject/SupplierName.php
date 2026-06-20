<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\ValueObject;

final readonly class SupplierName
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('SupplierName cannot be empty.');
        }

        if (mb_strlen($value) > 255) {
            throw new \InvalidArgumentException('SupplierName must not exceed 255 characters.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
