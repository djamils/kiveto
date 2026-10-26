<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Command\NotifyExpiringLots;

use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLots;
use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLotsHandler;
use App\Context\Inventory\Application\Notification\StockNoticeRecipients;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Inventory\Support\CapturingStaffBell;
use App\Tests\Unit\Context\Inventory\Support\InMemoryExpiryNotices;
use App\Tests\Unit\Context\Inventory\Support\StockNotices;
use PHPUnit\Framework\TestCase;

/**
 * AC 39: two lots expiring within 30 days give one « Péremption proche ·
 * 2 lots » entry; the next days post nothing for them.
 */
final class NotifyExpiringLotsHandlerTest extends TestCase
{
    private InMemoryExpiryNotices $notices;
    private CapturingStaffBell $bell;

    protected function setUp(): void
    {
        $this->notices = new InMemoryExpiryNotices();
        $this->bell    = new CapturingStaffBell();
    }

    public function testTheLotsExpiringWithin30DaysAreAnnouncedOnce(): void
    {
        $this->notices->add('lot-1', StockNotices::CLINIC, '2026-11-02');
        $this->notices->add('lot-2', StockNotices::CLINIC, '2026-11-26');
        $this->notices->add('lot-3', StockNotices::CLINIC, '2026-11-27');

        // 07:13 in Paris is 06:13 UTC on Tuesday 27/10.
        self::assertSame(2, $this->scan('2026-10-27 06:13'));
        self::assertSame([['2026-10-27 00:00 Europe/Paris', '2026-11-26']], $this->notices->windows);
        self::assertSame([[
            'kind'      => 'EXPIRING_LOTS',
            'clinicId'  => StockNotices::CLINIC,
            'userIds'   => [StockNotices::ASSISTANT],
            'articleId' => null,
            'title'     => 'Péremption proche · 2 lots',
            'detail'    => 'Expirent dans moins de 30 jours · voir Stocks',
            'key'       => 'expiring:' . StockNotices::CLINIC . ':2026-10-27',
        ]], $this->bell->posted);
        self::assertSame(['lot-1', 'lot-2'], array_keys($this->notices->announced));

        // The next day only the third lot enters the window.
        self::assertSame(1, $this->scan('2026-10-28 06:13'));
        self::assertSame(['Péremption proche · 1 lot', 'Expire dans moins de 30 jours · voir Stocks', 'expiring:' . StockNotices::CLINIC . ':2026-10-28'], [$this->bell->posted[1]['title'], $this->bell->posted[1]['detail'], $this->bell->posted[1]['key']]);

        self::assertSame(0, $this->scan('2026-10-29 06:13'));
        self::assertCount(2, $this->bell->posted);
    }

    public function testEachClinicHasItsEntryAndAClinicWithNobodyToTellKeepsItsLots(): void
    {
        $this->notices->add('lot-1', StockNotices::CLINIC, '2026-11-02');
        $this->notices->add('lot-2', StockNotices::OTHER, '2026-11-02');

        self::assertSame(1, $this->scan('2026-10-27 06:13'));
        self::assertSame([StockNotices::CLINIC], array_column($this->bell->posted, 'clinicId'));
        self::assertSame(['lot-1'], array_keys($this->notices->announced));
    }

    private function scan(string $utc): int
    {
        $handler = new NotifyExpiringLotsHandler(
            $this->notices,
            new StockNoticeRecipients(StockNotices::staff()),
            $this->bell,
            new FrozenClock(new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))),
        );

        return $handler(new NotifyExpiringLots());
    }
}
