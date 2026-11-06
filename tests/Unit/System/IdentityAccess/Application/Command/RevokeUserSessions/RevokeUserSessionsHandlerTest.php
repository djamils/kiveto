<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\RevokeUserSessions;

use App\System\IdentityAccess\Application\Command\RevokeUserSessions\RevokeUserSessions;
use App\System\IdentityAccess\Application\Command\RevokeUserSessions\RevokeUserSessionsHandler;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RevokeUserSessionsHandlerTest extends TestCase
{
    private const string USER   = '01960000-0000-7000-8000-000000004a01';
    private const string RECORD = '01960000-0000-7000-8000-000000004aa1';

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
    }

    #[DataProvider('provideOneSessionIsRevokedByItsRecordCases')]
    public function testOneSessionIsRevokedByItsRecord(bool $revoked, int $count): void
    {
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->expects(self::once())->method('revoke')->with(self::USER, self::RECORD, $this->clock->now())->willReturn($revoked);
        $sessions->expects(self::never())->method('revokeAll');

        self::assertSame($count, $this->revoke($sessions, new RevokeUserSessions(self::USER, self::RECORD, 'ignored-session-id')));
    }

    /**
     * @return iterable<string, array{bool, int}>
     */
    public static function provideOneSessionIsRevokedByItsRecordCases(): iterable
    {
        yield 'revoked' => [true, 1];

        yield 'unknown, foreign or already revoked' => [false, 0];
    }

    public function testEverySessionButTheAskingBrowsersOwnIsRevoked(): void
    {
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->expects(self::once())->method('revokeAll')->with(self::USER, hash('sha256', 'php-session-id'), $this->clock->now())->willReturn(4);
        $sessions->expects(self::never())->method('revoke');

        self::assertSame(4, $this->revoke($sessions, new RevokeUserSessions(self::USER, keepSessionId: 'php-session-id')));
    }

    public function testEverySessionIsRevokedWhenNoneIsKept(): void
    {
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->expects(self::once())->method('revokeAll')->with(self::USER, null, $this->clock->now())->willReturn(2);

        self::assertSame(2, $this->revoke($sessions, new RevokeUserSessions(self::USER)));
    }

    private function revoke(UserSessionStoreInterface $sessions, RevokeUserSessions $command): int
    {
        return new RevokeUserSessionsHandler($sessions, $this->clock)($command);
    }
}
