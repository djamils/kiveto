<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\RenameArticle;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RenameArticle implements CommandInterface
{
    public function __construct(
        public string $articleId,
        public string $clinicId,
        public string $name,
    ) {
    }
}
