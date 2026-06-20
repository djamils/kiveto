<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\SupplierPricing\Event;

use App\Context\Procurement\Domain\SupplierPricing\Event\SupplierPricingNegotiated;
use App\Context\Procurement\Domain\SupplierPricing\Event\SupplierPricingRemoved;
use App\Context\Procurement\Domain\SupplierPricing\Event\SupplierPricingUpdated;
use PHPUnit\Framework\TestCase;

final class SupplierPricingEventsTest extends TestCase
{
    private const string PRICING_ID       = 'pricing-id';
    private const string CLINIC_ID        = 'clinic-id';
    private const string SUPPLIER_ID      = 'supplier-id';
    private const string CATALOG_ENTRY_ID = 'entry-id';

    public function testSupplierPricingNegotiated(): void
    {
        $event = new SupplierPricingNegotiated(
            self::PRICING_ID,
            self::CLINIC_ID,
            self::SUPPLIER_ID,
            self::CATALOG_ENTRY_ID,
            999,
            'EUR',
        );

        self::assertSame(self::PRICING_ID, $event->aggregateId());
        self::assertSame([
            'pricingId'      => self::PRICING_ID,
            'clinicId'       => self::CLINIC_ID,
            'supplierId'     => self::SUPPLIER_ID,
            'catalogEntryId' => self::CATALOG_ENTRY_ID,
            'amountMinor'    => 999,
            'currency'       => 'EUR',
        ], $event->payload());
    }

    public function testSupplierPricingRemoved(): void
    {
        $event = new SupplierPricingRemoved(self::PRICING_ID, self::CLINIC_ID, self::CATALOG_ENTRY_ID);

        self::assertSame(self::PRICING_ID, $event->aggregateId());
        self::assertSame([
            'pricingId'      => self::PRICING_ID,
            'clinicId'       => self::CLINIC_ID,
            'catalogEntryId' => self::CATALOG_ENTRY_ID,
        ], $event->payload());
    }

    public function testSupplierPricingUpdated(): void
    {
        $event = new SupplierPricingUpdated(self::PRICING_ID, self::CLINIC_ID, self::CATALOG_ENTRY_ID);

        self::assertSame(self::PRICING_ID, $event->aggregateId());
        self::assertSame([
            'pricingId'      => self::PRICING_ID,
            'clinicId'       => self::CLINIC_ID,
            'catalogEntryId' => self::CATALOG_ENTRY_ID,
        ], $event->payload());
    }
}
