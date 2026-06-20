<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\ValueObject;

final readonly class LotInformation
{
    private function __construct(
        public readonly string $lotNumber,
        public readonly \DateTimeImmutable $expiryDate,
        public readonly ?\DateTimeImmutable $manufacturedAt,
    ) {
    }

    public static function create(
        string $lotNumber,
        \DateTimeImmutable $expiryDate,
        ?\DateTimeImmutable $manufacturedAt = null,
    ): self {
        if ('' === trim($lotNumber)) {
            throw new \InvalidArgumentException('Lot number cannot be empty.');
        }

        if (mb_strlen($lotNumber) > 64) {
            throw new \InvalidArgumentException('Lot number must not exceed 64 characters.');
        }

        return new self($lotNumber, $expiryDate, $manufacturedAt);
    }
}
