<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Shared\Application\Document\DocumentRenderingFailed;
use App\Shared\Application\Document\RenderedDocument;

/**
 * The PDF of an invoice or a credit note, as printed and as attached to an
 * e-mail: the same document with the clinic's letterhead and payment details.
 */
interface InvoicePdfRendererInterface
{
    /**
     * @throws \DomainException        when the invoice is unknown to this clinic
     * @throws DocumentRenderingFailed when the PDF service is down or refuses it
     */
    public function invoice(string $clinicId, string $invoiceId): RenderedDocument;

    /**
     * @throws \DomainException        when the credit note is unknown to this clinic
     * @throws DocumentRenderingFailed when the PDF service is down or refuses it
     */
    public function creditNote(string $clinicId, string $creditNoteId): RenderedDocument;
}
