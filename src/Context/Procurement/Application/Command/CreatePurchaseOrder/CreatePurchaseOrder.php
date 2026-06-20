<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\CreatePurchaseOrder;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreatePurchaseOrder implements CommandInterface
{
    /**
     * @param array<string, string|null> $deliveryAddress
     */
    public function __construct(
        public string $clinicId,
        public string $supplierId,
        public string $supplierAccountId,
        public array $deliveryAddress,
    ) {
    }
}
