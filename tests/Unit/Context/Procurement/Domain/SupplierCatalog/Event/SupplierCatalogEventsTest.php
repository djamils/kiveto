<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\SupplierCatalog\Event;

use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogEntryAdded;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogEntryDiscontinued;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogEntryUpdated;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogPriceChanged;
use PHPUnit\Framework\TestCase;

final class SupplierCatalogEventsTest extends TestCase
{
    private const string ENTRY_ID    = 'entry-id';
    private const string SUPPLIER_ID = 'supplier-id';

    public function testSupplierCatalogEntryAdded(): void
    {
        $event = new SupplierCatalogEntryAdded(self::ENTRY_ID, self::SUPPLIER_ID, 'PROD-42', 'Antibiotic 500mg');

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'entryId'     => self::ENTRY_ID,
            'supplierId'  => self::SUPPLIER_ID,
            'productCode' => 'PROD-42',
            'name'        => 'Antibiotic 500mg',
        ], $event->payload());
    }

    public function testSupplierCatalogEntryDiscontinued(): void
    {
        $event = new SupplierCatalogEntryDiscontinued(self::ENTRY_ID, self::SUPPLIER_ID);

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'entryId'    => self::ENTRY_ID,
            'supplierId' => self::SUPPLIER_ID,
        ], $event->payload());
    }

    public function testSupplierCatalogEntryUpdated(): void
    {
        $event = new SupplierCatalogEntryUpdated(self::ENTRY_ID, self::SUPPLIER_ID);

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'entryId'    => self::ENTRY_ID,
            'supplierId' => self::SUPPLIER_ID,
        ], $event->payload());
    }

    public function testSupplierCatalogPriceChanged(): void
    {
        $event = new SupplierCatalogPriceChanged(self::ENTRY_ID, self::SUPPLIER_ID, 1299, 'EUR');

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'entryId'       => self::ENTRY_ID,
            'supplierId'    => self::SUPPLIER_ID,
            'newPriceMinor' => 1299,
            'currency'      => 'EUR',
        ], $event->payload());
    }
}
