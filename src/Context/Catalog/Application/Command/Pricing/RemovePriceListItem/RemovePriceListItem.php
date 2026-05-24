<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\RemovePriceListItem;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemovePriceListItem implements CommandInterface
{
    public function __construct(
        public string $priceListId,
        public string $priceListItemId,
        public string $clinicId,
    ) {
    }
}
