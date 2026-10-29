<?php

declare(strict_types=1);

namespace App\Shared\Application\Document;

/**
 * Turns a document template of `templates/clinic/document/` into a PDF.
 *
 * Every printed document shares the same shell and the same page footer; the
 * bounded contexts that own a document (invoice, prescription, report…) build
 * its context and hand it here, so the Application layer can produce the PDF
 * it attaches to an e-mail as well as the one a controller streams.
 */
interface DocumentPdfRendererInterface
{
    /**
     * @param array<string, mixed> $context variables of the document template; `clinic`
     *                                      is set from the footer
     *
     * @throws DocumentRenderingFailed when the PDF service is down or refuses the document
     */
    public function render(string $template, array $context, DocumentFooter $footer): string;
}
