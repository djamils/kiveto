<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\ChangeActTaxCategory;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeActTaxCategory implements CommandInterface
{
    public function __construct(
        public string $actId,
        public string $clinicId,
        public string $taxCategoryCode,
    ) {
    }
}
