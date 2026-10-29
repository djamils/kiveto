<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\GetInvitationStatuses;

use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatuses;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatusesHandler;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenId;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryAccountTokens;
use PHPUnit\Framework\TestCase;

final class GetInvitationStatusesHandlerTest extends TestCase
{
    private const string CLINIC_A = 'clinic-a';
    private const string CLINIC_B = 'clinic-b';

    public function testEachUserStandsWithTheInvitationOfThisClinic(): void
    {
        $users = $this->createMock(UserReadRepositoryInterface::class);
        $users->expects(self::once())->method('statuses')->with(['invited', 'expired', 'elsewhere', 'active', 'off', 'unknown'])->willReturn([
            'invited'   => 'PENDING',
            'expired'   => 'PENDING',
            'elsewhere' => 'PENDING',
            'active'    => 'ACTIVE',
            'off'       => 'DISABLED',
        ]);

        $tokens = new InMemoryAccountTokens();
        $tokens->save(AccountToken::issue(AccountTokenId::fromString('t1'), 'invited', TokenPurpose::INVITATION, self::CLINIC_A, 's1', new \DateTimeImmutable('2026-10-25 10:00:00')));
        $tokens->save(AccountToken::issue(AccountTokenId::fromString('t2'), 'expired', TokenPurpose::INVITATION, self::CLINIC_A, 's2', new \DateTimeImmutable('2026-10-20 10:00:00')));
        $tokens->save(AccountToken::issue(AccountTokenId::fromString('t3'), 'elsewhere', TokenPurpose::INVITATION, self::CLINIC_B, 's3', new \DateTimeImmutable('2026-10-25 10:00:00')));

        $handler = new GetInvitationStatusesHandler($users, $tokens, new FrozenClock(new \DateTimeImmutable('2026-10-26 10:00:00')));

        self::assertSame([
            'invited'   => 'PENDING_VALID',
            'expired'   => 'PENDING_EXPIRED',
            'elsewhere' => 'PENDING_EXPIRED',
            'active'    => 'ACTIVE',
        ], $handler(new GetInvitationStatuses(self::CLINIC_A, ['invited', 'expired', 'elsewhere', 'active', 'off', 'unknown', 'invited'])));
    }

    public function testNoUserNoQuestion(): void
    {
        $users = $this->createMock(UserReadRepositoryInterface::class);
        $users->expects(self::never())->method('statuses');

        self::assertSame([], new GetInvitationStatusesHandler($users, new InMemoryAccountTokens(), new FrozenClock(new \DateTimeImmutable()))(new GetInvitationStatuses(self::CLINIC_A, [])));
    }
}
