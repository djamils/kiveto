<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\SearchCompanyRegistry;

use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Application\Query\SearchCompanyRegistry\CompanySearchResult;
use App\Context\Client\Application\Query\SearchCompanyRegistry\SearchCompanyRegistry;
use App\Context\Client\Application\Query\SearchCompanyRegistry\SearchCompanyRegistryHandler;
use App\Context\Client\Infrastructure\Registry\FixtureCompanyRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchCompanyRegistryHandler::class)]
#[CoversClass(SearchCompanyRegistry::class)]
#[CoversClass(CompanySearchResult::class)]
final class SearchCompanyRegistryHandlerTest extends TestCase
{
    public function testAShortQueryAsksNothing(): void
    {
        $registry = $this->createMock(CompanyRegistryInterface::class);
        $registry->expects(self::never())->method('search');

        $result = new SearchCompanyRegistryHandler($registry)(new SearchCompanyRegistry(' ca '));

        self::assertTrue($result->available);
        self::assertNull($result->reason);
        self::assertSame([], $result->hits);
    }

    public function testASirenOrASiretIsSearchedWhateverItsLength(): void
    {
        $handler = new SearchCompanyRegistryHandler(new FixtureCompanyRegistry());

        self::assertSame('523456788', $handler(new SearchCompanyRegistry('523 456 788'))->hits[0]->siren);
        self::assertSame('523456788', $handler(new SearchCompanyRegistry('523 456 788 00018'))->hits[0]->siren);
    }

    public function testTheHitsComeBackWithinTheLimits(): void
    {
        $registry = $this->createMock(CompanyRegistryInterface::class);
        $registry->expects(self::exactly(2))->method('search')
            ->willReturnCallback(static fn (string $query, int $limit): array => new FixtureCompanyRegistry()->search($query, $limit))
        ;

        $handler = new SearchCompanyRegistryHandler($registry);

        $result = $handler(new SearchCompanyRegistry('mont', 50));
        self::assertTrue($result->available);
        self::assertCount(2, $result->hits);
        self::assertCount(1, $handler(new SearchCompanyRegistry('mont', 0))->hits);
    }

    public function testASpentBudgetIsThrottled(): void
    {
        $result = new SearchCompanyRegistryHandler(new FixtureCompanyRegistry())(new SearchCompanyRegistry('limite'));

        self::assertFalse($result->available);
        self::assertSame('throttled', $result->reason);
        self::assertSame([], $result->hits);
    }

    public function testAnOutageIsDown(): void
    {
        $result = new SearchCompanyRegistryHandler(new FixtureCompanyRegistry())(new SearchCompanyRegistry('indisponible'));

        self::assertFalse($result->available);
        self::assertSame('down', $result->reason);
    }
}
