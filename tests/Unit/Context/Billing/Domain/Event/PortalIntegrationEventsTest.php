<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\Event;

use App\Context\Billing\Domain\Event\InvoiceCancelledIntegrationEvent;
use App\Context\Billing\Domain\Event\InvoiceIssuedForPortalIntegrationEvent;
use PHPUnit\Framework\TestCase;

/**
 * The Client Portal reads these events: their name, aggregate and payload
 * are asserted verbatim.
 */
final class PortalIntegrationEventsTest extends TestCase
{
    private const string INVOICE_ID = '01960000-0000-7000-8000-000000000b01';
    private const string CLINIC_ID  = '01960000-0000-7000-8000-000000000b02';

    public function testInvoiceIssuedForPortal(): void
    {
        $event = new InvoiceIssuedForPortalIntegrationEvent(self::INVOICE_ID, self::CLINIC_ID);

        self::assertSame('billing.invoice-issued-for-portal-integration.event.v1', $event->name());
        self::assertSame(self::INVOICE_ID, $event->aggregateId());
        self::assertSame(['invoiceId' => self::INVOICE_ID, 'clinicId' => self::CLINIC_ID], $event->payload());
    }

    public function testInvoiceCancelled(): void
    {
        $event = new InvoiceCancelledIntegrationEvent(self::INVOICE_ID, self::CLINIC_ID);

        self::assertSame('billing.invoice-cancelled-integration.event.v1', $event->name());
        self::assertSame(self::INVOICE_ID, $event->aggregateId());
        self::assertSame(['invoiceId' => self::INVOICE_ID, 'clinicId' => self::CLINIC_ID], $event->payload());
    }
}
