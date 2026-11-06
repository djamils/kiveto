<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Exception\AccountStateException;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\TestCase;

/**
 * What the Kiveto console does to a clinic account: turn it off and back
 * on, correct the address of an invitation.
 */
final class UserStaffConsoleTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-000000005a01';

    public function testAnActiveAccountComesBackActive(): void
    {
        $user = User::register(UserId::fromString(self::ID), 'claire@example.com', 'hash', new \DateTimeImmutable('2026-10-01'), UserType::CLINIC);

        $user->disableStaff();
        self::assertSame(UserStatus::DISABLED, $user->status());

        $user->disableStaff();
        self::assertSame(UserStatus::DISABLED, $user->status(), 'a disabled account stays so');

        $user->enableStaff();
        self::assertSame(UserStatus::ACTIVE, $user->status());

        $user->enableStaff();
        self::assertSame(UserStatus::ACTIVE, $user->status(), 'an account not disabled is left as is');
    }

    public function testAnInvitedAccountComesBackWaiting(): void
    {
        $user = User::invite(UserId::fromString(self::ID), 'hugo@example.com', new \DateTimeImmutable('2026-10-01'), UserType::CLINIC);

        $user->disableStaff();
        $user->enableStaff();

        self::assertSame(UserStatus::PENDING, $user->status());
    }

    public function testTheAddressOfAnInvitationIsCorrected(): void
    {
        $user = User::invite(UserId::fromString(self::ID), 'hugo@exemple.com', new \DateTimeImmutable('2026-10-01'), UserType::CLINIC);

        $user->changeInvitedEmail('hugo@example.com');

        self::assertSame('hugo@example.com', $user->email());
    }

    public function testTheAddressOfAnActiveAccountIsNotCorrectedThisWay(): void
    {
        $user = User::register(UserId::fromString(self::ID), 'claire@example.com', 'hash', new \DateTimeImmutable('2026-10-01'), UserType::CLINIC);

        $this->expectException(AccountStateException::class);

        $user->changeInvitedEmail('autre@example.com');
    }

    /**
     * @return iterable<string, array{\Closure(User): void}>
     */
    public static function provideOnlyAClinicAccountIsManagedFromTheConsoleCases(): iterable
    {
        yield 'disable' => [static fn (User $user) => $user->disableStaff()];

        yield 'enable' => [static fn (User $user) => $user->enableStaff()];

        yield 'change the e-mail' => [static fn (User $user) => $user->changeInvitedEmail('a@example.com')];
    }

    /**
     * @param \Closure(User): void $action
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideOnlyAClinicAccountIsManagedFromTheConsoleCases')]
    public function testOnlyAClinicAccountIsManagedFromTheConsole(\Closure $action): void
    {
        $owner = User::registerPortal(UserId::fromString(self::ID), PhoneNumber::fromString('+33612345678'), null, new \DateTimeImmutable('2026-10-01'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only a clinic account is managed from the console.');

        $action($owner);
    }
}
