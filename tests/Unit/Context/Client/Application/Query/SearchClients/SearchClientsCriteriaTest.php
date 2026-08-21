<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\SearchClients;

use App\Context\Client\Application\Query\SearchClients\SearchClientsCriteria;
use PHPUnit\Framework\TestCase;

final class SearchClientsCriteriaTest extends TestCase
{
    public function testDefaultsAreAccepted(): void
    {
        $criteria = new SearchClientsCriteria();

        self::assertNull($criteria->searchTerm);
        self::assertNull($criteria->status);
        self::assertSame(1, $criteria->page);
        self::assertSame(20, $criteria->limit);
        self::assertSame([], $criteria->statuses);
        self::assertSame([], $criteria->cities);
        self::assertSame('name', $criteria->sort);
        self::assertSame('asc', $criteria->direction);
    }

    public function testEveryArgumentIsKept(): void
    {
        $criteria = new SearchClientsCriteria(
            searchTerm: 'Dupont',
            status: 'ACTIVE',
            page: 2,
            limit: 50,
            statuses: ['ACTIVE', 'ARCHIVED'],
            cities: ['Lyon', 'Paris'],
            sort: SearchClientsCriteria::SORT_CITY,
            direction: 'desc',
        );

        self::assertSame('Dupont', $criteria->searchTerm);
        self::assertSame('ACTIVE', $criteria->status);
        self::assertSame(2, $criteria->page);
        self::assertSame(50, $criteria->limit);
        self::assertSame(['ACTIVE', 'ARCHIVED'], $criteria->statuses);
        self::assertSame(['Lyon', 'Paris'], $criteria->cities);
        self::assertSame('city', $criteria->sort);
        self::assertSame('desc', $criteria->direction);
    }

    public function testOffsetIsZeroOnTheFirstPage(): void
    {
        self::assertSame(0, (new SearchClientsCriteria(page: 1, limit: 20))->offset());
    }

    public function testOffsetSkipsThePreviousPages(): void
    {
        self::assertSame(40, (new SearchClientsCriteria(page: 3, limit: 20))->offset());
        self::assertSame(20, (new SearchClientsCriteria(page: 3, limit: 10))->offset());
    }

    public function testSortableColumns(): void
    {
        self::assertSame(
            ['name', 'city', 'created'],
            SearchClientsCriteria::sortableColumns(),
        );
    }

    public function testEverySortableColumnIsAccepted(): void
    {
        foreach (SearchClientsCriteria::sortableColumns() as $column) {
            self::assertSame($column, (new SearchClientsCriteria(sort: $column))->sort);
        }
    }

    public function testBothDirectionsAreAccepted(): void
    {
        self::assertSame('asc', (new SearchClientsCriteria(direction: 'asc'))->direction);
        self::assertSame('desc', (new SearchClientsCriteria(direction: 'desc'))->direction);
    }

    public function testBothLimitBoundsAreAccepted(): void
    {
        self::assertSame(1, (new SearchClientsCriteria(limit: 1))->limit);
        self::assertSame(100, (new SearchClientsCriteria(limit: 100))->limit);
    }

    public function testAPageBelowOneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be >= 1.');

        new SearchClientsCriteria(page: 0);
    }

    public function testALimitBelowOneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100.');

        new SearchClientsCriteria(limit: 0);
    }

    public function testALimitAboveOneHundredIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100.');

        new SearchClientsCriteria(limit: 101);
    }

    public function testAnUnknownSortColumnIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported sort column "species".');

        new SearchClientsCriteria(sort: 'species');
    }

    public function testAnUnknownSortDirectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported sort direction "sideways".');

        new SearchClientsCriteria(direction: 'sideways');
    }
}
