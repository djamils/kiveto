<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\SupplierAccount\Event;

use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountCreated;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountDisabled;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountEnabled;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountUpdated;
use PHPUnit\Framework\TestCase;

final class SupplierAccountEventsTest extends TestCase
{
    private const string ACCOUNT_ID  = 'account-id';
    private const string CLINIC_ID   = 'clinic-id';
    private const string SUPPLIER_ID = 'supplier-id';

    public function testSupplierAccountCreated(): void
    {
        $event = new SupplierAccountCreated(self::ACCOUNT_ID, self::CLINIC_ID, self::SUPPLIER_ID, 'CUST-42');

        self::assertSame(self::ACCOUNT_ID, $event->aggregateId());
        self::assertSame([
            'accountId'    => self::ACCOUNT_ID,
            'clinicId'     => self::CLINIC_ID,
            'supplierId'   => self::SUPPLIER_ID,
            'customerCode' => 'CUST-42',
        ], $event->payload());
    }

    public function testSupplierAccountDisabled(): void
    {
        $event = new SupplierAccountDisabled(self::ACCOUNT_ID, self::CLINIC_ID, self::SUPPLIER_ID);

        self::assertSame(self::ACCOUNT_ID, $event->aggregateId());
        self::assertSame([
            'accountId'  => self::ACCOUNT_ID,
            'clinicId'   => self::CLINIC_ID,
            'supplierId' => self::SUPPLIER_ID,
        ], $event->payload());
    }

    public function testSupplierAccountEnabled(): void
    {
        $event = new SupplierAccountEnabled(self::ACCOUNT_ID, self::CLINIC_ID, self::SUPPLIER_ID);

        self::assertSame(self::ACCOUNT_ID, $event->aggregateId());
        self::assertSame([
            'accountId'  => self::ACCOUNT_ID,
            'clinicId'   => self::CLINIC_ID,
            'supplierId' => self::SUPPLIER_ID,
        ], $event->payload());
    }

    public function testSupplierAccountUpdated(): void
    {
        $event = new SupplierAccountUpdated(self::ACCOUNT_ID, self::CLINIC_ID, self::SUPPLIER_ID);

        self::assertSame(self::ACCOUNT_ID, $event->aggregateId());
        self::assertSame([
            'accountId'  => self::ACCOUNT_ID,
            'clinicId'   => self::CLINIC_ID,
            'supplierId' => self::SUPPLIER_ID,
        ], $event->payload());
    }
}
