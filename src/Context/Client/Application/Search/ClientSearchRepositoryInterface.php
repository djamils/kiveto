<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Search;

use App\Shared\Search\Query\SearchQuery;

interface ClientSearchRepositoryInterface
{
    /**
     * @return list<ClientSearchRow>
     */
    public function findByQuery(SearchQuery $query): array;
}
