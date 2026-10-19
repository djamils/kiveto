<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Search\Fixture;

use App\Shared\Search\Query\SearchQuery;
use App\Shared\Search\Result\SearchBucket;
use App\Shared\Search\Result\SearchHit;
use App\Shared\Search\SearchProviderInterface;

/**
 * Configurable provider for SearchEngineTest.
 */
final class FakeSearchProvider implements SearchProviderInterface
{
    public ?int $askedLimit = null;

    public function __construct(
        private readonly string $type,
        private readonly int $hits,
        private readonly bool $supports,
        private readonly bool $external,
        private readonly bool $fails,
        private readonly int $delayMs,
    ) {
    }

    public function supports(SearchQuery $query): bool
    {
        return $this->supports;
    }

    public function search(SearchQuery $query): SearchBucket
    {
        $this->askedLimit = $query->limit;

        if ($this->fails) {
            throw new \RuntimeException('provider down');
        }

        usleep($this->delayMs * 1000);

        $hits = [];

        for ($i = 0; $i < $this->hits; ++$i) {
            $hits[] = new SearchHit((string) $i, (string) $i, 'Hit ' . $i, null, null);
        }

        return new SearchBucket($this->type, $hits, false);
    }

    public function resultType(): string
    {
        return $this->type;
    }

    public function isExternal(): bool
    {
        return $this->external;
    }
}
