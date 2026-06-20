<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\ValueObject;

final readonly class PurchaseOrderNumber
{
    private const string REGEX = '/^PO-\d{4}-\d{6}$/';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::REGEX, $value)) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid PurchaseOrderNumber: "%s". Expected format: PO-YYYY-NNNNNN.', $value)
            );
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
