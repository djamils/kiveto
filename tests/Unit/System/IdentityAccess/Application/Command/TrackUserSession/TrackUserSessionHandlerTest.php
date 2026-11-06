<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\TrackUserSession;

use App\System\IdentityAccess\Application\Command\TrackUserSession\TrackUserSession;
use App\System\IdentityAccess\Application\Command\TrackUserSession\TrackUserSessionHandler;
use App\System\IdentityAccess\Application\Port\UserSessionRecord;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrackUserSessionHandlerTest extends TestCase
{
    private const string USER    = '01960000-0000-7000-8000-000000003f01';
    private const string OTHER   = '01960000-0000-7000-8000-000000003f02';
    private const string SESSION = 'php-session-id';

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
    }

    public function testAnUnknownSessionIsOpenedByTheDigestOfItsId(): void
    {
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->expects(self::once())->method('find')->with(hash('sha256', self::SESSION))->willReturn(null);
        $sessions->expects(self::once())->method('open')->with(
            '01960000-0000-7000-8000-000000000001',
            self::USER,
            hash('sha256', self::SESSION),
            str_repeat('é', 255),
            '203.0.113.7',
            $this->clock->now(),
        );
        $sessions->expects(self::never())->method('touch');

        self::assertTrue($this->track($sessions, str_repeat('é', 300), '203.0.113.7'));
    }

    public function testASessionWithoutUserAgentIsOpenedToo(): void
    {
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->method('find')->willReturn(null);
        $sessions->expects(self::once())->method('open')->with(self::anything(), self::USER, self::anything(), null, null, $this->clock->now());

        self::assertTrue($this->track($sessions, null, null));
    }

    #[DataProvider('provideARevokedOrForeignSessionEndsCases')]
    public function testARevokedOrForeignSessionEnds(string $userId, bool $revoked): void
    {
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->method('find')->willReturn(new UserSessionRecord('01960000-0000-7000-8000-000000003fa1', $userId, $this->clock->now()->modify('-1 hour'), $revoked));
        $sessions->expects(self::never())->method('open');
        $sessions->expects(self::never())->method('touch');

        self::assertFalse($this->track($sessions, 'Firefox', '203.0.113.7'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideARevokedOrForeignSessionEndsCases(): iterable
    {
        yield 'revoked from another browser' => [self::USER, true];

        yield 'carried by another account' => [self::OTHER, false];
    }

    public function testASessionSeenLessThanAMinuteAgoIsNotTouched(): void
    {
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->method('find')->willReturn(new UserSessionRecord('01960000-0000-7000-8000-000000003fa1', self::USER, $this->clock->now()->modify('-59 seconds'), false));
        $sessions->expects(self::never())->method('touch');

        self::assertTrue($this->track($sessions, 'Firefox', '203.0.113.7'));
    }

    public function testASessionSeenAMinuteAgoIsSeenAgain(): void
    {
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->method('find')->willReturn(new UserSessionRecord('01960000-0000-7000-8000-000000003fa1', self::USER, $this->clock->now()->modify('-60 seconds'), false));
        $sessions->expects(self::once())->method('touch')->with(hash('sha256', self::SESSION), '203.0.113.7', $this->clock->now());
        $sessions->expects(self::never())->method('open');

        self::assertTrue($this->track($sessions, 'Firefox', '203.0.113.7'));
    }

    private function track(UserSessionStoreInterface $sessions, ?string $userAgent, ?string $ipAddress): bool
    {
        return new TrackUserSessionHandler($sessions, new AccountFakes(), $this->clock)(new TrackUserSession(self::USER, self::SESSION, $userAgent, $ipAddress));
    }
}
