<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Query\ListUsers;

use App\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\IdentityAccess\Application\Query\ListUsers\ListUsers;
use App\IdentityAccess\Application\Query\ListUsers\ListUsersHandler;
use App\IdentityAccess\Application\Query\ListUsers\UserCollection;
use App\IdentityAccess\Application\Query\ListUsers\UserListItem;
use PHPUnit\Framework\TestCase;

final class ListUsersHandlerTest extends TestCase
{
    public function testInvokeWithoutFilters(): void
    {
        $users = [
            new UserListItem(
                id: 'user-1',
                email: 'user1@example.com',
                type: 'clinic',
                status: 'active',
                emailVerifiedAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
                createdAt: new \DateTimeImmutable('2024-01-01 09:00:00'),
            ),
            new UserListItem(
                id: 'user-2',
                email: 'user2@example.com',
                type: 'portal',
                status: 'inactive',
                emailVerifiedAt: null,
                createdAt: new \DateTimeImmutable('2024-01-02 09:00:00'),
            ),
        ];

        $expectedCollection = new UserCollection($users, 2);

        $repository = $this->createStub(UserReadRepositoryInterface::class);
        $repository->method('listAll')->willReturn($expectedCollection);

        $handler = new ListUsersHandler($repository);
        $query   = new ListUsers();
        $result  = $handler($query);

        self::assertSame($expectedCollection, $result);
        self::assertCount(2, $result->users);
        self::assertSame(2, $result->total);
    }

    public function testInvokeWithSearchFilter(): void
    {
        $users = [
            new UserListItem(
                id: 'user-1',
                email: 'john@example.com',
                type: 'clinic',
                status: 'active',
                emailVerifiedAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
                createdAt: new \DateTimeImmutable('2024-01-01 09:00:00'),
            ),
        ];

        $expectedCollection = new UserCollection($users, 1);

        $repository = $this->createStub(UserReadRepositoryInterface::class);
        $repository->method('listAll')
            ->with('john', null, null)
            ->willReturn($expectedCollection)
        ;

        $handler = new ListUsersHandler($repository);
        $query   = new ListUsers(search: 'john');
        $result  = $handler($query);

        self::assertSame($expectedCollection, $result);
        self::assertCount(1, $result->users);
        self::assertSame('john@example.com', $result->users[0]->email);
    }

    public function testInvokeWithTypeFilter(): void
    {
        $users = [
            new UserListItem(
                id: 'user-1',
                email: 'clinic@example.com',
                type: 'clinic',
                status: 'active',
                emailVerifiedAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
                createdAt: new \DateTimeImmutable('2024-01-01 09:00:00'),
            ),
        ];

        $expectedCollection = new UserCollection($users, 1);

        $repository = $this->createStub(UserReadRepositoryInterface::class);
        $repository->method('listAll')
            ->with(null, 'clinic', null)
            ->willReturn($expectedCollection)
        ;

        $handler = new ListUsersHandler($repository);
        $query   = new ListUsers(type: 'clinic');
        $result  = $handler($query);

        self::assertSame($expectedCollection, $result);
        self::assertCount(1, $result->users);
        self::assertSame('clinic', $result->users[0]->type);
    }

    public function testInvokeWithStatusFilter(): void
    {
        $users = [
            new UserListItem(
                id: 'user-1',
                email: 'active@example.com',
                type: 'clinic',
                status: 'active',
                emailVerifiedAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
                createdAt: new \DateTimeImmutable('2024-01-01 09:00:00'),
            ),
        ];

        $expectedCollection = new UserCollection($users, 1);

        $repository = $this->createStub(UserReadRepositoryInterface::class);
        $repository->method('listAll')
            ->with(null, null, 'active')
            ->willReturn($expectedCollection)
        ;

        $handler = new ListUsersHandler($repository);
        $query   = new ListUsers(status: 'active');
        $result  = $handler($query);

        self::assertSame($expectedCollection, $result);
        self::assertCount(1, $result->users);
        self::assertSame('active', $result->users[0]->status);
    }

    public function testInvokeWithAllFilters(): void
    {
        $users = [
            new UserListItem(
                id: 'user-1',
                email: 'john@clinic.com',
                type: 'clinic',
                status: 'active',
                emailVerifiedAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
                createdAt: new \DateTimeImmutable('2024-01-01 09:00:00'),
            ),
        ];

        $expectedCollection = new UserCollection($users, 1);

        $repository = $this->createStub(UserReadRepositoryInterface::class);
        $repository->method('listAll')
            ->with('john', 'clinic', 'active')
            ->willReturn($expectedCollection)
        ;

        $handler = new ListUsersHandler($repository);
        $query   = new ListUsers(search: 'john', type: 'clinic', status: 'active');
        $result  = $handler($query);

        self::assertSame($expectedCollection, $result);
        self::assertCount(1, $result->users);
        self::assertSame('john@clinic.com', $result->users[0]->email);
        self::assertSame('clinic', $result->users[0]->type);
        self::assertSame('active', $result->users[0]->status);
    }

    public function testInvokeReturnsEmptyCollection(): void
    {
        $expectedCollection = new UserCollection([], 0);

        $repository = $this->createStub(UserReadRepositoryInterface::class);
        $repository->method('listAll')->willReturn($expectedCollection);

        $handler = new ListUsersHandler($repository);
        $query   = new ListUsers(search: 'nonexistent');
        $result  = $handler($query);

        self::assertSame($expectedCollection, $result);
        self::assertCount(0, $result->users);
        self::assertSame(0, $result->total);
    }

    public function testUserListItemWithNullEmailVerifiedAt(): void
    {
        $users = [
            new UserListItem(
                id: 'user-1',
                email: 'unverified@example.com',
                type: 'clinic',
                status: 'pending',
                emailVerifiedAt: null,
                createdAt: new \DateTimeImmutable('2024-01-01 09:00:00'),
            ),
        ];

        $expectedCollection = new UserCollection($users, 1);

        $repository = $this->createStub(UserReadRepositoryInterface::class);
        $repository->method('listAll')->willReturn($expectedCollection);

        $handler = new ListUsersHandler($repository);
        $query   = new ListUsers();
        $result  = $handler($query);

        self::assertCount(1, $result->users);
        self::assertNull($result->users[0]->emailVerifiedAt);
        self::assertSame('pending', $result->users[0]->status);
    }
}
