<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\RestoreArticle;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RestoreArticle implements CommandInterface
{
    public function __construct(
        public string $articleId,
        public string $clinicId,
    ) {
    }
}
