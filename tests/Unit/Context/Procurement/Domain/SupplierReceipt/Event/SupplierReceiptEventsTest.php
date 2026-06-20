<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\SupplierReceipt\Event;

use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptCompleted;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptCompletedIntegrationEvent;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptCreated;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptLineAdded;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptLineRemoved;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptValidated;
use PHPUnit\Framework\TestCase;

final class SupplierReceiptEventsTest extends TestCase
{
    private const string RECEIPT_ID  = 'receipt-id';
    private const string CLINIC_ID   = 'clinic-id';
    private const string SUPPLIER_ID = 'supplier-id';
    private const string PO_ID       = 'po-id';

    public function testSupplierReceiptCreated(): void
    {
        $event = new SupplierReceiptCreated(
            self::RECEIPT_ID,
            self::CLINIC_ID,
            self::SUPPLIER_ID,
            self::PO_ID,
            'DN-2026-001',
            'MATCHED',
        );

        self::assertSame(self::RECEIPT_ID, $event->aggregateId());
        self::assertSame([
            'receiptId'             => self::RECEIPT_ID,
            'clinicId'              => self::CLINIC_ID,
            'supplierId'            => self::SUPPLIER_ID,
            'purchaseOrderId'       => self::PO_ID,
            'deliveryNoteReference' => 'DN-2026-001',
            'matchType'             => 'MATCHED',
        ], $event->payload());
    }

    public function testSupplierReceiptCompleted(): void
    {
        $event = new SupplierReceiptCompleted(self::RECEIPT_ID, self::CLINIC_ID, self::SUPPLIER_ID, self::PO_ID);

        self::assertSame(self::RECEIPT_ID, $event->aggregateId());
        self::assertSame([
            'receiptId'       => self::RECEIPT_ID,
            'clinicId'        => self::CLINIC_ID,
            'supplierId'      => self::SUPPLIER_ID,
            'purchaseOrderId' => self::PO_ID,
        ], $event->payload());
    }

    public function testSupplierReceiptLineAdded(): void
    {
        $event = new SupplierReceiptLineAdded(self::RECEIPT_ID, self::CLINIC_ID, 'line-id');

        self::assertSame(self::RECEIPT_ID, $event->aggregateId());
        self::assertSame([
            'receiptId' => self::RECEIPT_ID,
            'clinicId'  => self::CLINIC_ID,
            'lineId'    => 'line-id',
        ], $event->payload());
    }

    public function testSupplierReceiptLineRemoved(): void
    {
        $event = new SupplierReceiptLineRemoved(self::RECEIPT_ID, self::CLINIC_ID, 'line-id');

        self::assertSame(self::RECEIPT_ID, $event->aggregateId());
        self::assertSame([
            'receiptId' => self::RECEIPT_ID,
            'clinicId'  => self::CLINIC_ID,
            'lineId'    => 'line-id',
        ], $event->payload());
    }

    public function testSupplierReceiptValidated(): void
    {
        $event = new SupplierReceiptValidated(self::RECEIPT_ID, self::CLINIC_ID, '2026-06-20T10:00:00+00:00');

        self::assertSame(self::RECEIPT_ID, $event->aggregateId());
        self::assertSame([
            'receiptId'   => self::RECEIPT_ID,
            'clinicId'    => self::CLINIC_ID,
            'validatedAt' => '2026-06-20T10:00:00+00:00',
        ], $event->payload());
    }

    public function testSupplierReceiptCompletedIntegrationEvent(): void
    {
        $lines = [
            [
                'purchaseOrderLineId'     => 'pol-id',
                'articleId'               => 'article-id',
                'receivedAmount'          => '10.000',
                'receivedUnit'            => 'unit',
                'lotNumber'               => 'LOT-42',
                'expiryDate'              => '2027-12-31',
                'manufacturedAt'          => '2026-01-15',
                'actualUnitPriceMinor'    => 1299,
                'actualUnitPriceCurrency' => 'EUR',
            ],
        ];
        $event = new SupplierReceiptCompletedIntegrationEvent(
            self::RECEIPT_ID,
            self::CLINIC_ID,
            self::SUPPLIER_ID,
            self::PO_ID,
            $lines,
        );

        self::assertSame(self::RECEIPT_ID, $event->aggregateId());
        self::assertSame(self::RECEIPT_ID, $event->receiptId());
        self::assertSame(self::CLINIC_ID, $event->clinicId());
        self::assertSame(self::SUPPLIER_ID, $event->supplierId());
        self::assertSame(self::PO_ID, $event->purchaseOrderId());
        self::assertSame($lines, $event->lines());
        self::assertSame([
            'receiptId'       => self::RECEIPT_ID,
            'clinicId'        => self::CLINIC_ID,
            'supplierId'      => self::SUPPLIER_ID,
            'purchaseOrderId' => self::PO_ID,
            'lines'           => $lines,
        ], $event->payload());
    }
}
