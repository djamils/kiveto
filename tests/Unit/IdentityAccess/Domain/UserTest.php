<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Domain;

use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegisterRecordsDomainEvent(): void
    {
        $userId    = UserId::fromString('11111111-1111-1111-1111-111111111111');
        $createdAt = new \DateTimeImmutable('2025-01-01T10:00:00+00:00');

        $user = User::register(
            $userId,
            'user@example.com',
            '$hashed',
            $createdAt,
            UserType::CLINIC,
        );

        $events = $user->recordedDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];

        self::assertSame('identity-access.user.registered.v1', $event->type());
        self::assertSame('11111111-1111-1111-1111-111111111111', $event->aggregateId());
        self::assertSame(UserStatus::ACTIVE->value, $user->status()->value);
        self::assertNull($user->emailVerifiedAt());
    }

    public function testReconstituteKeepsStateAndDoesNotRecordEvents(): void
    {
        $userId          = UserId::fromString('22222222-2222-2222-2222-222222222222');
        $createdAt       = new \DateTimeImmutable('2025-02-01T10:00:00+00:00');
        $emailVerifiedAt = new \DateTimeImmutable('2025-02-02T10:00:00+00:00');

        $user = User::reconstitute(
            $userId,
            'portal@example.com',
            '$hash',
            $createdAt,
            UserStatus::DISABLED,
            $emailVerifiedAt,
            UserType::PORTAL,
        );

        self::assertSame($userId->toString(), $user->id()->toString());
        self::assertSame('portal@example.com', $user->email());
        self::assertSame('$hash', $user->passwordHash());
        self::assertSame(
            $createdAt->format(\DateTimeInterface::ATOM),
            $user->createdAt()->format(\DateTimeInterface::ATOM),
        );
        self::assertSame(UserStatus::DISABLED, $user->status());
        self::assertSame(
            $emailVerifiedAt->format(\DateTimeInterface::ATOM),
            $user->emailVerifiedAt()?->format(\DateTimeInterface::ATOM),
        );
        self::assertSame(UserType::PORTAL, $user->type());
        self::assertSame([], $user->recordedDomainEvents(), 'Reconstitute must not record new domain events');
    }

    public function testReconstituteDefaultsTypeWhenMissing(): void
    {
        $user = User::reconstitute(
            UserId::fromString('33333333-3333-3333-3333-333333333333'),
            'clinic@example.com',
            '$hash',
            new \DateTimeImmutable('2025-03-01T10:00:00+00:00'),
            UserStatus::ACTIVE,
        );

        self::assertSame(UserType::CLINIC, $user->type());
    }
}
