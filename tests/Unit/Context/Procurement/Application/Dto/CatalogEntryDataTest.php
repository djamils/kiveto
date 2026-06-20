<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Dto;

use App\Context\Procurement\Application\Dto\CatalogEntryData;
use PHPUnit\Framework\TestCase;

final class CatalogEntryDataTest extends TestCase
{
    public function testConstructorExposesAllFields(): void
    {
        $data = new CatalogEntryData(
            supplierProductCode: 'PROD-001',
            name: 'Antibiotic 500mg',
            gtin: '1234567890123',
            priceMinor: 1299,
            currency: 'EUR',
            unit: 'TABLET',
            packagingAmount: '100',
        );

        self::assertSame('PROD-001', $data->supplierProductCode);
        self::assertSame('Antibiotic 500mg', $data->name);
        self::assertSame('1234567890123', $data->gtin);
        self::assertSame(1299, $data->priceMinor);
        self::assertSame('EUR', $data->currency);
        self::assertSame('TABLET', $data->unit);
        self::assertSame('100', $data->packagingAmount);
    }

    public function testConstructorAcceptsNullableFields(): void
    {
        $data = new CatalogEntryData(
            supplierProductCode: 'PROD-002',
            name: 'No GTIN product',
            gtin: null,
            priceMinor: 500,
            currency: 'EUR',
            unit: null,
            packagingAmount: null,
        );

        self::assertNull($data->gtin);
        self::assertNull($data->unit);
        self::assertNull($data->packagingAmount);
    }
}
