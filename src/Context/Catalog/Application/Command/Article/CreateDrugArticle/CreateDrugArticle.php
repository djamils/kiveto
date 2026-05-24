<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\CreateDrugArticle;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateDrugArticle implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $name,
        public string $code,
        public ?string $gtin,
        public string $taxCategoryCode,
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
        public string $unitOfMeasure,
        public ?string $authorizationRef,
        public bool $requiresPrescription,
        public ?string $prescriptionClass,
        public bool $isControlledSubstance,
        public bool $trackStock,
    ) {
    }
}
