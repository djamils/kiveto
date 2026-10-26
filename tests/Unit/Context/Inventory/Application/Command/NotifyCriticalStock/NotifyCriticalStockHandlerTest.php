<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Command\NotifyCriticalStock;

use App\Context\Inventory\Application\Command\NotifyCriticalStock\NotifyCriticalStock;
use App\Context\Inventory\Application\Command\NotifyCriticalStock\NotifyCriticalStockHandler;
use App\Context\Inventory\Application\Notification\StockNoticeRecipients;
use App\Context\Inventory\Application\Port\ClinicStaffInterface;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use App\Tests\Unit\Context\Inventory\Support\CapturingStaffBell;
use App\Tests\Unit\Context\Inventory\Support\StockNotices;
use PHPUnit\Framework\TestCase;

/**
 * AC 39: « Stock critique · … » to the assistants, or to the administrators
 * of a clinic without assistant.
 */
final class NotifyCriticalStockHandlerTest extends TestCase
{
    private CapturingStaffBell $bell;

    protected function setUp(): void
    {
        $this->bell = new CapturingStaffBell();
    }

    public function testTheAssistantsHearOfAStockOut(): void
    {
        $this->handle(StockNotices::alert());

        self::assertSame([[
            'kind'      => 'CRITICAL_STOCK',
            'clinicId'  => StockNotices::CLINIC,
            'userIds'   => [StockNotices::ASSISTANT],
            'articleId' => StockNotices::ARTICLE,
            'title'     => 'Stock critique · Vermifuge Milbemax',
            'detail'    => 'Rupture de stock',
            'key'       => 'stock-alert:' . StockNotices::ALERT,
        ]], $this->bell->posted);
    }

    public function testAClinicWithoutAssistantTellsItsAdministrators(): void
    {
        $this->handle(StockNotices::alert(StockAlertType::LOW_STOCK, articleName: ''), StockNotices::staff(assistants: []));

        self::assertSame([StockNotices::ADMIN], $this->bell->posted[0]['userIds']);
        self::assertSame(['Stock critique · article sans nom', 'Sous le seuil d’alerte'], [$this->bell->posted[0]['title'], $this->bell->posted[0]['detail']]);
    }

    public function testNothingIsPostedWithoutAnActiveAlertOrAnyoneToTell(): void
    {
        $this->handle(null);
        $this->handle(StockNotices::alert(resolvedAt: new \DateTimeImmutable('2026-10-26 09:05')));
        $this->handle(StockNotices::alert(), clinicId: StockNotices::OTHER);
        $this->handle(StockNotices::alert(StockAlertType::OUT_OF_STOCK, StockAlertSeverity::CRITICAL), StockNotices::staff([], []));

        self::assertSame([], $this->bell->posted);
    }

    private function handle(?StockAlertView $alert, ?ClinicStaffInterface $staff = null, string $clinicId = StockNotices::CLINIC): void
    {
        $handler = new NotifyCriticalStockHandler(StockNotices::alerts($alert), new StockNoticeRecipients($staff ?? StockNotices::staff()), $this->bell);

        $handler(new NotifyCriticalStock($clinicId, StockNotices::ALERT));
    }
}
