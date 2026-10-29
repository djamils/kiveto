<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Document;

use App\Context\Billing\Application\Port\ClinicDocumentContext;
use App\Context\Billing\Application\Port\ClinicDocumentContextInterface;
use App\Context\Billing\Application\Port\InvoicePaymentDetails;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\GetCreditNoteDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Infrastructure\Document\InvoicePdfRenderer;
use App\Shared\Application\Document\DocumentClinic;
use App\Shared\Application\Document\DocumentRenderingFailed;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Support\Document\CapturingDocumentPdfRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoicePdfRenderer::class)]
#[CoversClass(ClinicDocumentContext::class)]
#[CoversClass(InvoicePaymentDetails::class)]
final class InvoicePdfRendererTest extends TestCase
{
    private const string CLINIC_ID      = '01960000-0000-7000-8000-000000000001';
    private const string INVOICE_ID     = '01960000-0000-7000-8000-0000000b0001';
    private const string CREDIT_NOTE_ID = '01960000-0000-7000-8000-0000000b0002';

    public function testAnInvoiceIsPrintedWithTheClinicAndItsPaymentDetails(): void
    {
        $documents = new CapturingDocumentPdfRenderer();
        $invoice   = self::invoice('FA-2026-0814');
        $bus       = new AnsweringQueryBus([GetInvoiceDetails::class => $invoice]);

        $rendered = new InvoicePdfRenderer($bus, self::clinics(), $documents)->invoice(self::CLINIC_ID, self::INVOICE_ID);

        self::assertSame('FA-2026-0814.pdf', $rendered->filename);
        self::assertSame('%PDF clinic/document/invoice.html.twig', $rendered->content);

        $last = $documents->last();
        self::assertSame($invoice, $last['context']['invoice']);
        $payment = $last['context']['billing'];
        self::assertInstanceOf(InvoicePaymentDetails::class, $payment);
        self::assertSame('FR76 3000 6000', $payment->iban);
        self::assertSame('Clinique Montmartre', $last['footer']->clinic?->name);
        self::assertSame('Facture · FA-2026-0814', $last['footer']->reference);
        self::assertSame('Sophie Garnier', $last['footer']->subject);

        $query = $bus->asked[0];
        self::assertInstanceOf(GetInvoiceDetails::class, $query);
        self::assertSame(self::INVOICE_ID, $query->invoiceId);
        self::assertSame(self::CLINIC_ID, $query->clinicId);
    }

    public function testADraftIsPrintedAsADraft(): void
    {
        $documents = new CapturingDocumentPdfRenderer();
        $bus       = new AnsweringQueryBus([GetInvoiceDetails::class => self::invoice(null)]);

        $rendered = new InvoicePdfRenderer($bus, self::clinics(), $documents)->invoice(self::CLINIC_ID, self::INVOICE_ID);

        self::assertSame('facture.pdf', $rendered->filename);
        self::assertSame('Facture · brouillon', $documents->last()['footer']->reference);
    }

    public function testACreditNoteIsPrintedWithTheClinic(): void
    {
        $documents = new CapturingDocumentPdfRenderer();
        $bus       = new AnsweringQueryBus([GetCreditNoteDetails::class => self::creditNote()]);

        $rendered = new InvoicePdfRenderer($bus, self::clinics(), $documents)->creditNote(self::CLINIC_ID, self::CREDIT_NOTE_ID);

        self::assertSame('AV-2026-0003.pdf', $rendered->filename);
        $last = $documents->last();
        self::assertSame('clinic/document/credit_note.html.twig', $last['template']);
        self::assertSame('Avoir · AV-2026-0003', $last['footer']->reference);
        self::assertSame('Sophie Garnier', $last['footer']->subject);
        self::assertSame('Clinique Montmartre', $last['footer']->clinic?->name);
    }

    public function testAnUnknownInvoiceIsRefusedBeforeAnythingIsRendered(): void
    {
        $documents = new CapturingDocumentPdfRenderer();
        $bus       = new AnsweringQueryBus([GetInvoiceDetails::class => static fn (): never => throw new \DomainException('Unknown invoice')]);

        try {
            new InvoicePdfRenderer($bus, self::clinics(), $documents)->invoice(self::CLINIC_ID, self::INVOICE_ID);
            self::fail('An unknown invoice was rendered.');
        } catch (\DomainException) {
            self::assertSame([], $documents->rendered);
        }
    }

    public function testARenderingFailureIsPassedOn(): void
    {
        $bus = new AnsweringQueryBus([GetInvoiceDetails::class => self::invoice('FA-1')]);

        $this->expectException(DocumentRenderingFailed::class);

        new InvoicePdfRenderer($bus, self::clinics(), new CapturingDocumentPdfRenderer(failing: true))->invoice(self::CLINIC_ID, self::INVOICE_ID);
    }

    private static function clinics(): ClinicDocumentContextInterface
    {
        return new class implements ClinicDocumentContextInterface {
            public function forClinic(string $clinicId): ClinicDocumentContext
            {
                return new ClinicDocumentContext(
                    new DocumentClinic('Clinique Montmartre'),
                    new InvoicePaymentDetails('FR76 3000 6000', 'AGRIFRPP', false, 'Règlement à réception.'),
                );
            }
        };
    }

    private static function invoice(?string $number): InvoiceDetails
    {
        return new InvoiceDetails(
            id: self::INVOICE_ID,
            clinicId: self::CLINIC_ID,
            number: $number,
            status: null === $number ? 'DRAFT' : 'ISSUED',
            statusLabel: 'Émise',
            admissionId: null,
            payerClientId: null,
            payerName: 'Sophie Garnier',
            payerAddressLines: [],
            lines: [],
            payments: [],
            taxBreakdown: [],
            currency: 'EUR',
            totalNetBeforeDiscountMinorUnits: 7392,
            discountMinorUnits: 0,
            discountKind: null,
            discountValue: null,
            discountReason: null,
            totalNetMinorUnits: 7392,
            totalTaxMinorUnits: 1478,
            totalGrossMinorUnits: 8870,
            amountPaidMinorUnits: 0,
            balanceDueMinorUnits: 8870,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-10-22 10:00:00'),
            issuedAt: new \DateTimeImmutable('2026-10-22 10:00:00'),
            paidAt: null,
            cancelledAt: null,
            cancellationReason: null,
        );
    }

    private static function creditNote(): CreditNoteDetails
    {
        return new CreditNoteDetails(
            id: self::CREDIT_NOTE_ID,
            clinicId: self::CLINIC_ID,
            number: 'AV-2026-0003',
            invoiceId: self::INVOICE_ID,
            invoiceNumber: 'FA-2026-0814',
            invoiceIssuedAt: new \DateTimeImmutable('2026-10-22 10:00:00'),
            admissionId: null,
            payerClientId: null,
            payerName: 'Sophie Garnier',
            payerAddressLines: [],
            lines: [],
            taxBreakdown: [],
            currency: 'EUR',
            totalNetMinorUnits: 1000,
            totalTaxMinorUnits: 200,
            totalGrossMinorUnits: 1200,
            reason: 'Erreur de saisie',
            refundMethod: null,
            refundMethodLabel: null,
            refundedMinorUnits: 0,
            issuedAt: new \DateTimeImmutable('2026-10-23 10:00:00'),
            issuedByUserId: null,
        );
    }
}
