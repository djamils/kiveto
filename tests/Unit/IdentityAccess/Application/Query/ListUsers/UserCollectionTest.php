<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Query\ListUsers;

use App\IdentityAccess\Application\Query\ListUsers\UserCollection;
use App\IdentityAccess\Application\Query\ListUsers\UserListItem;
use PHPUnit\Framework\TestCase;

final class UserCollectionTest extends TestCase
{
    public function testConstructorWithEmptyUsers(): void
    {
        $collection = new UserCollection([], 0);

        self::assertSame([], $collection->users);
        self::assertSame(0, $collection->total);
    }

    public function testConstructorWithSingleUser(): void
    {
        $user = new UserListItem(
            id: 'user-1',
            email: 'test@example.com',
            type: 'clinic',
            status: 'active',
            emailVerifiedAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        $collection = new UserCollection([$user], 1);

        self::assertCount(1, $collection->users);
        self::assertSame($user, $collection->users[0]);
        self::assertSame(1, $collection->total);
    }

    public function testConstructorWithMultipleUsers(): void
    {
        $user1 = new UserListItem(
            id: 'user-1',
            email: 'user1@example.com',
            type: 'clinic',
            status: 'active',
            emailVerifiedAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        $user2 = new UserListItem(
            id: 'user-2',
            email: 'user2@example.com',
            type: 'portal',
            status: 'inactive',
            emailVerifiedAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        $user3 = new UserListItem(
            id: 'user-3',
            email: 'user3@example.com',
            type: 'backoffice',
            status: 'active',
            emailVerifiedAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        $collection = new UserCollection([$user1, $user2, $user3], 3);

        self::assertCount(3, $collection->users);
        self::assertSame($user1, $collection->users[0]);
        self::assertSame($user2, $collection->users[1]);
        self::assertSame($user3, $collection->users[2]);
        self::assertSame(3, $collection->total);
    }

    public function testTotalCanBeDifferentFromUsersCount(): void
    {
        $user1 = new UserListItem('1', 'user1@test.com', 'clinic', 'active', null, new \DateTimeImmutable());
        $user2 = new UserListItem('2', 'user2@test.com', 'clinic', 'active', null, new \DateTimeImmutable());

        $collection = new UserCollection([$user1, $user2], 100);

        self::assertCount(2, $collection->users);
        self::assertSame(100, $collection->total);
    }

    public function testUserCollectionIsReadonly(): void
    {
        $reflection = new \ReflectionClass(UserCollection::class);
        self::assertTrue($reflection->isReadOnly());
    }

    public function testUsersArrayIsTyped(): void
    {
        $users = [
            new UserListItem('1', 'test1@example.com', 'clinic', 'active', null, new \DateTimeImmutable()),
            new UserListItem('2', 'test2@example.com', 'portal', 'active', null, new \DateTimeImmutable()),
        ];

        $collection = new UserCollection($users, 2);

        foreach ($collection->users as $user) {
            self::assertInstanceOf(UserListItem::class, $user);
        }
    }

    public function testEmptyCollectionWithNonZeroTotal(): void
    {
        $collection = new UserCollection([], 50);

        self::assertCount(0, $collection->users);
        self::assertSame(50, $collection->total);
    }
}
