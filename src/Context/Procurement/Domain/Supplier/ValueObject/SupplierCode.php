<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\ValueObject;

final readonly class SupplierCode
{
    private const string REGEX = '/^[A-Z0-9_-]{2,32}$/';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::REGEX, $value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid SupplierCode: "%s". Must match ^[A-Z0-9_-]{2,32}$.', $value));
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
