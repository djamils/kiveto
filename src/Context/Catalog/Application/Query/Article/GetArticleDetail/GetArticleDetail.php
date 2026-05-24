<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Article\GetArticleDetail;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetArticleDetail implements QueryInterface
{
    public function __construct(
        public string $articleId,
        public string $clinicId,
    ) {
    }
}
