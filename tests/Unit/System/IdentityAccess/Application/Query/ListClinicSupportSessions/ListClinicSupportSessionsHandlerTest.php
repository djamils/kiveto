<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\ListClinicSupportSessions;

use App\System\IdentityAccess\Application\Query\ListClinicSupportSessions\ListClinicSupportSessions;
use App\System\IdentityAccess\Application\Query\ListClinicSupportSessions\ListClinicSupportSessionsHandler;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\System\IdentityAccess\Domain\Support\SupportSession;
use App\Tests\Unit\System\IdentityAccess\Support\InMemorySupportSessions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListClinicSupportSessionsHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000fa11';
    private const string OTHER  = '01960000-0000-7000-8000-00000000fa12';

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideTheLatestSessionsOfTheClinicAreListedCases')]
    public function testTheLatestSessionsOfTheClinicAreListed(int $limit, array $expected): void
    {
        $sessions = new InMemorySupportSessions();
        foreach (['fa21' => '09:00', 'fa22' => '10:00', 'fa23' => '11:00', 'fa24' => '12:00'] as $suffix => $time) {
            self::claimed($sessions, '01960000-0000-7000-8000-00000000' . $suffix, self::CLINIC, $time);
        }
        self::claimed($sessions, '01960000-0000-7000-8000-00000000fa25', self::OTHER, '13:00');

        $views = new ListClinicSupportSessionsHandler($sessions)(new ListClinicSupportSessions(self::CLINIC, $limit));

        self::assertSame($expected, array_map(static fn (SupportSessionView $view): string => mb_substr($view->id, -4), $views));
    }

    /**
     * @return iterable<string, array{int, list<string>}>
     */
    public static function provideTheLatestSessionsOfTheClinicAreListedCases(): iterable
    {
        yield 'the latest three' => [3, ['fa24', 'fa23', 'fa22']];

        yield 'at least one' => [0, ['fa24']];

        yield 'all of them' => [10, ['fa24', 'fa23', 'fa22', 'fa21']];
    }

    public function testThreeAreListedByDefault(): void
    {
        self::assertSame(3, new ListClinicSupportSessions(self::CLINIC)->limit);
    }

    private static function claimed(InMemorySupportSessions $sessions, string $id, string $clinicId, string $time): void
    {
        $at      = new \DateTimeImmutable('2026-11-06 ' . $time . ':00', new \DateTimeZone('UTC'));
        $session = SupportSession::start($id, $clinicId, '01960000-0000-7000-8000-00000000fa13', '01960000-0000-7000-8000-00000000fa14', 'Djamil', 'Ticket', SupportMode::READ, 15, 'digest-' . $id, $at);
        $session->claim($at);
        $sessions->add($session);
    }
}
