<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\RecordSupportPage;

use App\System\IdentityAccess\Application\Command\RecordSupportPage\RecordSupportPage;
use App\System\IdentityAccess\Application\Command\RecordSupportPage\RecordSupportPageHandler;
use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\System\IdentityAccess\Domain\Support\SupportSession;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\InMemorySupportSessions;
use PHPUnit\Framework\TestCase;

final class RecordSupportPageHandlerTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-00000000fd01';

    private InMemorySupportSessions $sessions;
    private SupportSession $session;

    protected function setUp(): void
    {
        $this->sessions = new InMemorySupportSessions();
        $this->session  = $this->sessions->add(SupportSession::start(
            self::ID,
            '01960000-0000-7000-8000-00000000fd02',
            '01960000-0000-7000-8000-00000000fd03',
            '01960000-0000-7000-8000-00000000fd04',
            'Djamil',
            'Ticket 42',
            SupportMode::WRITE,
            15,
            'digest',
            new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')),
        ));
        $this->session->claim(new \DateTimeImmutable('2026-11-06 09:00:30', new \DateTimeZone('UTC')));
    }

    public function testThePagesOfAnOpenSessionAreCounted(): void
    {
        self::assertTrue($this->record(self::ID, false, '2026-11-06 09:05:00'));
        self::assertTrue($this->record(self::ID, true, '2026-11-06 09:06:00'));

        self::assertSame([[self::ID, self::ID], 2, 1], [$this->sessions->saved, $this->session->pages(), $this->session->writes()]);
    }

    public function testASessionThatRanOutOrEndedCountsNothing(): void
    {
        self::assertFalse($this->record(self::ID, false, '2026-11-06 09:15:00'), 'ran out');

        $this->session->end(new \DateTimeImmutable('2026-11-06 09:10:00', new \DateTimeZone('UTC')));
        self::assertFalse($this->record(self::ID, true, '2026-11-06 09:11:00'), 'ended');

        self::assertSame([[], 0, 0], [$this->sessions->saved, $this->session->pages(), $this->session->writes()]);
    }

    public function testAnUnknownSessionCountsNothing(): void
    {
        self::assertFalse($this->record('01960000-0000-7000-8000-00000000fdff', false, '2026-11-06 09:05:00'));
        self::assertSame([], $this->sessions->saved);
    }

    private function record(string $sessionId, bool $write, string $at): bool
    {
        return new RecordSupportPageHandler($this->sessions, new FrozenClock(new \DateTimeImmutable($at, new \DateTimeZone('UTC'))))(new RecordSupportPage($sessionId, $write));
    }
}
