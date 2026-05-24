<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\CreateAct;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateAct implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $name,
        public string $code,
        public ?string $description,
        public string $category,
        public string $taxCategoryCode,
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
        public int $estimatedDurationMinutes,
        public bool $requiresAnesthesia,
    ) {
    }
}
