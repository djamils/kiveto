<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\TestCase;

/** Account deletion (S5g): the owner account is disabled at once, anonymised later. */
final class UserAnonymiseTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-0000000003a1';

    public function testAnOwnerAccountIsDisabledThenAnonymised(): void
    {
        $at   = new \DateTimeImmutable('2026-11-01 14:00:00');
        $user = User::registerPortal(UserId::fromString(self::ID), PhoneNumber::fromString('+33612340301'), 'owner@example.com', new \DateTimeImmutable('2026-10-01'));
        $user->confirmEmail('owner@example.com', new \DateTimeImmutable('2026-10-02'));

        $user->disablePortal();
        self::assertSame(UserStatus::DISABLED, $user->status());
        self::assertSame('+33612340301', $user->phone()?->toString(), 'nothing erased yet');

        $user->disablePortal();
        self::assertSame(UserStatus::DISABLED, $user->status(), 'a disabled account stays so');

        $user->anonymisePortal($at);
        self::assertSame(
            [null, null, null, null, $at, UserStatus::DISABLED],
            [$user->phone(), $user->phoneVerifiedAt(), $user->email(), $user->emailVerifiedAt(), $user->anonymisedAt(), $user->status()],
        );

        $user->anonymisePortal(new \DateTimeImmutable('2026-11-02'));
        self::assertSame($at, $user->anonymisedAt(), 'done once');
    }

    public function testOnlyADisabledAccountIsAnonymised(): void
    {
        $user = User::registerPortal(UserId::fromString(self::ID), PhoneNumber::fromString('+33612340302'), null, new \DateTimeImmutable('2026-10-01'));

        $this->expectException(\LogicException::class);
        $user->anonymisePortal(new \DateTimeImmutable('2026-11-01'));
    }

    public function testAStaffAccountIsNeverDisabledFromThePortal(): void
    {
        $staff = User::register(UserId::fromString(self::ID), 'vet@example.com', 'hash', new \DateTimeImmutable('2026-10-01'), UserType::CLINIC);

        $this->expectException(\LogicException::class);
        $staff->disablePortal();
    }

    public function testAStaffAccountIsNeverAnonymised(): void
    {
        $staff = User::reconstitute(UserId::fromString(self::ID), 'vet@example.com', 'hash', new \DateTimeImmutable('2026-10-01'), UserStatus::DISABLED, type: UserType::CLINIC);

        $this->expectException(\LogicException::class);
        $staff->anonymisePortal(new \DateTimeImmutable('2026-11-01'));
    }

    public function testReconstituteRestoresTheAnonymisation(): void
    {
        $at   = new \DateTimeImmutable('2026-11-01 14:00:00');
        $user = User::reconstitute(UserId::fromString(self::ID), null, 'hash', new \DateTimeImmutable('2026-10-01'), UserStatus::DISABLED, type: UserType::PORTAL, anonymisedAt: $at);

        self::assertSame($at, $user->anonymisedAt());
    }
}
