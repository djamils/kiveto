<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Port;

use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\ValueObject\MarketingAuthorizationRef;
use App\Context\Catalog\Domain\ValueObject\ClinicId;

interface ArticleReadRepositoryInterface
{
    /** @return list<Article> */
    public function findAllByAuthorizationRef(MarketingAuthorizationRef $ref): array;

    /** @return list<Article> */
    public function search(string $term, ClinicId $clinicId, int $limit): array;
}
