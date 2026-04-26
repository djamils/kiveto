<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

use App\Shared\Search\Normalization\SearchTermNormalizer;
use App\Shared\Search\Query\SearchQuery;
use App\Shared\Search\Result\SearchBucket;
use App\Shared\Search\Result\SearchHit;
use App\Shared\Search\SearchProviderInterface;

final readonly class AnimalSearchProvider implements SearchProviderInterface
{
    public function __construct(
        private AnimalSearchRepositoryInterface $repository,
        private SearchTermNormalizer $normalizer,
    ) {
    }

    public function resultType(): string
    {
        return 'animal';
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

        $hits = array_map(fn (AnimalSearchRow $row) => new SearchHit(
            id: $row->id,
            resourceId: $row->id,
            title: $row->animalName,
            subtitle: $this->buildSubtitle($row),
            context: $row->searchOwnerName ?? 'Sans propriétaire',
            metadata: [
                'status'  => $row->status,
                'species' => $row->species,
            ],
        ), $rows);

        return new SearchBucket(
            type: $this->resultType(),
            hits: $hits,
            hasMore: $hasMore,
        );
    }

    private function buildSubtitle(AnimalSearchRow $row): string
    {
        if (null !== $row->breedName) {
            return $row->species . ' · ' . $row->breedName;
        }

        return $row->species;
    }
}
