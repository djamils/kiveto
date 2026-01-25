<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Query\SearchClients;

use App\Client\Application\Query\SearchClients\SearchClients;
use PHPUnit\Framework\TestCase;

final class SearchClientsTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $query = new SearchClients(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            searchTerm: 'John',
            status: 'active',
            page: 2,
            limit: 50
        );

        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $query->clinicId);
        self::assertSame('John', $query->searchTerm);
        self::assertSame('active', $query->status);
        self::assertSame(2, $query->page);
        self::assertSame(50, $query->limit);
    }

    public function testConstructorDefaultsOptionalFields(): void
    {
        $query = new SearchClients(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0'
        );

        self::assertNull($query->searchTerm);
        self::assertNull($query->status);
        self::assertSame(1, $query->page);
        self::assertSame(20, $query->limit);
    }
}
