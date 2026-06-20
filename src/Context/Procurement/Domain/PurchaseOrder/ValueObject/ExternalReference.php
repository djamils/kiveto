<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\ValueObject;

final readonly class ExternalReference
{
    private function __construct(
        public readonly string $value,
        public readonly string $providedBy,
    ) {
    }

    public static function create(string $value, string $providedBy): self
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException('ExternalReference value cannot be empty.');
        }

        if ('' === trim($providedBy)) {
            throw new \InvalidArgumentException('ExternalReference providedBy cannot be empty.');
        }

        return new self(trim($value), trim($providedBy));
    }

    public function toString(): string
    {
        return $this->value;
    }
}
