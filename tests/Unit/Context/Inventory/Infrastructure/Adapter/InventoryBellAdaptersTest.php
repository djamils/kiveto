<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Infrastructure\Adapter;

use App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification\ListClinicMembersForNotification;
use App\Context\Inventory\Infrastructure\Adapter\Clinic\QueryBusClinicStaff;
use App\Context\Inventory\Infrastructure\Adapter\Notification\CommandBusStaffBell;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class InventoryBellAdaptersTest extends TestCase
{
    public function testTheStaffComesFromClinic(): void
    {
        $bus = new AnsweringQueryBus()->answer(
            ListClinicMembersForNotification::class,
            static fn (ListClinicMembersForNotification $q): mixed => $q->administratorsOnly ? ['admin-1', 3] : ('VETERINARY_ASSISTANT' === $q->role ? ['asv-1', 'asv-2'] : null),
        );
        $staff = new QueryBusClinicStaff($bus);

        self::assertSame(['asv-1', 'asv-2'], $staff->assistants('clinic-1'));
        self::assertSame(['admin-1'], $staff->administrators('clinic-1'));
        self::assertSame('clinic-1', $bus->asked[0] instanceof ListClinicMembersForNotification ? $bus->asked[0]->clinicId : null);

        self::assertSame([], new QueryBusClinicStaff(new AnsweringQueryBus())->assistants('clinic-1'));
    }

    public function testTheEntriesOpenTheStockPage(): void
    {
        $bus = new class implements CommandBusInterface {
            /** @var list<object> */
            public array $commands = [];

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->commands[] = $command;

                return 1;
            }
        };
        $bell = new CommandBusStaffBell($bus);

        $bell->announceCriticalStock('clinic-1', ['asv-1'], 'article-1', 'Stock critique · vermifuge', 'Rupture de stock', 'stock-alert:a-1');
        $bell->announceExpiringLots('clinic-1', ['asv-1', 'asv-2'], 'Péremption proche · 2 lots', 'Expirent dans moins de 30 jours · voir Stocks', 'expiring:clinic-1:2026-10-27');

        [$stock, $expiring] = $bus->commands;
        self::assertInstanceOf(PostInAppNotification::class, $stock);
        self::assertSame(
            ['clinic-1', ['asv-1'], 'CRITICAL_STOCK', 'Stock critique · vermifuge', 'Rupture de stock', 'clinic_stock', ['article' => 'article-1'], 'stock-alert:a-1'],
            [$stock->clinicId, $stock->userIds, $stock->kind, $stock->title, $stock->detail, $stock->route, $stock->routeParams, $stock->idempotencyKey],
        );
        self::assertInstanceOf(PostInAppNotification::class, $expiring);
        self::assertSame(
            [['asv-1', 'asv-2'], 'EXPIRING_LOTS', 'clinic_stock', ['onglet' => 'alertes'], 'expiring:clinic-1:2026-10-27'],
            [$expiring->userIds, $expiring->kind, $expiring->route, $expiring->routeParams, $expiring->idempotencyKey],
        );
    }
}
