<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\GetSupportSession;

use App\System\IdentityAccess\Application\Query\GetSupportSession\GetSupportSession;
use App\System\IdentityAccess\Application\Query\GetSupportSession\GetSupportSessionHandler;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\System\IdentityAccess\Domain\Support\SupportSession;
use App\Tests\Unit\System\IdentityAccess\Support\InMemorySupportSessions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GetSupportSessionHandlerTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-00000000ff01';
    private const string CLINIC = '01960000-0000-7000-8000-00000000ff02';
    private const string USER   = '01960000-0000-7000-8000-00000000ff03';
    private const string ADMIN  = '01960000-0000-7000-8000-00000000ff04';

    public function testTheSessionIsShownAsTheConsoleAndTheBannerSeeIt(): void
    {
        $sessions  = new InMemorySupportSessions();
        $startedAt = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $sessions->add(SupportSession::reconstitute(
            self::ID,
            self::CLINIC,
            self::USER,
            self::ADMIN,
            'Djamil Soidiki',
            'Ticket 42',
            SupportMode::WRITE,
            $startedAt,
            $startedAt->modify('+60 minutes'),
            'digest',
            $startedAt->modify('+1 minute'),
            $startedAt->modify('+21 minutes'),
            7,
            2,
        ));

        $view = new GetSupportSessionHandler($sessions)(new GetSupportSession(self::ID));

        self::assertNotNull($view);
        self::assertSame(
            [self::ID, self::CLINIC, self::USER, self::ADMIN, 'Djamil Soidiki', 'Ticket 42', 'write', 'Lecture et écriture', $startedAt, '10:00', '09:21', 20, 7, 2, false],
            [
                $view->id, $view->clinicId, $view->userId, $view->adminId, $view->adminName, $view->reason, $view->mode, $view->modeLabel,
                $view->startedAt, $view->expiresAt->format('H:i'), $view->endedAt?->format('H:i'), $view->minutes, $view->pages, $view->writes, $view->isReadOnly(),
            ],
        );
    }

    public function testAnUnknownSessionIsNull(): void
    {
        self::assertNull(new GetSupportSessionHandler(new InMemorySupportSessions())(new GetSupportSession(self::ID)));
    }

    #[DataProvider('provideOnlyAReadSessionIsReadOnlyCases')]
    public function testOnlyAReadSessionIsReadOnly(SupportMode $mode, bool $readOnly): void
    {
        $session = SupportSession::start(self::ID, self::CLINIC, self::USER, self::ADMIN, 'Djamil', 'Ticket 42', $mode, 15, 'digest', new \DateTimeImmutable());

        self::assertSame($readOnly, SupportSessionView::of($session)->isReadOnly());
    }

    /**
     * @return iterable<string, array{SupportMode, bool}>
     */
    public static function provideOnlyAReadSessionIsReadOnlyCases(): iterable
    {
        yield 'read' => [SupportMode::READ, true];

        yield 'write' => [SupportMode::WRITE, false];
    }
}
