<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

use App\Shared\Search\Query\SearchQuery;

interface AnimalSearchRepositoryInterface
{
    /**
     * @return list<AnimalSearchRow>
     */
    public function findByQuery(SearchQuery $query): array;
}
