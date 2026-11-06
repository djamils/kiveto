<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain;

use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\ConsoleRole;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\TestCase;

/**
 * A member of the Kiveto team: their role, their invitation link, switched
 * off and on.
 */
final class ConsoleMemberTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-00000000c501';

    public function testTheRoles(): void
    {
        self::assertSame(['Administrateur', 'Support'], array_map(static fn (ConsoleRole $r): string => $r->label(), ConsoleRole::cases()));
        self::assertSame(['ROLE_CONSOLE_ADMIN', 'ROLE_CONSOLE_SUPPORT'], array_map(static fn (ConsoleRole $r): string => $r->securityRole(), ConsoleRole::cases()));
    }

    public function testAConsoleInvitationLastsAWeekLikeAClinicOne(): void
    {
        self::assertSame(['P7D', 'PT30M', 'P7D'], array_map(
            static fn (TokenPurpose $p): string => \sprintf('P%s%s', $p->lifetime()->d > 0 ? $p->lifetime()->d . 'D' : '', $p->lifetime()->i > 0 ? 'T' . $p->lifetime()->i . 'M' : ''),
            [TokenPurpose::INVITATION, TokenPurpose::PASSWORD_RESET, TokenPurpose::CONSOLE_INVITATION],
        ));
        self::assertSame([true, false, true], [TokenPurpose::INVITATION->isInvitation(), TokenPurpose::PASSWORD_RESET->isInvitation(), TokenPurpose::CONSOLE_INVITATION->isInvitation()]);
    }

    public function testAMemberIsSwitchedOffThenOnAsTheyWere(): void
    {
        $invited = User::invite(UserId::fromString(self::ID), 'lea@kiveto.app', new \DateTimeImmutable(), UserType::BACKOFFICE);
        $invited->disableConsoleMember();
        self::assertSame(UserStatus::DISABLED, $invited->status());
        $invited->enableConsoleMember();
        self::assertSame(UserStatus::PENDING, $invited->status(), 'still waiting for their password');

        $active = User::invite(UserId::fromString(self::ID), 'lea@kiveto.app', new \DateTimeImmutable(), UserType::BACKOFFICE);
        $active->activate('$argon2id$hash', new \DateTimeImmutable());
        $active->enableConsoleMember();
        self::assertSame(UserStatus::ACTIVE, $active->status(), 'not disabled: left as is');
        $active->disableConsoleMember();
        $active->enableConsoleMember();
        self::assertSame(UserStatus::ACTIVE, $active->status());
    }

    public function testOnlyAConsoleAccountIsAMemberOfTheTeam(): void
    {
        $clinic = User::invite(UserId::fromString(self::ID), 'vet@clinic.test', new \DateTimeImmutable(), UserType::CLINIC);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only a console account is a member of the Kiveto team.');

        $clinic->disableConsoleMember();
    }
}
