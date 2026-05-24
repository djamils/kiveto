<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\ChangePackageFixedPrice;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangePackageFixedPrice implements CommandInterface
{
    public function __construct(
        public string $packageId,
        public string $clinicId,
        public int $fixedPriceMinorUnits,
        public string $fixedPriceCurrency,
    ) {
    }
}
