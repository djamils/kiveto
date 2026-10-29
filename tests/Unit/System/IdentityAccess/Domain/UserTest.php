<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain;

use App\System\IdentityAccess\Domain\Exception\AccountStateException;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
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

        self::assertSame('identity-access.user.registered.v1', $event->name());
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

    public function testAnInvitedUserIsPendingWithAnUnusablePassword(): void
    {
        $user = User::invite(UserId::fromString('44444444-4444-4444-4444-444444444444'), 'hugo@example.com', new \DateTimeImmutable('2026-10-26 10:00:00'), UserType::CLINIC);

        self::assertSame(UserStatus::PENDING, $user->status());
        self::assertStringStartsWith('!', $user->passwordHash());
        self::assertNull($user->emailVerifiedAt());
        self::assertSame(['identity-access.user.invited.v1'], array_map(static fn ($e): string => $e->name(), $user->pullDomainEvents()));
    }

    public function testActivationChoosesThePasswordOnce(): void
    {
        $user = User::invite(UserId::fromString('44444444-4444-4444-4444-444444444444'), 'hugo@example.com', new \DateTimeImmutable('2026-10-26 10:00:00'), UserType::CLINIC);
        self::assertCount(1, $user->pullDomainEvents());

        $user->activate('$new', new \DateTimeImmutable('2026-10-27 08:00:00+00:00'));

        self::assertSame([UserStatus::ACTIVE, '$new', '2026-10-27 08:00'], [$user->status(), $user->passwordHash(), $user->emailVerifiedAt()?->format('Y-m-d H:i')]);
        self::assertSame([['userId' => '44444444-4444-4444-4444-444444444444', 'occurredAt' => '2026-10-27T08:00:00+00:00']], array_map(static fn ($e): array => $e->payload(), $user->pullDomainEvents()));

        $this->expectException(AccountStateException::class);
        $user->activate('$again', new \DateTimeImmutable());
    }

    public function testOnlyAnActiveUserChangesTheirPassword(): void
    {
        $user   = User::reconstitute(UserId::fromString('55555555-5555-5555-5555-555555555555'), 'a@example.com', '$old', new \DateTimeImmutable(), UserStatus::ACTIVE);
        $before = $user->passwordFingerprint();

        $user->changePassword('$new', new \DateTimeImmutable('2026-10-27 08:00:00+00:00'));

        self::assertSame('$new', $user->passwordHash());
        self::assertSame(substr(hash('sha256', '$new'), 0, 16), $user->passwordFingerprint());
        self::assertNotSame($before, $user->passwordFingerprint());
        self::assertSame(['identity-access.user-password.changed.v1'], array_map(static fn ($e): string => $e->name(), $user->pullDomainEvents()));

        $pending = User::reconstitute(UserId::fromString('66666666-6666-6666-6666-666666666666'), 'b@example.com', '!x', new \DateTimeImmutable(), UserStatus::PENDING);
        $this->expectException(AccountStateException::class);
        $this->expectExceptionMessage('Only an ACTIVE account can change its password (status PENDING).');
        $pending->changePassword('$new', new \DateTimeImmutable());
    }
}
