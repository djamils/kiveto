<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\DataExport;

use App\Context\ClientPortal\Application\Query\GetDataExportFile\GetDataExportFile;
use App\Context\ClientPortal\Application\Query\GetDataExportFile\GetDataExportFileHandler;
use App\Context\ClientPortal\Application\Query\ListDataExports\DataExportSummary;
use App\Context\ClientPortal\Application\Query\ListDataExports\ListDataExports;
use App\Context\ClientPortal\Application\Query\ListDataExports\ListDataExportsHandler;
use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportId;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryDataExports;
use App\Tests\Unit\Context\ClientPortal\Support\SettableClock;
use PHPUnit\Framework\TestCase;

final class DataExportQueriesTest extends TestCase
{
    private const string USER    = '01960000-0000-7000-8000-0000000e1001';
    private const string OTHER   = '01960000-0000-7000-8000-0000000e1002';
    private const string READY   = '01960000-0000-7000-8000-0000000e0001';
    private const string OLD     = '01960000-0000-7000-8000-0000000e0002';
    private const string FAILED  = '01960000-0000-7000-8000-0000000e0003';
    private const string PENDING = '01960000-0000-7000-8000-0000000e0004';
    private const string FOREIGN = '01960000-0000-7000-8000-0000000e0005';

    private InMemoryDataExports $exports;
    private SettableClock $clock;

    protected function setUp(): void
    {
        $this->exports = new InMemoryDataExports();
        $this->clock   = new SettableClock(new \DateTimeImmutable('2026-11-02 10:00:00', new \DateTimeZone('UTC')));

        $this->export(self::OLD, self::USER, '2026-10-20 09:00', ready: true);
        $this->export(self::FAILED, self::USER, '2026-10-25 09:00', failed: true);
        $this->export(self::READY, self::USER, '2026-11-01 09:00', ready: true);
        $this->export(self::PENDING, self::USER, '2026-11-02 09:59');
        $this->export(self::FOREIGN, self::OTHER, '2026-11-01 09:00', ready: true);
    }

    public function testTheAccountsExportsAreListedLatestFirstWithTheirStatusAsOfNow(): void
    {
        $list = new ListDataExportsHandler($this->exports, $this->clock)(new ListDataExports(self::USER));

        self::assertSame(
            [
                [self::PENDING, 'pending', '2026-11-02 09:59', null, null],
                [self::READY, 'ready', '2026-11-01 09:00', '2026-11-01 09:00', '2026-11-03 09:00'],
                [self::FAILED, 'failed', '2026-10-25 09:00', null, null],
                [self::OLD, 'expired', '2026-10-20 09:00', '2026-10-20 09:00', '2026-10-22 09:00'],
            ],
            array_map(static fn (DataExportSummary $e): array => [$e->id, $e->status, $e->requestedAt->format('Y-m-d H:i'), $e->readyAt?->format('Y-m-d H:i'), $e->expiresAt?->format('Y-m-d H:i')], $list),
        );
    }

    public function testOnlyTheAccountsReadyExportWithinItsHoursHasAFile(): void
    {
        $handler = new GetDataExportFileHandler($this->exports, $this->clock);

        $file = $handler(new GetDataExportFile(self::USER, self::READY));
        self::assertSame([self::READY, 'client_portal/exports/' . self::READY . '.zip'], [$file?->exportId, $file?->storageKey]);
        self::assertSame($file?->storageKey, GetDataExportFileHandler::storageKey(self::READY));

        foreach ([self::OLD, self::FAILED, self::PENDING, self::FOREIGN, 'unknown'] as $id) {
            self::assertNull($handler(new GetDataExportFile(self::USER, $id)), $id);
        }

        $this->clock->now = new \DateTimeImmutable('2026-11-03 09:00:00', new \DateTimeZone('UTC'));
        self::assertNull($handler(new GetDataExportFile(self::USER, self::READY)), 'after 48 hours');
    }

    private function export(string $id, string $userId, string $requestedAt, bool $ready = false, bool $failed = false): void
    {
        $at     = new \DateTimeImmutable($requestedAt, new \DateTimeZone('UTC'));
        $export = DataExport::request(DataExportId::fromString($id), $userId, $at);

        if ($ready) {
            $export->markReady($at);
        }
        if ($failed) {
            $export->markFailed($at);
        }

        $this->exports->add($export);
    }
}
