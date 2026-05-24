<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\ChangeArticleBasePrice;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeArticleBasePrice implements CommandInterface
{
    public function __construct(
        public string $articleId,
        public string $clinicId,
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
    ) {
    }
}
