<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\ListUserSessions;

use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use App\System\IdentityAccess\Application\Query\ListUserSessions\ListUserSessions;
use App\System\IdentityAccess\Application\Query\ListUserSessions\ListUserSessionsHandler;
use App\System\IdentityAccess\Application\Query\ListUserSessions\UserSessionView;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;

final class ListUserSessionsHandlerTest extends TestCase
{
    private const string USER = '01960000-0000-7000-8000-000000004f01';

    public function testTheSessionsSeenWithinThirtyDaysAreListedWithTheAskingOneFlagged(): void
    {
        $now      = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $view     = new UserSessionView('01960000-0000-7000-8000-000000004fa1', 'Firefox', '203.0.113.7', $now->modify('-2 days'), $now, true);
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->expects(self::once())->method('listActive')
            ->with(self::USER, self::callback(static fn (\DateTimeImmutable $since): bool => '2026-10-07 09:00:00' === $since->format('Y-m-d H:i:s')), hash('sha256', 'php-session-id'))
            ->willReturn([$view])
        ;

        self::assertSame([$view], new ListUserSessionsHandler($sessions, new FrozenClock($now))(new ListUserSessions(self::USER, 'php-session-id')));
        self::assertSame(30, ListUserSessionsHandler::DAYS);
    }

    public function testNoSessionIsFlaggedWithoutTheAskingOne(): void
    {
        $now      = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->expects(self::once())->method('listActive')->with(self::USER, self::anything(), null)->willReturn([]);

        self::assertSame([], new ListUserSessionsHandler($sessions, new FrozenClock($now))(new ListUserSessions(self::USER)));
    }
}
