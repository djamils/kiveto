<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\RevisePackage;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RevisePackage implements CommandInterface
{
    public function __construct(
        public string $packageId,
        public string $clinicId,
        public string $name,
        public string $taxCategoryCode,
        public string $pricingMode,
        public ?int $fixedPriceMinorUnits,
        public ?string $fixedPriceCurrency,
    ) {
    }
}
