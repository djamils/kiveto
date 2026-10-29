<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Document;

use App\Context\Billing\Application\Port\ClinicDocumentContextInterface;
use App\Context\Billing\Application\Port\InvoicePdfRendererInterface;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\GetCreditNoteDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentPdfRendererInterface;
use App\Shared\Application\Document\RenderedDocument;

/**
 * Invoices and credit notes through the shared document templates. Every
 * invoice carries the clinic's payment details (IBAN, footer note), whether it
 * is printed alone, in a consultation bundle, in a selection or attached to an
 * e-mail.
 */
final readonly class InvoicePdfRenderer implements InvoicePdfRendererInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private ClinicDocumentContextInterface $clinics,
        private DocumentPdfRendererInterface $documents,
    ) {
    }

    public function invoice(string $clinicId, string $invoiceId): RenderedDocument
    {
        $invoice = $this->queryBus->ask(new GetInvoiceDetails(clinicId: $clinicId, invoiceId: $invoiceId));
        \assert($invoice instanceof InvoiceDetails);

        $context = $this->clinics->forClinic($clinicId);

        $pdf = $this->documents->render(
            'clinic/document/invoice.html.twig',
            ['invoice' => $invoice, 'billing' => $context->payment],
            new DocumentFooter($context->clinic, \sprintf('Facture · %s', $invoice->number ?? 'brouillon'), $invoice->payerName),
        );

        return new RenderedDocument(($invoice->number ?? 'facture') . '.pdf', $pdf);
    }

    public function creditNote(string $clinicId, string $creditNoteId): RenderedDocument
    {
        $note = $this->queryBus->ask(new GetCreditNoteDetails($clinicId, $creditNoteId));
        \assert($note instanceof CreditNoteDetails);

        $pdf = $this->documents->render(
            'clinic/document/credit_note.html.twig',
            ['note' => $note],
            new DocumentFooter($this->clinics->forClinic($clinicId)->clinic, \sprintf('Avoir · %s', $note->number), $note->payerName),
        );

        return new RenderedDocument($note->number . '.pdf', $pdf);
    }
}
