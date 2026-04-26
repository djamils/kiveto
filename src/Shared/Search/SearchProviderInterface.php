<?php

declare(strict_types=1);

namespace App\Shared\Search;

use App\Shared\Search\Query\SearchQuery;
use App\Shared\Search\Result\SearchBucket;

interface SearchProviderInterface
{
    public function supports(SearchQuery $query): bool;

    public function search(SearchQuery $query): SearchBucket;

    public function resultType(): string;

    public function isExternal(): bool;
}
