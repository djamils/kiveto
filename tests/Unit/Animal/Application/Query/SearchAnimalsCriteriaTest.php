<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Query;

use App\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use PHPUnit\Framework\TestCase;

final class SearchAnimalsCriteriaTest extends TestCase
{
    public function testConstruct(): void
    {
        $criteria = new SearchAnimalsCriteria(
            searchTerm: 'Rex',
            status: 'ACTIVE',
            species: 'dog',
            lifeStatus: 'alive',
            ownerClientId: 'client-123',
            page: 3,
            limit: 100,
        );

        self::assertSame('Rex', $criteria->searchTerm);
        self::assertSame('ACTIVE', $criteria->status);
        self::assertSame('dog', $criteria->species);
        self::assertSame('alive', $criteria->lifeStatus);
        self::assertSame('client-123', $criteria->ownerClientId);
        self::assertSame(3, $criteria->page);
        self::assertSame(100, $criteria->limit);
    }

    public function testConstructWithDefaults(): void
    {
        $criteria = new SearchAnimalsCriteria();

        self::assertNull($criteria->searchTerm);
        self::assertNull($criteria->status);
        self::assertNull($criteria->species);
        self::assertNull($criteria->lifeStatus);
        self::assertNull($criteria->ownerClientId);
        self::assertSame(1, $criteria->page);
        self::assertSame(20, $criteria->limit);
    }
}
