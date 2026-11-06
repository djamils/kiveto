<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\System\IdentityAccess\Application\Command\ChangeInvitedUserEmail\ChangeInvitedUserEmail;
use App\System\IdentityAccess\Application\Command\ChangeInvitedUserEmail\ChangeInvitedUserEmailHandler;
use App\System\IdentityAccess\Application\Command\DisableClinicAccount\DisableClinicAccount;
use App\System\IdentityAccess\Application\Command\DisableClinicAccount\DisableClinicAccountHandler;
use App\System\IdentityAccess\Application\Command\EnableClinicAccount\EnableClinicAccount;
use App\System\IdentityAccess\Application\Command\EnableClinicAccount\EnableClinicAccountHandler;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use PHPUnit\Framework\TestCase;

/**
 * The console's commands on a clinic account: disable (sessions revoked),
 * enable, correct the address of an invitation.
 */
final class ConsoleAccountCommandsTest extends TestCase
{
    private const string CLAIRE  = '01960000-0000-7000-8000-000000005b01';
    private const string HUGO    = '01960000-0000-7000-8000-000000005b02';
    private const string UNKNOWN = '01960000-0000-7000-8000-000000005b99';

    private InMemoryUsers $users;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
        $this->users = new InMemoryUsers();
        $this->users->save(User::register(UserId::fromString(self::CLAIRE), 'claire@example.com', 'hash', new \DateTimeImmutable('2026-10-01'), UserType::CLINIC));
        $this->users->save(User::invite(UserId::fromString(self::HUGO), 'hugo@exemple.com', new \DateTimeImmutable('2026-10-01'), UserType::CLINIC));
    }

    public function testADisabledAccountLosesItsSessionsThenComesBack(): void
    {
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->expects(self::once())->method('revokeAll')->with(self::CLAIRE, null, $this->clock->now())->willReturn(2);

        self::assertSame(2, (new DisableClinicAccountHandler($this->users, $sessions, $this->clock))(new DisableClinicAccount(self::CLAIRE)));
        self::assertSame(UserStatus::DISABLED, $this->statusOf(self::CLAIRE));
        self::assertContains(self::CLAIRE, $this->users->locked);

        (new EnableClinicAccountHandler($this->users))(new EnableClinicAccount(self::CLAIRE));
        self::assertSame(UserStatus::ACTIVE, $this->statusOf(self::CLAIRE));
    }

    public function testAnUnknownAccountIsNotDisabled(): void
    {
        $this->expectException(\RuntimeException::class);

        (new DisableClinicAccountHandler($this->users, $this->createStub(UserSessionStoreInterface::class), $this->clock))(new DisableClinicAccount(self::UNKNOWN));
    }

    public function testAnUnknownAccountIsNotEnabled(): void
    {
        $this->expectException(\RuntimeException::class);

        (new EnableClinicAccountHandler($this->users))(new EnableClinicAccount(self::UNKNOWN));
    }

    public function testTheAddressOfAnInvitationIsCorrectedAndNormalised(): void
    {
        $this->change(self::HUGO, '  Hugo@Example.com ');

        self::assertSame('hugo@example.com', $this->users->findById(UserId::fromString(self::HUGO))?->email());
    }

    public function testTheSameAddressAgainIsAccepted(): void
    {
        $this->change(self::HUGO, 'hugo@exemple.com');

        self::assertSame('hugo@exemple.com', $this->users->findById(UserId::fromString(self::HUGO))?->email());
    }

    public function testAMalformedAddressIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cette adresse e-mail n’est pas valide.');

        $this->change(self::HUGO, 'pas-une-adresse');
    }

    public function testTheAddressOfAnotherClinicAccountIsRefused(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cette adresse est déjà celle d’un autre compte clinique.');

        $this->change(self::HUGO, 'claire@example.com');
    }

    public function testAnUnknownAccountKeepsNoAddress(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->change(self::UNKNOWN, 'nouveau@example.com');
    }

    private function change(string $userId, string $email): void
    {
        (new ChangeInvitedUserEmailHandler($this->users))(new ChangeInvitedUserEmail($userId, $email));
    }

    private function statusOf(string $userId): ?UserStatus
    {
        return $this->users->findById(UserId::fromString($userId))?->status();
    }
}
