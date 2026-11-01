<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain\Export;

use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportId;
use App\Context\ClientPortal\Domain\Export\DataExportStatus;
use PHPUnit\Framework\TestCase;

final class DataExportTest extends TestCase
{
    private const string ID   = '01960000-0000-7000-8000-0000000e0001';
    private const string USER = '01960000-0000-7000-8000-0000000e1001';

    public function testAReadyExportIsDownloadableFor48HoursThenExpired(): void
    {
        $export = DataExport::request(DataExportId::fromString(self::ID), self::USER, self::at('2026-11-02 10:00'));

        self::assertSame([self::ID, self::USER, DataExportStatus::PENDING, '2026-11-02 10:00', null, null, null, 'client_portal/exports/' . self::ID . '.zip'], [
            $export->id()->toString(), $export->userId(), $export->status(), $export->requestedAt()->format('Y-m-d H:i'), $export->readyAt(), $export->expiresAt(), $export->failedAt(), $export->storageKey(),
        ]);
        self::assertFalse($export->isDownloadableAt(self::at('2026-11-02 10:00')));
        self::assertFalse($export->expire(), 'a pending export has no archive to expire');

        self::assertTrue($export->markReady(self::at('2026-11-02 10:05')));
        self::assertSame(['2026-11-02 10:05', '2026-11-04 10:05'], [$export->readyAt()?->format('Y-m-d H:i'), $export->expiresAt()?->format('Y-m-d H:i')]);
        self::assertFalse($export->markReady(self::at('2026-11-02 11:00')), 'a replayed outcome changes nothing');
        self::assertFalse($export->markFailed(self::at('2026-11-02 11:00')));
        self::assertSame('2026-11-02 10:05', $export->readyAt()?->format('Y-m-d H:i'));

        self::assertTrue($export->isDownloadableAt(self::at('2026-11-04 10:04')));
        self::assertSame(DataExportStatus::EXPIRED, $export->statusAt(self::at('2026-11-04 10:05')));
        self::assertFalse($export->isDownloadableAt(self::at('2026-11-04 10:05')));
        self::assertSame(DataExportStatus::READY, $export->status(), 'expired as seen, not yet deleted');

        self::assertTrue($export->expire());
        self::assertSame(DataExportStatus::EXPIRED, $export->status());
        self::assertFalse($export->expire());
    }

    public function testAFailedBuildIsTerminal(): void
    {
        $export = DataExport::reconstitute(DataExportId::fromString(self::ID), self::USER, DataExportStatus::PENDING, self::at('2026-11-02 10:00'), null, null, null);

        self::assertTrue($export->markFailed(self::at('2026-11-02 10:01')));
        self::assertSame([DataExportStatus::FAILED, '2026-11-02 10:01'], [$export->statusAt(self::at('2026-11-10 00:00')), $export->failedAt()?->format('Y-m-d H:i')]);
        self::assertFalse($export->markReady(self::at('2026-11-02 10:02')));
        self::assertSame('client_portal/exports/abc.zip', DataExport::keyOf('abc'));
    }

    private static function at(string $utc): \DateTimeImmutable
    {
        return new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
    }
}
