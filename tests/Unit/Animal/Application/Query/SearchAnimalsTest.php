<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Query;

use App\Animal\Application\Query\SearchAnimals\SearchAnimals;
use App\Shared\Application\Bus\QueryInterface;
use PHPUnit\Framework\TestCase;

final class SearchAnimalsTest extends TestCase
{
    public function testConstruct(): void
    {
        $query = new SearchAnimals(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            searchTerm: 'Rex',
            status: 'ACTIVE',
            species: 'dog',
            lifeStatus: 'alive',
            ownerClientId: 'client-123',
            page: 2,
            limit: 50,
        );

        self::assertInstanceOf(QueryInterface::class, $query);
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $query->clinicId);
        self::assertSame('Rex', $query->searchTerm);
        self::assertSame('ACTIVE', $query->status);
        self::assertSame('dog', $query->species);
        self::assertSame('alive', $query->lifeStatus);
        self::assertSame('client-123', $query->ownerClientId);
        self::assertSame(2, $query->page);
        self::assertSame(50, $query->limit);
    }

    public function testConstructWithDefaults(): void
    {
        $query = new SearchAnimals(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
        );

        self::assertNull($query->searchTerm);
        self::assertNull($query->status);
        self::assertNull($query->species);
        self::assertNull($query->lifeStatus);
        self::assertNull($query->ownerClientId);
        self::assertSame(1, $query->page);
        self::assertSame(20, $query->limit);
    }
}
