<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Search;

use App\Context\Client\Application\Search\ClientSearchProvider;
use App\Context\Client\Application\Search\ClientSearchRepositoryInterface;
use App\Context\Client\Application\Search\ClientSearchRow;
use App\Shared\Search\Normalization\SearchTermNormalizer;
use App\Shared\Search\Query\SearchQuery;
use PHPUnit\Framework\TestCase;

final class ClientSearchProviderTest extends TestCase
{
    private const string CLINIC_ID = '01912345-6789-7abc-8def-000000000001';

    public function testItIsTheInternalClientProvider(): void
    {
        $provider = new ClientSearchProvider($this->createStub(ClientSearchRepositoryInterface::class), new SearchTermNormalizer());

        self::assertSame('client', $provider->resultType());
        self::assertFalse($provider->isExternal());
    }

    public function testATooShortTermIsNotSearched(): void
    {
        $repo = $this->createMock(ClientSearchRepositoryInterface::class);
        $repo->expects(self::never())->method('findByQuery');

        $bucket = new ClientSearchProvider($repo, new SearchTermNormalizer())->search(new SearchQuery('m', self::CLINIC_ID, 'user-1'));

        self::assertSame('client', $bucket->type);
        self::assertSame([], $bucket->hits);
        self::assertFalse($bucket->hasMore);
    }

    public function testAStructureIsTitledByItsLegalNameAPersonByTheirName(): void
    {
        $repo = $this->createStub(ClientSearchRepositoryInterface::class);
        $repo->method('findByQuery')->willReturn([
            new ClientSearchRow('id-1', 'Claire', 'Rostand', '+33491223344', 'contact@calanques.fr', 'active', 'Élevage des Calanques'),
            new ClientSearchRow('id-2', 'Nadia', 'Benali', null, null, 'active'),
        ]);

        $bucket = new ClientSearchProvider($repo, new SearchTermNormalizer())->search(new SearchQuery('ca', self::CLINIC_ID, 'user-1', 21));

        self::assertCount(2, $bucket->hits);
        self::assertFalse($bucket->hasMore);
        self::assertSame(['Élevage des Calanques', 'Nadia Benali'], array_map(static fn ($hit): string => $hit->title, $bucket->hits));
        self::assertSame('id-1', $bucket->hits[0]->resourceId);
        self::assertSame('+33491223344', $bucket->hits[0]->subtitle);
        self::assertSame('contact@calanques.fr', $bucket->hits[0]->context);
        self::assertSame(['status' => 'active'], $bucket->hits[0]->metadata);
    }

    public function testOneRowMoreThanShownMeansThereIsMore(): void
    {
        $repo = $this->createStub(ClientSearchRepositoryInterface::class);
        $repo->method('findByQuery')->willReturn(array_fill(0, 21, new ClientSearchRow('id-1', 'Nadia', 'Benali', null, null, 'active')));

        $bucket = new ClientSearchProvider($repo, new SearchTermNormalizer())->search(new SearchQuery('nadia', self::CLINIC_ID, 'user-1', 21));

        self::assertCount(20, $bucket->hits);
        self::assertTrue($bucket->hasMore);
    }
}
