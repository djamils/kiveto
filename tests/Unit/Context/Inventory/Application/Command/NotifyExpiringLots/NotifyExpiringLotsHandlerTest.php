<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Command\NotifyExpiringLots;

use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLots;
use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLotsHandler;
use App\Context\Inventory\Application\Notification\StockNoticeRecipients;
use App\Context\Inventory\Application\Port\ClinicTimeZoneInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Inventory\Support\CapturingStaffBell;
use App\Tests\Unit\Context\Inventory\Support\CapturingStockNoticeJobs;
use App\Tests\Unit\Context\Inventory\Support\InMemoryExpiryNotices;
use App\Tests\Unit\Context\Inventory\Support\StockNotices;
use PHPUnit\Framework\TestCase;

/**
 * AC 39: two lots expiring within 30 days give one « Péremption proche ·
 * 2 lots » entry; the next days post nothing for them. The daily run queues
 * one scan per clinic, each counting days in its own time zone.
 */
final class NotifyExpiringLotsHandlerTest extends TestCase
{
    private InMemoryExpiryNotices $notices;
    private CapturingStaffBell $bell;
    private CapturingStockNoticeJobs $jobs;

    /** @var array<string, string> clinic id => time zone */
    private array $timeZones = [];

    protected function setUp(): void
    {
        $this->notices = new InMemoryExpiryNotices();
        $this->bell    = new CapturingStaffBell();
        $this->jobs    = new CapturingStockNoticeJobs();
    }

    public function testTheDailyRunQueuesOneScanPerClinicWithLotsAroundTheWindow(): void
    {
        $this->notices->add('lot-1', StockNotices::CLINIC, '2026-11-02');
        $this->notices->add('lot-2', StockNotices::CLINIC, '2026-11-05');
        $this->notices->add('lot-3', StockNotices::OTHER, '2026-10-26');
        $this->notices->add('lot-4', '01960000-0000-7000-8000-0000000c0003', '2026-11-29');

        // 07:13 in Paris is 06:13 UTC on Tuesday 27/10: a day's margin each side of the UTC window.
        $this->scan('2026-10-27 06:13');

        self::assertSame([['2026-10-26 00:00 UTC', '2026-11-27']], $this->notices->windows);
        self::assertSame([StockNotices::CLINIC, StockNotices::OTHER], $this->jobs->expiringLots);
        self::assertSame([], $this->bell->posted);
    }

    public function testTheLotsExpiringWithin30DaysAreAnnouncedOnce(): void
    {
        $this->notices->add('lot-1', StockNotices::CLINIC, '2026-11-02');
        $this->notices->add('lot-2', StockNotices::CLINIC, '2026-11-26');
        $this->notices->add('lot-3', StockNotices::CLINIC, '2026-11-27');

        $this->scan('2026-10-27 06:13', StockNotices::CLINIC);
        self::assertSame([['2026-10-27 00:00 Europe/Paris', '2026-11-26']], $this->notices->windows);
        self::assertSame([[
            'kind'      => 'EXPIRING_LOTS',
            'clinicId'  => StockNotices::CLINIC,
            'userIds'   => [StockNotices::ASSISTANT],
            'articleId' => null,
            'title'     => 'Péremption proche · 2 lots',
            'detail'    => 'Expirent dans moins de 30 jours · voir Stocks',
            'key'       => 'expiring:' . StockNotices::CLINIC . ':2026-10-27:' . self::digest('lot-1,lot-2'),
        ]], $this->bell->posted);
        self::assertSame(['lot-1', 'lot-2'], array_keys($this->notices->announced));

        // The next day only the third lot enters the window.
        $this->scan('2026-10-28 06:13', StockNotices::CLINIC);
        self::assertSame(['Péremption proche · 1 lot', 'Expire dans moins de 30 jours · voir Stocks'], [$this->bell->posted[1]['title'], $this->bell->posted[1]['detail']]);

        $this->scan('2026-10-29 06:13', StockNotices::CLINIC);
        self::assertCount(2, $this->bell->posted);
    }

    public function testASecondScanTheSameDayAnnouncesTheLotsReceivedSince(): void
    {
        $this->notices->add('lot-1', StockNotices::CLINIC, '2026-11-02');
        $this->scan('2026-10-27 06:13', StockNotices::CLINIC);

        // A lot received at noon, then a scan again (a retried or second run): its own entry.
        $this->notices->add('lot-2', StockNotices::CLINIC, '2026-11-10');
        $this->scan('2026-10-27 12:00', StockNotices::CLINIC);

        self::assertCount(2, $this->bell->posted);
        self::assertNotSame($this->bell->posted[0]['key'], $this->bell->posted[1]['key']);
        self::assertSame('expiring:' . StockNotices::CLINIC . ':2026-10-27:' . self::digest('lot-2'), $this->bell->posted[1]['key']);
    }

    public function testDaysAreCountedInTheClinicsTimeZone(): void
    {
        // 02:30 UTC on Tuesday 27/10 is still Monday 26/10 in Cayenne: the lot expiring on 26/10 is in.
        $this->timeZones[StockNotices::CLINIC] = 'America/Cayenne';
        $this->notices->add('lot-1', StockNotices::CLINIC, '2026-10-26');

        $this->scan('2026-10-27 02:30', StockNotices::CLINIC);

        self::assertSame([['2026-10-26 00:00 America/Cayenne', '2026-11-25']], $this->notices->windows);
        self::assertSame('expiring:' . StockNotices::CLINIC . ':2026-10-26:' . self::digest('lot-1'), $this->bell->posted[0]['key']);
    }

    public function testAClinicWithNobodyToTellOrNothingToAnnounceKeepsItsLots(): void
    {
        $this->notices->add('lot-2', StockNotices::OTHER, '2026-11-02');

        $this->scan('2026-10-27 06:13', StockNotices::OTHER);
        $this->scan('2026-10-27 06:13', StockNotices::CLINIC);

        self::assertSame([], $this->bell->posted);
        self::assertSame([], $this->notices->announced);
    }

    private function scan(string $utc, ?string $clinicId = null): void
    {
        $timeZones = new class($this->timeZones) implements ClinicTimeZoneInterface {
            /**
             * @param array<string, string> $zones
             */
            public function __construct(private array $zones)
            {
            }

            public function of(string $clinicId): \DateTimeZone
            {
                return new \DateTimeZone($this->zones[$clinicId] ?? 'Europe/Paris');
            }
        };

        $handler = new NotifyExpiringLotsHandler(
            $this->notices,
            new StockNoticeRecipients(StockNotices::staff()),
            $this->bell,
            $this->jobs,
            $timeZones,
            new FrozenClock(new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))),
        );

        $handler(new NotifyExpiringLots($clinicId));
    }

    private static function digest(string $lotIds): string
    {
        return mb_substr(hash('sha256', $lotIds), 0, 16);
    }
}
