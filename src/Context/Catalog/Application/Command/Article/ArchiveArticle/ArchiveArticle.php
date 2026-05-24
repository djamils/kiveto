<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\ArchiveArticle;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ArchiveArticle implements CommandInterface
{
    public function __construct(
        public string $articleId,
        public string $clinicId,
    ) {
    }
}
