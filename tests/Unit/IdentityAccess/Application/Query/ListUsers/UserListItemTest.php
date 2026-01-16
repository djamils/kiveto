<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Query\ListUsers;

use App\IdentityAccess\Application\Query\ListUsers\UserListItem;
use PHPUnit\Framework\TestCase;

final class UserListItemTest extends TestCase
{
    public function testConstructorWithAllProperties(): void
    {
        $emailVerifiedAt = new \DateTimeImmutable('2024-01-15 10:30:00');
        $createdAt       = new \DateTimeImmutable('2024-01-01 08:00:00');

        $item = new UserListItem(
            id: 'user-123',
            email: 'test@example.com',
            type: 'clinic',
            status: 'active',
            emailVerifiedAt: $emailVerifiedAt,
            createdAt: $createdAt,
        );

        self::assertSame('user-123', $item->id);
        self::assertSame('test@example.com', $item->email);
        self::assertSame('clinic', $item->type);
        self::assertSame('active', $item->status);
        self::assertSame($emailVerifiedAt, $item->emailVerifiedAt);
        self::assertSame($createdAt, $item->createdAt);
    }

    public function testConstructorWithNullEmailVerifiedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-01 08:00:00');

        $item = new UserListItem(
            id: 'user-456',
            email: 'unverified@example.com',
            type: 'portal',
            status: 'pending',
            emailVerifiedAt: null,
            createdAt: $createdAt,
        );

        self::assertSame('user-456', $item->id);
        self::assertSame('unverified@example.com', $item->email);
        self::assertSame('portal', $item->type);
        self::assertSame('pending', $item->status);
        self::assertNull($item->emailVerifiedAt);
        self::assertSame($createdAt, $item->createdAt);
    }

    public function testDifferentUserTypes(): void
    {
        $createdAt = new \DateTimeImmutable();

        $clinicUser     = new UserListItem('1', 'clinic@test.com', 'clinic', 'active', null, $createdAt);
        $portalUser     = new UserListItem('2', 'portal@test.com', 'portal', 'active', null, $createdAt);
        $backofficeUser = new UserListItem('3', 'admin@test.com', 'backoffice', 'active', null, $createdAt);

        self::assertSame('clinic', $clinicUser->type);
        self::assertSame('portal', $portalUser->type);
        self::assertSame('backoffice', $backofficeUser->type);
    }

    public function testDifferentStatuses(): void
    {
        $createdAt = new \DateTimeImmutable();

        $activeUser   = new UserListItem('1', 'active@test.com', 'clinic', 'active', null, $createdAt);
        $inactiveUser = new UserListItem('2', 'inactive@test.com', 'clinic', 'inactive', null, $createdAt);
        $pendingUser  = new UserListItem('3', 'pending@test.com', 'clinic', 'pending', null, $createdAt);

        self::assertSame('active', $activeUser->status);
        self::assertSame('inactive', $inactiveUser->status);
        self::assertSame('pending', $pendingUser->status);
    }

    public function testUserListItemIsReadonly(): void
    {
        $reflection = new \ReflectionClass(UserListItem::class);
        self::assertTrue($reflection->isReadOnly());
    }

    public function testEmailVerifiedAtIsNullableDateTime(): void
    {
        $createdAt = new \DateTimeImmutable();

        $verifiedUser = new UserListItem(
            '1',
            'verified@test.com',
            'clinic',
            'active',
            new \DateTimeImmutable('2024-01-10'),
            $createdAt
        );
        $unverifiedUser = new UserListItem('2', 'unverified@test.com', 'clinic', 'pending', null, $createdAt);

        self::assertInstanceOf(\DateTimeImmutable::class, $verifiedUser->emailVerifiedAt);
        self::assertNull($unverifiedUser->emailVerifiedAt);
    }
}
