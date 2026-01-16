<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Query\ListUsers;

use App\IdentityAccess\Application\Query\ListUsers\ListUsers;
use PHPUnit\Framework\TestCase;

final class ListUsersTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $query = new ListUsers();

        self::assertNull($query->search);
        self::assertNull($query->type);
        self::assertNull($query->status);
    }

    public function testConstructorWithSearchOnly(): void
    {
        $query = new ListUsers(search: 'john');

        self::assertSame('john', $query->search);
        self::assertNull($query->type);
        self::assertNull($query->status);
    }

    public function testConstructorWithTypeOnly(): void
    {
        $query = new ListUsers(type: 'clinic');

        self::assertNull($query->search);
        self::assertSame('clinic', $query->type);
        self::assertNull($query->status);
    }

    public function testConstructorWithStatusOnly(): void
    {
        $query = new ListUsers(status: 'active');

        self::assertNull($query->search);
        self::assertNull($query->type);
        self::assertSame('active', $query->status);
    }

    public function testConstructorWithAllParameters(): void
    {
        $query = new ListUsers(
            search: 'test search',
            type: 'portal',
            status: 'inactive',
        );

        self::assertSame('test search', $query->search);
        self::assertSame('portal', $query->type);
        self::assertSame('inactive', $query->status);
    }

    public function testConstructorWithEmptyStrings(): void
    {
        $query = new ListUsers(
            search: '',
            type: '',
            status: '',
        );

        self::assertSame('', $query->search);
        self::assertSame('', $query->type);
        self::assertSame('', $query->status);
    }

    public function testQueryIsReadonly(): void
    {
        $query = new ListUsers(search: 'test');

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}
