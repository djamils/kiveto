<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Search;

use App\Shared\Search\Normalization\SearchTermNormalizer;
use App\Shared\Search\Query\SearchQuery;
use App\Shared\Search\Result\SearchBucket;
use App\Shared\Search\Result\SearchHit;
use App\Shared\Search\SearchProviderInterface;

final readonly class ClientSearchProvider implements SearchProviderInterface
{
    public function __construct(
        private ClientSearchRepositoryInterface $repository,
        private SearchTermNormalizer $normalizer,
    ) {
    }

    public function resultType(): string
    {
        return 'client';
    }

    public function isExternal(): bool
    {
        return false;
    }

    public function supports(SearchQuery $query): bool
    {
        return $this->normalizer->isExecutable($query->term);
    }

    public function search(SearchQuery $query): SearchBucket
    {
        if (!$this->supports($query)) {
            return new SearchBucket($this->resultType(), [], false);
        }

        $rows    = $this->repository->findByQuery($query);
        $hasMore = \count($rows) > ($query->limit - 1);
        $rows    = \array_slice($rows, 0, $query->limit - 1);

        $hits = array_map(fn (ClientSearchRow $row) => new SearchHit(
            id: $row->id,
            resourceId: $row->id,
            title: $row->firstName . ' ' . $row->lastName,
            subtitle: $row->searchPhone,
            context: $row->primaryEmail,
            metadata: ['status' => $row->status],
        ), $rows);

        return new SearchBucket(
            type: $this->resultType(),
            hits: $hits,
            hasMore: $hasMore,
        );
    }
}
