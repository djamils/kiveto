<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\CreatePackage;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreatePackage implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $name,
        public string $code,
        public string $taxCategoryCode,
        public string $pricingMode,
        public ?int $fixedPriceMinorUnits,
        public ?string $fixedPriceCurrency,
    ) {
    }
}
