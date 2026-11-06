<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Audit\Application\Query;

use App\System\Audit\Application\Port\AuditLogCriteria;
use App\System\Audit\Application\Port\AuditLogReadRepositoryInterface;
use App\System\Audit\Application\Query\AuditDetailView;
use App\System\Audit\Application\Query\AuditEntryView;
use App\System\Audit\Application\Query\ExportAuditLog\ExportAuditLog;
use App\System\Audit\Application\Query\ExportAuditLog\ExportAuditLogHandler;
use App\System\Audit\Application\Query\ListClinicActivity\ListClinicActivity;
use App\System\Audit\Application\Query\ListClinicActivity\ListClinicActivityHandler;
use App\System\Audit\Application\Query\ListUserHistory\ListUserHistory;
use App\System\Audit\Application\Query\ListUserHistory\ListUserHistoryHandler;
use App\System\Audit\Application\Query\SearchAuditLog\AuditLogPage;
use App\System\Audit\Application\Query\SearchAuditLog\SearchAuditLog;
use App\System\Audit\Application\Query\SearchAuditLog\SearchAuditLogHandler;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;

final class AuditLogQueriesTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-000000000c01';
    private const string USER   = '01960000-0000-7000-8000-000000000b01';

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-11-06T10:00:00Z'));
    }

    public function testASearchAnswersThePageOfTheFilters(): void
    {
        $entry = $this->entry();
        $log   = $this->createMock(AuditLogReadRepositoryInterface::class);
        $log->method('count')->willReturn(30);
        $log->expects(self::once())->method('search')->with(
            self::callback(static fn (AuditLogCriteria $c): bool => AuditCategory::CLINICS === $c->category
                && self::CLINIC === $c->clinicId
                && '2026-10-07' === $c->since?->format('Y-m-d')
                && 'prado' === $c->text),
            12,
            12,
        )->willReturn([$entry]);

        $page = new SearchAuditLogHandler($log, $this->clock)(new SearchAuditLog(
            page: 2,
            category: AuditCategory::CLINICS,
            clinicId: self::CLINIC,
            periodDays: 30,
            text: 'prado',
        ));

        self::assertSame([$entry], $page->items);
        self::assertSame(30, $page->total);
        self::assertSame(2, $page->page);
        self::assertSame(12, $page->perPage);
        self::assertSame(3, $page->pageCount());
    }

    public function testAPageBeyondTheLastAnswersTheLast(): void
    {
        $log = $this->createMock(AuditLogReadRepositoryInterface::class);
        $log->method('count')->willReturn(13);
        $log->expects(self::once())->method('search')->with(self::anything(), 12, 12)->willReturn([]);

        self::assertSame(2, new SearchAuditLogHandler($log, $this->clock)(new SearchAuditLog(page: 9))->page);
    }

    public function testAnEmptyLogReadsNoRow(): void
    {
        $log = $this->createMock(AuditLogReadRepositoryInterface::class);
        $log->method('count')->willReturn(0);
        $log->expects(self::never())->method('search');

        $page = new SearchAuditLogHandler($log, $this->clock)(new SearchAuditLog(page: 0, perPage: 0));

        self::assertSame([], $page->items);
        self::assertSame(1, $page->page);
        self::assertSame(1, $page->perPage);
        self::assertSame(1, $page->pageCount());
        self::assertSame(1, new AuditLogPage([], 0, 1, 12)->pageCount());
    }

    public function testTheExportReadsAtMostTenThousandRowsOfTheFilters(): void
    {
        $entry = $this->entry();
        $log   = $this->createMock(AuditLogReadRepositoryInterface::class);
        $log->expects(self::once())->method('search')->with(
            self::callback(static fn (AuditLogCriteria $c): bool => AuditCategory::SUPPORT === $c->category && null === $c->since),
            0,
            ExportAuditLogHandler::MAX_ROWS,
        )->willReturn([$entry]);

        self::assertSame([$entry], new ExportAuditLogHandler($log, $this->clock)(new ExportAuditLog(category: AuditCategory::SUPPORT)));
    }

    public function testTheClinicActivityAndTheUserHistoryReadTheirEntries(): void
    {
        $entry = $this->entry();
        $log   = $this->createMock(AuditLogReadRepositoryInterface::class);
        $log->expects(self::once())->method('forClinic')->with(self::CLINIC, 5)->willReturn([$entry]);
        $log->expects(self::once())->method('forUser')->with(self::USER, 1)->willReturn([$entry]);

        self::assertSame([$entry], new ListClinicActivityHandler($log)(new ListClinicActivity(self::CLINIC)));
        self::assertSame([$entry], new ListUserHistoryHandler($log)(new ListUserHistory(self::USER, 0)));
    }

    private function entry(): AuditEntryView
    {
        return new AuditEntryView(
            id: '01960000-0000-7000-8000-000000000e01',
            occurredAt: new \DateTimeImmutable('2026-11-01T02:32:00Z'),
            actorKind: 'kiveto',
            actorId: '01960000-0000-7000-8000-000000000a01',
            actorName: 'Djamil Soidiki',
            action: 'support.session_ended',
            category: 'support',
            summary: 'Session support terminée',
            clinicId: self::CLINIC,
            clinicName: 'Clinique du Prado',
            targetUserId: self::USER,
            ipAddress: '91.12.34.12',
            details: [new AuditDetailView('Durée', '18 min')],
        );
    }
}
