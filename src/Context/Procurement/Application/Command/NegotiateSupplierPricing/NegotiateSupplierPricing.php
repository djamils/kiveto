<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\NegotiateSupplierPricing;

use App\Shared\Application\Bus\CommandInterface;

final readonly class NegotiateSupplierPricing implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $supplierId,
        public string $catalogEntryId,
        public int $amountMinor,
        public string $currency,
        public ?string $discountPercentage,
        public ?string $notes,
        public ?string $expiresAt,
    ) {
    }
}
