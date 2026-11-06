<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\StartSupportSession;

use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use App\System\IdentityAccess\Application\Command\StartSupportSession\StartSupportSession;
use App\System\IdentityAccess\Application\Command\StartSupportSession\StartSupportSessionHandler;
use App\System\IdentityAccess\Application\Command\StartSupportSession\SupportHandoff;
use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use App\Tests\Unit\System\IdentityAccess\Support\InMemorySupportSessions;
use PHPUnit\Framework\TestCase;

final class StartSupportSessionHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000fb01';
    private const string USER   = '01960000-0000-7000-8000-00000000fb02';
    private const string ADMIN  = '01960000-0000-7000-8000-00000000fb03';

    private InMemorySupportSessions $sessions;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->sessions = new InMemorySupportSessions();
        $this->clock    = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
    }

    /**
     * The hand-off link carries the secret; only its digest is stored.
     */
    public function testTheSessionIsSavedAndHandedOffByALinkToTheClinicHost(): void
    {
        $handoff = $this->start('write', 60);

        self::assertSame('01960000-0000-7000-8000-000000000001', $handoff->sessionId);
        self::assertStringStartsWith('https://clinic/support/', $handoff->url);

        $session = $this->sessions->find($handoff->sessionId);
        self::assertNotNull($session);
        self::assertSame([[$handoff->sessionId], hash('sha256', AccountFakes::secretOf($handoff->url))], [$this->sessions->saved, $session->handoffHash()]);
        self::assertSame([self::CLINIC, self::USER, self::ADMIN, 'Djamil Soidiki', 'Ticket 42', SupportMode::WRITE, $this->clock->now(), '2026-11-06 10:00:00'], [
            $session->clinicId(), $session->userId(), $session->adminId(), $session->adminName(), $session->reason(), $session->mode(),
            $session->startedAt(), $session->expiresAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function testAnUnknownModeIsRefused(): void
    {
        try {
            $this->start('admin', 15);
            self::fail('An unknown mode was expected to be refused.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Mode de session inconnu.', $e->getMessage());
        }

        self::assertSame([], $this->sessions->saved);
    }

    public function testADurationNotOfferedIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Durée de session non proposée.');

        $this->start('read', 90);
    }

    private function start(string $mode, int $minutes): SupportHandoff
    {
        $fakes = new AccountFakes();

        return new StartSupportSessionHandler($this->sessions, $fakes, new OpaqueTokenHasher(), $fakes, $this->clock)(
            new StartSupportSession(self::CLINIC, self::USER, self::ADMIN, 'Djamil Soidiki', 'Ticket 42', $mode, $minutes),
        );
    }
}
