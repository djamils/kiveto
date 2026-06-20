<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\AddPurchaseOrderLine;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddPurchaseOrderLine implements CommandInterface
{
    public function __construct(
        public string $purchaseOrderId,
        public string $clinicId,
        public string $articleId,
        public string $catalogEntryId,
        public string $orderedAmount,
        public string $orderedUnit,
        public int $unitPriceMinor,
        public string $unitPriceCurrency,
        public ?string $note,
    ) {
    }
}
