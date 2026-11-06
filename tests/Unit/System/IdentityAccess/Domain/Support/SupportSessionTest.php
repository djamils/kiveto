<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\Support;

use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\System\IdentityAccess\Domain\Support\SupportSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SupportSessionTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-00000000fa01';
    private const string CLINIC = '01960000-0000-7000-8000-00000000fa02';
    private const string USER   = '01960000-0000-7000-8000-00000000fa03';
    private const string ADMIN  = '01960000-0000-7000-8000-00000000fa04';
    private const string START  = '2026-11-06 09:00:00';

    public function testASessionStartsForAReasonAndAWhile(): void
    {
        $at      = self::at(self::START);
        $session = SupportSession::start(self::ID, self::CLINIC, self::USER, self::ADMIN, '  Djamil Soidiki ', "  Ticket 42 : l'agenda ne s'affiche pas.  ", SupportMode::READ, 30, 'digest', $at);

        self::assertSame(
            [self::ID, self::CLINIC, self::USER, self::ADMIN, 'Djamil Soidiki', "Ticket 42 : l'agenda ne s'affiche pas.", SupportMode::READ, $at, '2026-11-06 09:30:00', 'digest', null, null, 0, 0],
            [
                $session->id(), $session->clinicId(), $session->userId(), $session->adminId(), $session->adminName(), $session->reason(), $session->mode(),
                $session->startedAt(), $session->expiresAt()->format('Y-m-d H:i:s'), $session->handoffHash(), $session->claimedAt(), $session->endedAt(),
                $session->pages(), $session->writes(),
            ],
        );
    }

    #[DataProvider('provideTheConsoleOffersThreeDurationsCases')]
    public function testTheConsoleOffersThreeDurations(int $minutes, string $expiresAt): void
    {
        self::assertSame($expiresAt, self::session(minutes: $minutes)->expiresAt()->format('H:i'));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideTheConsoleOffersThreeDurationsCases(): iterable
    {
        yield 'a quarter of an hour' => [15, '09:15'];

        yield 'half an hour' => [30, '09:30'];

        yield 'an hour' => [60, '10:00'];
    }

    public function testALongNameAndReasonAreCut(): void
    {
        $session = SupportSession::start(self::ID, self::CLINIC, self::USER, self::ADMIN, str_repeat('é', 200), str_repeat('à', 600), SupportMode::WRITE, 15, 'digest', self::at(self::START));

        self::assertSame([str_repeat('é', 160), str_repeat('à', 500)], [$session->adminName(), $session->reason()]);
    }

    #[DataProvider('provideAReasonIsRequiredCases')]
    public function testAReasonIsRequired(string $reason): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le motif est obligatoire : il est journalisé.');

        SupportSession::start(self::ID, self::CLINIC, self::USER, self::ADMIN, 'Djamil', $reason, SupportMode::READ, 15, 'digest', self::at(self::START));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAReasonIsRequiredCases(): iterable
    {
        yield 'empty' => [''];

        yield 'blank' => [" \n "];
    }

    #[DataProvider('provideADurationNotOfferedIsRefusedCases')]
    public function testADurationNotOfferedIsRefused(int $minutes): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Durée de session non proposée.');

        self::session(minutes: $minutes);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideADurationNotOfferedIsRefusedCases(): iterable
    {
        yield 'none' => [0];

        yield 'three quarters' => [45];

        yield 'two hours' => [120];
    }

    public function testTheHandoffIsTakenOnceWithinTwoMinutes(): void
    {
        $session = self::session();

        self::assertTrue($session->claim(self::at('2026-11-06 09:02:00')), 'two minutes exactly');
        self::assertSame('2026-11-06 09:02:00', $session->claimedAt()?->format('Y-m-d H:i:s'));
        self::assertFalse($session->claim(self::at('2026-11-06 09:02:00')), 'once');
        self::assertSame(120, SupportSession::HANDOFF_SECONDS);
    }

    public function testALateOrEndedHandoffIsRefused(): void
    {
        $late = self::session();
        self::assertFalse($late->claim(self::at('2026-11-06 09:02:01')));
        self::assertNull($late->claimedAt());

        $ended = self::session();
        $ended->end(self::at('2026-11-06 09:00:30'));
        self::assertFalse($ended->claim(self::at('2026-11-06 09:01:00')));
        self::assertNull($ended->claimedAt());
    }

    public function testASessionIsOpenFromItsClaimUntilItsEndOrExpiry(): void
    {
        $session = self::session(minutes: 15);
        self::assertFalse($session->isOpenAt(self::at('2026-11-06 09:01:00')), 'not claimed yet');

        $session->claim(self::at('2026-11-06 09:01:00'));
        self::assertTrue($session->isOpenAt(self::at('2026-11-06 09:14:59')));
        self::assertFalse($session->isOpenAt(self::at('2026-11-06 09:15:00')), 'ran out');

        $session->end(self::at('2026-11-06 09:05:00'));
        self::assertFalse($session->isOpenAt(self::at('2026-11-06 09:06:00')), 'ended');
    }

    public function testPagesAndChangesAreCounted(): void
    {
        $session = self::session();

        $session->recordPage(false);
        $session->recordPage(true);
        $session->recordPage(false);

        self::assertSame([3, 1], [$session->pages(), $session->writes()]);
    }

    public function testASessionEndsOnceAtTheTimeAsked(): void
    {
        $session = self::session();
        $session->claim(self::at('2026-11-06 09:01:00'));

        self::assertTrue($session->end(self::at('2026-11-06 09:11:00')));
        self::assertSame('2026-11-06 09:11:00', self::format($session->endedAt()));
        self::assertSame(10, $session->minutes(), 'from its claim');

        self::assertFalse($session->end(self::at('2026-11-06 09:20:00')));
        self::assertSame('2026-11-06 09:11:00', self::format($session->endedAt()), 'the first end is kept');
    }

    public function testASessionEndedAfterItsTimeEndsAtItsExpiry(): void
    {
        $session = self::session(minutes: 15);
        $session->claim(self::at('2026-11-06 09:00:00'));

        self::assertTrue($session->end(self::at('2026-11-06 11:00:00')));
        self::assertSame('2026-11-06 09:15:00', $session->endedAt()?->format('Y-m-d H:i:s'));
        self::assertSame(15, $session->minutes());
    }

    #[DataProvider('provideASessionLastsAtLeastAMinuteCases')]
    public function testASessionLastsAtLeastAMinute(?string $claimedAt, ?string $endedAt, int $minutes): void
    {
        $session = self::session(minutes: 30);

        if (null !== $claimedAt) {
            $session->claim(self::at($claimedAt));
        }

        if (null !== $endedAt) {
            $session->end(self::at($endedAt));
        }

        self::assertSame($minutes, $session->minutes());
    }

    /**
     * @return iterable<string, array{?string, ?string, int}>
     */
    public static function provideASessionLastsAtLeastAMinuteCases(): iterable
    {
        yield 'never claimed nor ended: its full time' => [null, null, 30];

        yield 'never claimed, ended: from its start' => [null, '2026-11-06 09:05:00', 5];

        yield 'claimed, still running: until its expiry' => ['2026-11-06 09:01:00', null, 29];

        yield 'ended at once' => ['2026-11-06 09:00:10', '2026-11-06 09:00:20', 1];

        yield 'rounded down' => ['2026-11-06 09:00:00', '2026-11-06 09:01:29', 1];

        yield 'rounded up' => ['2026-11-06 09:00:00', '2026-11-06 09:01:30', 2];
    }

    public function testAReconstitutedSessionKeepsItsState(): void
    {
        $session = SupportSession::reconstitute(
            self::ID,
            self::CLINIC,
            self::USER,
            self::ADMIN,
            'Djamil',
            'Ticket 42',
            SupportMode::WRITE,
            self::at(self::START),
            self::at('2026-11-06 10:00:00'),
            'digest',
            self::at('2026-11-06 09:01:00'),
            null,
            12,
            3,
        );

        self::assertSame([SupportMode::WRITE, '09:01', null, 12, 3, 'Djamil', 'Ticket 42'], [
            $session->mode(), $session->claimedAt()?->format('H:i'), $session->endedAt(), $session->pages(), $session->writes(), $session->adminName(), $session->reason(),
        ]);
        self::assertTrue($session->isOpenAt(self::at('2026-11-06 09:30:00')));
    }

    private static function session(int $minutes = 30): SupportSession
    {
        return SupportSession::start(self::ID, self::CLINIC, self::USER, self::ADMIN, 'Djamil', 'Ticket 42', SupportMode::READ, $minutes, 'digest', self::at(self::START));
    }

    private static function format(?\DateTimeImmutable $at): ?string
    {
        return $at?->format('Y-m-d H:i:s');
    }

    private static function at(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
