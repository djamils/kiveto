<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\ChangeActBasePrice;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeActBasePrice implements CommandInterface
{
    public function __construct(
        public string $actId,
        public string $clinicId,
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
    ) {
    }
}
