<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\ValueObject;

final readonly class DeliveryNoteReference
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('DeliveryNoteReference cannot be empty.');
        }

        if (mb_strlen($value) > 128) {
            throw new \InvalidArgumentException('DeliveryNoteReference must not exceed 128 characters.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
