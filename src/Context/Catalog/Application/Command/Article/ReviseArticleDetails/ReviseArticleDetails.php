<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\ReviseArticleDetails;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ReviseArticleDetails implements CommandInterface
{
    public function __construct(
        public string $articleId,
        public string $clinicId,
        public ?string $gtin,
        public string $unitOfMeasure,
        public bool $trackStock,
    ) {
    }
}
