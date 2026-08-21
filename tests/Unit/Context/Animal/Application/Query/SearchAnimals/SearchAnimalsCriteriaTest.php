<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\SearchAnimals;

use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use PHPUnit\Framework\TestCase;

final class SearchAnimalsCriteriaTest extends TestCase
{
    private const string ANIMAL_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLIENT_ID = '22222222-2222-4222-8222-222222222222';

    public function testDefaultsAreAccepted(): void
    {
        $criteria = new SearchAnimalsCriteria();

        self::assertNull($criteria->searchTerm);
        self::assertNull($criteria->status);
        self::assertNull($criteria->species);
        self::assertNull($criteria->lifeStatus);
        self::assertNull($criteria->ownerClientId);
        self::assertSame(1, $criteria->page);
        self::assertSame(20, $criteria->limit);
        self::assertSame([], $criteria->speciesList);
        self::assertSame([], $criteria->lifeStatuses);
        self::assertNull($criteria->restrictToIds);
        self::assertSame('name', $criteria->sort);
        self::assertSame('asc', $criteria->direction);
    }

    public function testEveryArgumentIsKept(): void
    {
        $criteria = new SearchAnimalsCriteria(
            searchTerm: 'Rex',
            status: 'ACTIVE',
            species: 'DOG',
            lifeStatus: 'ALIVE',
            ownerClientId: self::CLIENT_ID,
            page: 2,
            limit: 50,
            speciesList: ['DOG', 'CAT'],
            lifeStatuses: ['ALIVE', 'DECEASED'],
            restrictToIds: [self::ANIMAL_ID],
            sort: SearchAnimalsCriteria::SORT_SPECIES,
            direction: 'desc',
        );

        self::assertSame('Rex', $criteria->searchTerm);
        self::assertSame('ACTIVE', $criteria->status);
        self::assertSame('DOG', $criteria->species);
        self::assertSame('ALIVE', $criteria->lifeStatus);
        self::assertSame(self::CLIENT_ID, $criteria->ownerClientId);
        self::assertSame(2, $criteria->page);
        self::assertSame(50, $criteria->limit);
        self::assertSame(['DOG', 'CAT'], $criteria->speciesList);
        self::assertSame(['ALIVE', 'DECEASED'], $criteria->lifeStatuses);
        self::assertSame([self::ANIMAL_ID], $criteria->restrictToIds);
        self::assertSame('species', $criteria->sort);
        self::assertSame('desc', $criteria->direction);
    }

    public function testOffsetIsZeroOnTheFirstPage(): void
    {
        self::assertSame(0, (new SearchAnimalsCriteria(page: 1, limit: 20))->offset());
    }

    public function testOffsetSkipsThePreviousPages(): void
    {
        self::assertSame(40, (new SearchAnimalsCriteria(page: 3, limit: 20))->offset());
        self::assertSame(20, (new SearchAnimalsCriteria(page: 3, limit: 10))->offset());
    }

    public function testAnEmptyIdRestrictionCanNeverMatch(): void
    {
        self::assertTrue((new SearchAnimalsCriteria(restrictToIds: []))->isImpossible());
    }

    public function testNoIdRestrictionIsNotImpossible(): void
    {
        self::assertFalse((new SearchAnimalsCriteria(restrictToIds: null))->isImpossible());
    }

    public function testAFilledIdRestrictionIsNotImpossible(): void
    {
        self::assertFalse((new SearchAnimalsCriteria(restrictToIds: [self::ANIMAL_ID]))->isImpossible());
    }

    public function testSortableColumns(): void
    {
        self::assertSame(
            ['name', 'species', 'status', 'created'],
            SearchAnimalsCriteria::sortableColumns(),
        );
    }

    public function testEverySortableColumnIsAccepted(): void
    {
        foreach (SearchAnimalsCriteria::sortableColumns() as $column) {
            self::assertSame($column, (new SearchAnimalsCriteria(sort: $column))->sort);
        }
    }

    public function testBothDirectionsAreAccepted(): void
    {
        self::assertSame('asc', (new SearchAnimalsCriteria(direction: 'asc'))->direction);
        self::assertSame('desc', (new SearchAnimalsCriteria(direction: 'desc'))->direction);
    }

    public function testBothLimitBoundsAreAccepted(): void
    {
        self::assertSame(1, (new SearchAnimalsCriteria(limit: 1))->limit);
        self::assertSame(100, (new SearchAnimalsCriteria(limit: 100))->limit);
    }

    public function testAPageBelowOneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be >= 1.');

        new SearchAnimalsCriteria(page: 0);
    }

    public function testALimitBelowOneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100.');

        new SearchAnimalsCriteria(limit: 0);
    }

    public function testALimitAboveOneHundredIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100.');

        new SearchAnimalsCriteria(limit: 101);
    }

    public function testAnUnknownSortColumnIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported sort column "city".');

        new SearchAnimalsCriteria(sort: 'city');
    }

    public function testAnUnknownSortDirectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported sort direction "sideways".');

        new SearchAnimalsCriteria(direction: 'sideways');
    }
}
