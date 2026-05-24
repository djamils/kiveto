<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\RemovePriceRule;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemovePriceRule implements CommandInterface
{
    public function __construct(
        public string $priceListId,
        public string $priceRuleId,
        public string $clinicId,
    ) {
    }
}
