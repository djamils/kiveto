<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Query\SearchClients;

use App\Client\Application\Query\SearchClients\SearchClientsCriteria;
use PHPUnit\Framework\TestCase;

final class SearchClientsCriteriaTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $criteria = new SearchClientsCriteria(
            searchTerm: 'John',
            status: 'active',
            page: 2,
            limit: 50
        );

        self::assertSame('John', $criteria->searchTerm);
        self::assertSame('active', $criteria->status);
        self::assertSame(2, $criteria->page);
        self::assertSame(50, $criteria->limit);
    }

    public function testConstructorDefaultsOptionalFields(): void
    {
        $criteria = new SearchClientsCriteria();

        self::assertNull($criteria->searchTerm);
        self::assertNull($criteria->status);
        self::assertSame(1, $criteria->page);
        self::assertSame(20, $criteria->limit);
    }

    public function testOffsetCalculatesCorrectly(): void
    {
        $criteria = new SearchClientsCriteria(page: 1, limit: 20);
        self::assertSame(0, $criteria->offset());

        $criteria = new SearchClientsCriteria(page: 2, limit: 20);
        self::assertSame(20, $criteria->offset());

        $criteria = new SearchClientsCriteria(page: 3, limit: 10);
        self::assertSame(20, $criteria->offset());
    }

    public function testConstructorThrowsExceptionForInvalidPage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be >= 1.');

        new SearchClientsCriteria(page: 0);
    }

    public function testConstructorThrowsExceptionForLimitTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100.');

        new SearchClientsCriteria(limit: 0);
    }

    public function testConstructorThrowsExceptionForLimitTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100.');

        new SearchClientsCriteria(limit: 101);
    }
}
