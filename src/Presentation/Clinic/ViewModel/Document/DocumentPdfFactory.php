<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Document;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentPdfRendererInterface;
use App\Shared\Application\Document\DocumentRenderingFailed;
use App\Shared\Application\Pdf\PdfRendererInterface;
use App\Shared\Application\Pdf\PdfRenderingFailedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Merges document PDFs and turns a PDF into a response.
 *
 * The documents owned by a bounded context (invoice, credit note, ordonnance,
 * consultation report) come from that context's renderer; `render()` is left
 * for the documents that have none yet (cheque deposit slip, narcotics
 * register), through the same shared renderer.
 */
final readonly class DocumentPdfFactory
{
    public function __construct(
        private DocumentPdfRendererInterface $documents,
        private PdfRendererInterface $renderer,
    ) {
    }

    /**
     * @param array<string, mixed> $context   variables of the document template
     * @param string               $reference what identifies the document in the page footer
     * @param string|null          $subject   who it is about, printed after the reference
     */
    public function render(
        string $template,
        array $context,
        ?ClinicDto $clinic,
        string $reference,
        ?string $subject = null,
    ): string {
        try {
            return $this->documents->render($template, $context, new DocumentFooter($clinic?->toDocumentClinic(), $reference, $subject));
        } catch (DocumentRenderingFailed $exception) {
            throw self::unavailable($exception);
        }
    }

    /**
     * @param list<string> $documents
     */
    public function merge(array $documents): string
    {
        try {
            return $this->renderer->merge($documents);
        } catch (PdfRenderingFailedException $exception) {
            throw self::unavailable($exception);
        }
    }

    /**
     * Inline rather than attachment: the viewer shows it, and saving it is one
     * click away. The name is what the browser suggests when saving; a ".pdf"
     * it already ends with is not doubled.
     */
    public function response(string $pdf, string $filename): Response
    {
        $name = preg_replace('/\.pdf$/i', '', $filename) ?? $filename;

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => \sprintf('inline; filename="%s.pdf"', self::sanitize($name)),
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /**
     * The renderer is another service: when it is down or refuses a document,
     * this is a gateway failure, not a bug in the request.
     */
    public static function unavailable(DocumentRenderingFailed|PdfRenderingFailedException $exception): HttpException
    {
        return new HttpException(
            Response::HTTP_BAD_GATEWAY,
            'Le document n\'a pas pu être généré : le service d\'impression est indisponible.',
            $exception,
        );
    }

    /**
     * A file name has to survive a header, a file system and a download folder.
     */
    private static function sanitize(string $filename): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $filename);

        if (false === $ascii) {
            $ascii = $filename;
        }

        $cleaned = preg_replace('/[^A-Za-z0-9._-]+/', '-', $ascii) ?? '';
        $trimmed = trim($cleaned, '-');

        return '' === $trimmed ? 'document' : $trimmed;
    }
}
