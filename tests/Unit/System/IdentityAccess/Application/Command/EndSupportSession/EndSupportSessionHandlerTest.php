<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\EndSupportSession;

use App\System\IdentityAccess\Application\Command\EndSupportSession\EndSupportSession;
use App\System\IdentityAccess\Application\Command\EndSupportSession\EndSupportSessionHandler;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\System\IdentityAccess\Domain\Support\SupportSession;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\InMemorySupportSessions;
use PHPUnit\Framework\TestCase;

final class EndSupportSessionHandlerTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-00000000fe01';

    public function testASessionEndsOnce(): void
    {
        $sessions = new InMemorySupportSessions();
        $session  = $sessions->add(SupportSession::start(
            self::ID,
            '01960000-0000-7000-8000-00000000fe02',
            '01960000-0000-7000-8000-00000000fe03',
            '01960000-0000-7000-8000-00000000fe04',
            'Djamil',
            'Ticket 42',
            SupportMode::READ,
            30,
            'digest',
            new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')),
        ));
        $session->claim(new \DateTimeImmutable('2026-11-06 09:01:00', new \DateTimeZone('UTC')));

        $first = $this->end($sessions, self::ID, '2026-11-06 09:13:00');
        self::assertNotNull($first);
        self::assertTrue($first['ended']);
        self::assertSame([self::ID, '09:13', 12], [$first['session']->id, $first['session']->endedAt?->format('H:i'), $first['session']->minutes]);
        self::assertSame([self::ID], $sessions->saved);

        $second = $this->end($sessions, self::ID, '2026-11-06 09:20:00');
        self::assertNotNull($second);
        self::assertFalse($second['ended'], 'already ended');
        self::assertSame('09:13', $second['session']->endedAt?->format('H:i'));
        self::assertSame([self::ID], $sessions->saved, 'not saved again');
    }

    public function testAnUnknownSessionAnswersNull(): void
    {
        $sessions = new InMemorySupportSessions();

        self::assertNull($this->end($sessions, self::ID, '2026-11-06 09:13:00'));
        self::assertSame([], $sessions->saved);
    }

    /**
     * @return array{session: SupportSessionView, ended: bool}|null
     */
    private function end(InMemorySupportSessions $sessions, string $sessionId, string $at): ?array
    {
        return new EndSupportSessionHandler($sessions, new FrozenClock(new \DateTimeImmutable($at, new \DateTimeZone('UTC'))))(new EndSupportSession($sessionId));
    }
}
