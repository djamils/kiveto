<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\PollSupplierDeliveries;

use App\Shared\Application\Bus\CommandInterface;

final readonly class PollSupplierDeliveries implements CommandInterface
{
    public function __construct(
        public string $supplierId,
        public string $supplierAccountId,
        public int $limit = 100,
    ) {
    }
}
