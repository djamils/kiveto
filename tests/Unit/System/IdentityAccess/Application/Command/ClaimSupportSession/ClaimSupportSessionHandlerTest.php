<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\ClaimSupportSession;

use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use App\System\IdentityAccess\Application\Command\ClaimSupportSession\ClaimSupportSession;
use App\System\IdentityAccess\Application\Command\ClaimSupportSession\ClaimSupportSessionHandler;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\System\IdentityAccess\Domain\Support\SupportSession;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\InMemorySupportSessions;
use PHPUnit\Framework\TestCase;

final class ClaimSupportSessionHandlerTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-00000000fc01';

    private InMemorySupportSessions $sessions;

    protected function setUp(): void
    {
        $this->sessions = new InMemorySupportSessions();
        $this->sessions->add(SupportSession::start(
            self::ID,
            '01960000-0000-7000-8000-00000000fc02',
            '01960000-0000-7000-8000-00000000fc03',
            '01960000-0000-7000-8000-00000000fc04',
            'Djamil',
            'Ticket 42',
            SupportMode::READ,
            15,
            hash('sha256', 'handoff-secret'),
            new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')),
        ));
    }

    public function testTheClinicHostOpensTheSessionOnce(): void
    {
        $view = $this->claim('handoff-secret', '2026-11-06 09:01:00');

        self::assertInstanceOf(SupportSessionView::class, $view);
        self::assertSame([self::ID, 'read', 14], [$view->id, $view->mode, $view->minutes], 'from the claim to the expiry');
        self::assertSame([[hash('sha256', 'handoff-secret')], [self::ID]], [$this->sessions->locked, $this->sessions->saved]);
        self::assertSame('09:01', $this->sessions->sessions[self::ID]->claimedAt()?->format('H:i'));

        self::assertNull($this->claim('handoff-secret', '2026-11-06 09:01:30'), 'used');
        self::assertSame([self::ID], $this->sessions->saved);
    }

    public function testATooOldHandoffOpensNothing(): void
    {
        self::assertNull($this->claim('handoff-secret', '2026-11-06 09:02:01'));
        self::assertSame([], $this->sessions->saved);
        self::assertNull($this->sessions->sessions[self::ID]->claimedAt());
    }

    public function testAnUnknownSecretOpensNothing(): void
    {
        self::assertNull($this->claim('another-secret', '2026-11-06 09:01:00'));
        self::assertSame([], $this->sessions->saved);
    }

    private function claim(string $secret, string $at): ?SupportSessionView
    {
        return new ClaimSupportSessionHandler($this->sessions, new OpaqueTokenHasher(), new FrozenClock(new \DateTimeImmutable($at, new \DateTimeZone('UTC'))))(
            new ClaimSupportSession($secret),
        );
    }
}
