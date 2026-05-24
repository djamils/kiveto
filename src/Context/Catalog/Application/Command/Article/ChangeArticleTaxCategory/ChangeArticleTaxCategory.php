<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\ChangeArticleTaxCategory;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeArticleTaxCategory implements CommandInterface
{
    public function __construct(
        public string $articleId,
        public string $clinicId,
        public string $taxCategoryCode,
    ) {
    }
}
