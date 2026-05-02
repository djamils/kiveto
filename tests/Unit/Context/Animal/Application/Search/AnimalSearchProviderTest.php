<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Search;

use App\Context\Animal\Application\Search\AnimalSearchProvider;
use App\Context\Animal\Application\Search\AnimalSearchRepositoryInterface;
use App\Context\Animal\Application\Search\AnimalSearchRow;
use App\Shared\Search\Normalization\SearchTermNormalizer;
use App\Shared\Search\Query\SearchQuery;
use PHPUnit\Framework\TestCase;

final class AnimalSearchProviderTest extends TestCase
{
    private const string CLINIC_ID = '01912345-6789-7abc-8def-000000000001';

    public function testResultType(): void
    {
        $repo     = $this->createStub(AnimalSearchRepositoryInterface::class);
        $provider = new AnimalSearchProvider($repo, new SearchTermNormalizer());
        self::assertSame('animal', $provider->resultType());
    }

    public function testIsExternalReturnsFalse(): void
    {
        $repo     = $this->createStub(AnimalSearchRepositoryInterface::class);
        $provider = new AnimalSearchProvider($repo, new SearchTermNormalizer());
        self::assertFalse($provider->isExternal());
    }

    public function testIsExecutableGuardReturnsEmptyBucket(): void
    {
        $repo = $this->createMock(AnimalSearchRepositoryInterface::class);
        $repo->expects(self::never())->method('findByQuery');

        $provider = new AnimalSearchProvider($repo, new SearchTermNormalizer());
        $query    = new SearchQuery('m', self::CLINIC_ID, 'user-1');

        $bucket = $provider->search($query);

        self::assertSame('animal', $bucket->type);
        self::assertCount(0, $bucket->hits);
        self::assertFalse($bucket->hasMore);
    }

    public function testChipDetectionUsesExactMatch(): void
    {
        $repo = $this->createStub(AnimalSearchRepositoryInterface::class);
        $repo->method('findByQuery')->willReturn([
            new AnimalSearchRow('id-1', 'Rex', 'dog', null, null, null, 'active'),
        ]);

        $provider = new AnimalSearchProvider($repo, new SearchTermNormalizer());
        $query    = new SearchQuery('250269802120045', self::CLINIC_ID, 'user-1', 21);

        $bucket = $provider->search($query);

        self::assertCount(1, $bucket->hits);
        self::assertFalse($bucket->hasMore);
    }

    public function testPhoneDetectionUsesE164ExactMatch(): void
    {
        $repo = $this->createStub(AnimalSearchRepositoryInterface::class);
        $repo->method('findByQuery')->willReturn([
            new AnimalSearchRow('id-1', 'Rex', 'dog', null, 'Jean Dupont', null, 'active'),
        ]);

        $provider = new AnimalSearchProvider($repo, new SearchTermNormalizer());
        $query    = new SearchQuery('0612345678', self::CLINIC_ID, 'user-1', 21);

        $bucket = $provider->search($query);

        self::assertCount(1, $bucket->hits);
    }

    public function testTextTermUsesPrefixLike(): void
    {
        $repo = $this->createStub(AnimalSearchRepositoryInterface::class);
        $repo->method('findByQuery')->willReturn([
            new AnimalSearchRow('id-1', 'Rex', 'dog', 'Labrador', null, null, 'active'),
        ]);

        $provider = new AnimalSearchProvider($repo, new SearchTermNormalizer());
        $query    = new SearchQuery('rex', self::CLINIC_ID, 'user-1', 21);

        $bucket = $provider->search($query);

        self::assertCount(1, $bucket->hits);
        self::assertSame('Rex', $bucket->hits[0]->title);
    }

    public function testHasMoreReturnedWhenLimitPlusOneFound(): void
    {
        $rows = array_fill(0, 21, new AnimalSearchRow('id-1', 'Rex', 'dog', null, null, null, 'active'));

        $repo = $this->createStub(AnimalSearchRepositoryInterface::class);
        $repo->method('findByQuery')->willReturn($rows);

        $provider = new AnimalSearchProvider($repo, new SearchTermNormalizer());
        $query    = new SearchQuery('rex', self::CLINIC_ID, 'user-1', 21);

        $bucket = $provider->search($query);

        self::assertCount(20, $bucket->hits);
        self::assertTrue($bucket->hasMore);
    }
}
