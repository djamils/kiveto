<?php

declare(strict_types=1);

namespace App\Shared\Search;

use App\Shared\Search\Query\SearchQuery;
use App\Shared\Search\Result\SearchBucket;

final class SearchEngine
{
    public const int MAX_HITS_PER_PROVIDER = 20;

    private const int EXTERNAL_TIMEOUT_MS = 800;

    /**
     * @param iterable<SearchProviderInterface> $providers
     * @param list<string>                      $bucketOrder
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly array $bucketOrder,
    ) {
    }

    /**
     * @return list<SearchBucket>
     */
    public function searchAll(SearchQuery $query): array
    {
        $capped  = $query->withLimit(self::MAX_HITS_PER_PROVIDER + 1);
        $buckets = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports($query)) {
                continue;
            }

            $bucket = $this->executeProvider($provider, $capped);

            if (null === $bucket) {
                continue;
            }

            $buckets[$provider->resultType()] = $this->cap($bucket);
        }

        return $this->sort($buckets);
    }

    public function searchOne(SearchQuery $query, string $type): ?SearchBucket
    {
        $capped = $query->withLimit(self::MAX_HITS_PER_PROVIDER + 1);

        foreach ($this->providers as $provider) {
            if ($provider->resultType() !== $type) {
                continue;
            }

            if (!$provider->supports($query)) {
                return null;
            }

            $bucket = $this->executeProvider($provider, $capped);

            if (null === $bucket) {
                return null;
            }

            return $this->cap($bucket);
        }

        return null;
    }

    private function executeProvider(SearchProviderInterface $provider, SearchQuery $query): ?SearchBucket
    {
        if (!$provider->isExternal()) {
            return $provider->search($query);
        }

        $start = microtime(true);

        try {
            $bucket = $provider->search($query);
        } catch (\Throwable) {
            return null;
        }

        $elapsed = (microtime(true) - $start) * 1000;

        if ($elapsed > self::EXTERNAL_TIMEOUT_MS) {
            return null;
        }

        return $bucket;
    }

    private function cap(SearchBucket $bucket): SearchBucket
    {
        $hasMore = \count($bucket->hits) > self::MAX_HITS_PER_PROVIDER;
        $hits    = \array_slice($bucket->hits, 0, self::MAX_HITS_PER_PROVIDER);

        /* @var list<\App\Shared\Search\Result\SearchHit> $hits */
        return new SearchBucket($bucket->type, $hits, $hasMore);
    }

    /**
     * @param array<string, SearchBucket> $buckets
     *
     * @return list<SearchBucket>
     */
    private function sort(array $buckets): array
    {
        $ordered = [];

        foreach ($this->bucketOrder as $type) {
            if (isset($buckets[$type])) {
                $ordered[] = $buckets[$type];
                unset($buckets[$type]);
            }
        }

        foreach ($buckets as $bucket) {
            $ordered[] = $bucket;
        }

        return $ordered;
    }
}
