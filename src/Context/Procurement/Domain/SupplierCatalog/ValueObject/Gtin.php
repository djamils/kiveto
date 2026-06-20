<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierCatalog\ValueObject;

final readonly class Gtin
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        $validPattern = preg_match('/^\d{8}$/', $value)
            || preg_match('/^\d{12}$/', $value)
            || preg_match('/^\d{13}$/', $value)
            || preg_match('/^\d{14}$/', $value);

        if (!$validPattern) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid GTIN: "%s". Must be 8, 12, 13, or 14 digits.', $value),
            );
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
