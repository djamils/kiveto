<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\CreateNonDrugArticle;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateNonDrugArticle implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $name,
        public string $code,
        public string $kind,
        public ?string $gtin,
        public string $taxCategoryCode,
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
        public string $unitOfMeasure,
        public bool $trackStock,
    ) {
    }
}
