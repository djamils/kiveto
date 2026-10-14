<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\Event;

use App\Context\Billing\Domain\Event\CreditNoteIssued;
use App\Context\Billing\Domain\Event\InvoiceCancelled;
use App\Context\Billing\Domain\Event\InvoiceCredited;
use App\Context\Billing\Domain\Event\InvoiceDrafted;
use App\Context\Billing\Domain\Event\InvoiceIssued;
use App\Context\Billing\Domain\Event\InvoicePaid;
use App\Context\Billing\Domain\Event\PaymentRecorded;
use PHPUnit\Framework\TestCase;

/**
 * Events are what the rest of the system reads about billing, so their name,
 * their aggregate and every key of their payload are asserted verbatim.
 */
final class BillingEventsTest extends TestCase
{
    private const string INVOICE_ID   = '01960000-0000-7000-8000-000000000101';
    private const string CLINIC_ID    = '01960000-0000-7000-8000-000000000102';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-000000000103';
    private const string PAYMENT_ID   = '01960000-0000-7000-8000-000000000104';
    private const string AT           = '2026-08-22T10:00:00+00:00';

    public function testInvoiceDrafted(): void
    {
        $event = new InvoiceDrafted(self::INVOICE_ID, self::CLINIC_ID, self::ADMISSION_ID, self::AT);

        self::assertSame('billing.invoice.drafted.v1', $event->name());
        self::assertSame(self::INVOICE_ID, $event->aggregateId());
        self::assertSame([
            'invoiceId'   => self::INVOICE_ID,
            'clinicId'    => self::CLINIC_ID,
            'admissionId' => self::ADMISSION_ID,
            'draftedAt'   => self::AT,
        ], $event->payload());
    }

    public function testInvoiceIssued(): void
    {
        $event = new InvoiceIssued(self::INVOICE_ID, self::CLINIC_ID, 'FAC-2026-000001', 6000, 'EUR', self::AT);

        self::assertSame('billing.invoice.issued.v1', $event->name());
        self::assertSame(self::INVOICE_ID, $event->aggregateId());
        self::assertSame([
            'invoiceId'            => self::INVOICE_ID,
            'clinicId'             => self::CLINIC_ID,
            'number'               => 'FAC-2026-000001',
            'totalGrossMinorUnits' => 6000,
            'currency'             => 'EUR',
            'issuedAt'             => self::AT,
        ], $event->payload());
    }

    public function testPaymentRecorded(): void
    {
        $event = new PaymentRecorded(
            self::INVOICE_ID,
            self::CLINIC_ID,
            self::PAYMENT_ID,
            'CARD',
            6000,
            'EUR',
            self::AT,
        );

        self::assertSame('billing.payment.recorded.v1', $event->name());
        self::assertSame(self::INVOICE_ID, $event->aggregateId());
        self::assertSame([
            'invoiceId'        => self::INVOICE_ID,
            'clinicId'         => self::CLINIC_ID,
            'paymentId'        => self::PAYMENT_ID,
            'method'           => 'CARD',
            'amountMinorUnits' => 6000,
            'currency'         => 'EUR',
            'receivedAt'       => self::AT,
        ], $event->payload());
    }

    public function testInvoicePaid(): void
    {
        $event = new InvoicePaid(self::INVOICE_ID, self::CLINIC_ID, self::ADMISSION_ID, 6000, 'EUR', self::AT);

        self::assertSame('billing.invoice.paid.v1', $event->name());
        self::assertSame(self::INVOICE_ID, $event->aggregateId());
        self::assertSame([
            'invoiceId'            => self::INVOICE_ID,
            'clinicId'             => self::CLINIC_ID,
            'admissionId'          => self::ADMISSION_ID,
            'totalGrossMinorUnits' => 6000,
            'currency'             => 'EUR',
            'paidAt'               => self::AT,
        ], $event->payload());
    }

    public function testInvoiceCancelled(): void
    {
        $event = new InvoiceCancelled(self::INVOICE_ID, self::CLINIC_ID, 'Erreur de saisie', self::AT);

        self::assertSame('billing.invoice.cancelled.v1', $event->name());
        self::assertSame(self::INVOICE_ID, $event->aggregateId());
        self::assertSame([
            'invoiceId'   => self::INVOICE_ID,
            'clinicId'    => self::CLINIC_ID,
            'reason'      => 'Erreur de saisie',
            'cancelledAt' => self::AT,
        ], $event->payload());
    }

    public function testInvoiceCredited(): void
    {
        $event = new InvoiceCredited(self::INVOICE_ID, self::CLINIC_ID, 1500, 500, 'EUR', self::AT);

        self::assertSame('billing.invoice.credited.v1', $event->name());
        self::assertSame(self::INVOICE_ID, $event->aggregateId());
        self::assertSame([
            'invoiceId'          => self::INVOICE_ID,
            'clinicId'           => self::CLINIC_ID,
            'creditedMinorUnits' => 1500,
            'refundedMinorUnits' => 500,
            'currency'           => 'EUR',
            'creditedAt'         => self::AT,
        ], $event->payload());
    }

    public function testCreditNoteIssued(): void
    {
        $event = new CreditNoteIssued(self::PAYMENT_ID, self::CLINIC_ID, self::INVOICE_ID, 'AV-2026-000001', 1500, 0, 'EUR', self::AT);

        self::assertSame('billing.credit-note.issued.v1', $event->name());
        self::assertSame(self::PAYMENT_ID, $event->aggregateId());
        self::assertSame([
            'creditNoteId'         => self::PAYMENT_ID,
            'clinicId'             => self::CLINIC_ID,
            'invoiceId'            => self::INVOICE_ID,
            'number'               => 'AV-2026-000001',
            'totalGrossMinorUnits' => 1500,
            'refundedMinorUnits'   => 0,
            'currency'             => 'EUR',
            'issuedAt'             => self::AT,
        ], $event->payload());
    }
}
