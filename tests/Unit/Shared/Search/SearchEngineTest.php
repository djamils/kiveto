<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Search;

use App\Shared\Search\Query\SearchQuery;
use App\Shared\Search\Result\SearchBucket;
use App\Shared\Search\SearchEngine;
use App\Tests\Unit\Shared\Search\Fixture\FakeSearchProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchEngine::class)]
final class SearchEngineTest extends TestCase
{
    public function testBucketsFollowTheConfiguredOrderThenTheRest(): void
    {
        $engine = new SearchEngine([
            self::provider('client', hits: 1),
            self::provider('invoice', hits: 1),
            self::provider('animal', hits: 1),
            self::provider('product', supports: false),
        ], ['animal', 'client', 'missing']);

        $types = array_map(static fn (SearchBucket $bucket): string => $bucket->type, $engine->searchAll(self::query()));

        self::assertSame(['animal', 'client', 'invoice'], $types);
    }

    public function testEachProviderIsAskedOneHitMoreThanTheCapAndTheBucketIsCapped(): void
    {
        $provider = self::provider('client', hits: SearchEngine::MAX_HITS_PER_PROVIDER + 1);
        $buckets  = new SearchEngine([$provider], [])->searchAll(self::query());

        self::assertSame(SearchEngine::MAX_HITS_PER_PROVIDER + 1, $provider->askedLimit);
        self::assertCount(SearchEngine::MAX_HITS_PER_PROVIDER, $buckets[0]->hits);
        self::assertTrue($buckets[0]->hasMore);
    }

    public function testABucketUnderTheCapHasNoMore(): void
    {
        $buckets = new SearchEngine([self::provider('client', hits: 2)], [])->searchAll(self::query());

        self::assertCount(2, $buckets[0]->hits);
        self::assertFalse($buckets[0]->hasMore);
    }

    public function testAFailingExternalProviderIsSkipped(): void
    {
        $engine = new SearchEngine([
            self::provider('registry', external: true, fails: true),
            self::provider('client', hits: 1),
        ], []);

        $types = array_map(static fn (SearchBucket $bucket): string => $bucket->type, $engine->searchAll(self::query()));

        self::assertSame(['client'], $types);
    }

    public function testAnExternalProviderAnsweringInTimeIsKept(): void
    {
        $buckets = new SearchEngine([self::provider('registry', hits: 1, external: true)], [])->searchAll(self::query());

        self::assertSame('registry', $buckets[0]->type);
    }

    public function testASlowExternalProviderIsDropped(): void
    {
        $engine = new SearchEngine([self::provider('registry', hits: 1, external: true, delayMs: 820)], []);

        self::assertNull($engine->searchOne(self::query(), 'registry'));
    }

    public function testSearchOneAsksOnlyTheProviderOfThatType(): void
    {
        $other  = self::provider('animal', hits: 1);
        $client = self::provider('client', hits: 3);

        $bucket = new SearchEngine([$other, $client], [])->searchOne(self::query(), 'client');

        self::assertNotNull($bucket);
        self::assertSame('client', $bucket->type);
        self::assertCount(3, $bucket->hits);
        self::assertNull($other->askedLimit);
    }

    public function testSearchOneGivesNothingWhenTheProviderDoesNotSupportTheQuery(): void
    {
        self::assertNull(new SearchEngine([self::provider('client', supports: false)], [])->searchOne(self::query(), 'client'));
    }

    public function testSearchOneGivesNothingWhenTheExternalProviderFails(): void
    {
        $engine = new SearchEngine([self::provider('registry', external: true, fails: true)], []);

        self::assertNull($engine->searchOne(self::query(), 'registry'));
    }

    public function testSearchOneGivesNothingForAnUnknownType(): void
    {
        self::assertNull(new SearchEngine([self::provider('client', hits: 1)], [])->searchOne(self::query(), 'invoice'));
    }

    private static function query(): SearchQuery
    {
        return new SearchQuery('rex', 'clinic-1', 'user-1');
    }

    private static function provider(
        string $type,
        int $hits = 0,
        bool $supports = true,
        bool $external = false,
        bool $fails = false,
        int $delayMs = 0,
    ): FakeSearchProvider {
        return new FakeSearchProvider($type, $hits, $supports, $external, $fails, $delayMs);
    }
}
