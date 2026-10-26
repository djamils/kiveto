<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The invoice of a visit, read from the Billing context, and the PDF
 * Billing keeps of it once sent.
 */
interface InvoiceDocumentInterface
{
    /** The issued (numbered, not cancelled) invoice of the visit, if any. */
    public function invoiceOf(string $clinicId, string $admissionId): ?ConsultationInvoice;

    /**
     * The invoice PDF as sent to the client, stored once by Billing and
     * kept by it: attach it, never delete it.
     *
     * @throws \DomainException when the invoice is unknown to this clinic
     */
    public function sentFile(string $clinicId, string $invoiceId): StoredDocument;
}
