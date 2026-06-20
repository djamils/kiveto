<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\PurchaseOrder\Event;

use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderCancelled;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderClosed;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderConfirmed;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderCreated;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderFullyReceived;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineAdded;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineCancelled;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineRemoved;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineUpdated;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderPartiallyReceived;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderSendFailed;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderSubmitted;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderSubmittingStarted;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderEventsTest extends TestCase
{
    private const string PO_ID     = 'po-id';
    private const string CLINIC_ID = 'clinic-id';

    public function testPurchaseOrderCreated(): void
    {
        $event = new PurchaseOrderCreated(self::PO_ID, self::CLINIC_ID, 'supplier-id', 'PO-2026-001', 'EUR');

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
            'supplierId'      => 'supplier-id',
            'orderNumber'     => 'PO-2026-001',
            'currency'        => 'EUR',
        ], $event->payload());
    }

    public function testPurchaseOrderCancelled(): void
    {
        $event = new PurchaseOrderCancelled(self::PO_ID, self::CLINIC_ID, 'duplicate order');

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
            'reason'          => 'duplicate order',
        ], $event->payload());
    }

    public function testPurchaseOrderClosed(): void
    {
        $event = new PurchaseOrderClosed(self::PO_ID, self::CLINIC_ID);

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
        ], $event->payload());
    }

    public function testPurchaseOrderConfirmed(): void
    {
        $event = new PurchaseOrderConfirmed(self::PO_ID, self::CLINIC_ID, '2026-06-20T10:00:00+00:00');

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
            'confirmedAt'     => '2026-06-20T10:00:00+00:00',
        ], $event->payload());
    }

    public function testPurchaseOrderFullyReceived(): void
    {
        $event = new PurchaseOrderFullyReceived(self::PO_ID, self::CLINIC_ID);

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
        ], $event->payload());
    }

    public function testPurchaseOrderLineAdded(): void
    {
        $event = new PurchaseOrderLineAdded(self::PO_ID, self::CLINIC_ID, 'line-id');

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
            'lineId'          => 'line-id',
        ], $event->payload());
    }

    public function testPurchaseOrderLineCancelled(): void
    {
        $event = new PurchaseOrderLineCancelled(self::PO_ID, self::CLINIC_ID, 'line-id', 'out of stock');

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
            'lineId'          => 'line-id',
            'reason'          => 'out of stock',
        ], $event->payload());
    }

    public function testPurchaseOrderLineRemoved(): void
    {
        $event = new PurchaseOrderLineRemoved(self::PO_ID, self::CLINIC_ID, 'line-id');

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
            'lineId'          => 'line-id',
        ], $event->payload());
    }

    public function testPurchaseOrderLineUpdated(): void
    {
        $event = new PurchaseOrderLineUpdated(self::PO_ID, self::CLINIC_ID, 'line-id');

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
            'lineId'          => 'line-id',
        ], $event->payload());
    }

    public function testPurchaseOrderPartiallyReceived(): void
    {
        $event = new PurchaseOrderPartiallyReceived(self::PO_ID, self::CLINIC_ID);

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
        ], $event->payload());
    }

    public function testPurchaseOrderSendFailed(): void
    {
        $event = new PurchaseOrderSendFailed(self::PO_ID, self::CLINIC_ID, 'connection timeout');

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
            'reason'          => 'connection timeout',
        ], $event->payload());
    }

    public function testPurchaseOrderSubmitted(): void
    {
        $event = new PurchaseOrderSubmitted(
            self::PO_ID,
            self::CLINIC_ID,
            'EXT-REF-42',
            'centravet',
            '2026-06-20T10:00:00+00:00',
        );

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId'    => self::PO_ID,
            'clinicId'           => self::CLINIC_ID,
            'externalReference'  => 'EXT-REF-42',
            'externalProvidedBy' => 'centravet',
            'submittedAt'        => '2026-06-20T10:00:00+00:00',
        ], $event->payload());
    }

    public function testPurchaseOrderSubmittingStarted(): void
    {
        $event = new PurchaseOrderSubmittingStarted(self::PO_ID, self::CLINIC_ID);

        self::assertSame(self::PO_ID, $event->aggregateId());
        self::assertSame([
            'purchaseOrderId' => self::PO_ID,
            'clinicId'        => self::CLINIC_ID,
        ], $event->payload());
    }
}
