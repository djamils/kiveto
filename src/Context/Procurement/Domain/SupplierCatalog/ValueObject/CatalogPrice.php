<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierCatalog\ValueObject;

use App\Shared\Money\Domain\ValueObject\Money;

final readonly class CatalogPrice
{
    private function __construct(
        public readonly Money $amount,
        public readonly \DateTimeImmutable $validFrom,
        public readonly ?\DateTimeImmutable $validTo,
    ) {
    }

    public static function create(Money $amount, \DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo = null): self
    {
        if (null !== $validTo && $validTo < $validFrom) {
            throw new \InvalidArgumentException('CatalogPrice validTo must be >= validFrom.');
        }

        return new self($amount, $validFrom, $validTo);
    }

    public function isValidAt(\DateTimeImmutable $date): bool
    {
        return $date >= $this->validFrom && (null === $this->validTo || $date <= $this->validTo);
    }
}
