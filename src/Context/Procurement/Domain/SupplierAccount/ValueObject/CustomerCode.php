<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierAccount\ValueObject;

final readonly class CustomerCode
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('CustomerCode cannot be empty.');
        }

        if (mb_strlen($value) > 64) {
            throw new \InvalidArgumentException('CustomerCode must not exceed 64 characters.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
