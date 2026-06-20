<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\UpdateSupplierPricing;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateSupplierPricing implements CommandInterface
{
    public function __construct(
        public string $pricingId,
        public int $amountMinor,
        public string $currency,
        public ?string $discountPercentage,
        public ?string $notes,
        public ?string $expiresAt,
    ) {
    }
}
