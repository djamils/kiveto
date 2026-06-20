<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\Supplier\Event;

use App\Context\Procurement\Domain\Supplier\Event\SupplierArchived;
use App\Context\Procurement\Domain\Supplier\Event\SupplierIntegrationModeChanged;
use App\Context\Procurement\Domain\Supplier\Event\SupplierRegistered;
use App\Context\Procurement\Domain\Supplier\Event\SupplierRenamed;
use PHPUnit\Framework\TestCase;

final class SupplierEventsTest extends TestCase
{
    private const string SUPPLIER_ID = 'supplier-id';

    public function testSupplierRegistered(): void
    {
        $event = new SupplierRegistered(
            self::SUPPLIER_ID,
            'Centravet',
            'CENT',
            'WHOLESALER',
            'FR',
            'MANUAL_EXPORT',
            'centravet-csv',
        );

        self::assertSame(self::SUPPLIER_ID, $event->aggregateId());
        self::assertSame([
            'supplierId'        => self::SUPPLIER_ID,
            'name'              => 'Centravet',
            'code'              => 'CENT',
            'type'              => 'WHOLESALER',
            'countryCode'       => 'FR',
            'integrationMode'   => 'MANUAL_EXPORT',
            'adapterIdentifier' => 'centravet-csv',
        ], $event->payload());
    }

    public function testSupplierRegisteredWithNullAdapter(): void
    {
        $event = new SupplierRegistered(
            self::SUPPLIER_ID,
            'No Adapter Supplier',
            'NOAD',
            'WHOLESALER',
            'FR',
            'MANUAL',
            null,
        );

        self::assertSame(self::SUPPLIER_ID, $event->aggregateId());
        self::assertNull($event->payload()['adapterIdentifier']);
    }

    public function testSupplierArchived(): void
    {
        $event = new SupplierArchived(self::SUPPLIER_ID);

        self::assertSame(self::SUPPLIER_ID, $event->aggregateId());
        self::assertSame(['supplierId' => self::SUPPLIER_ID], $event->payload());
    }

    public function testSupplierIntegrationModeChanged(): void
    {
        $event = new SupplierIntegrationModeChanged(self::SUPPLIER_ID, 'SIMULATION', 'simulated-supplier');

        self::assertSame(self::SUPPLIER_ID, $event->aggregateId());
        self::assertSame([
            'supplierId'           => self::SUPPLIER_ID,
            'newMode'              => 'SIMULATION',
            'newAdapterIdentifier' => 'simulated-supplier',
        ], $event->payload());
    }

    public function testSupplierIntegrationModeChangedWithNullAdapter(): void
    {
        $event = new SupplierIntegrationModeChanged(self::SUPPLIER_ID, 'MANUAL', null);

        self::assertNull($event->payload()['newAdapterIdentifier']);
    }

    public function testSupplierRenamed(): void
    {
        $event = new SupplierRenamed(self::SUPPLIER_ID, 'Old Name', 'New Name');

        self::assertSame(self::SUPPLIER_ID, $event->aggregateId());
        self::assertSame([
            'supplierId' => self::SUPPLIER_ID,
            'oldName'    => 'Old Name',
            'newName'    => 'New Name',
        ], $event->payload());
    }
}
