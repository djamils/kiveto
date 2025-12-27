<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Domain;

use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
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
            \App\IdentityAccess\Domain\ValueObject\UserType::CLINIC,
        );

        $events = $user->recordedDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];

        self::assertSame('identity-access.user.registered.v1', $event->type());
        self::assertSame('11111111-1111-1111-1111-111111111111', $event->aggregateId());
        self::assertSame(\App\IdentityAccess\Domain\ValueObject\UserStatus::ACTIVE->value, $user->status()->value);
        self::assertNull($user->emailVerifiedAt());
    }
}
