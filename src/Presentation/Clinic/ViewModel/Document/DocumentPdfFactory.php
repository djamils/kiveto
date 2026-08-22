<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Document;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Shared\Application\Pdf\PdfRendererInterface;
use App\Shared\Application\Pdf\PdfRenderingFailedException;
use App\Shared\Application\Pdf\PdfRenderRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twig\Environment;

/**
 * Turns a document template into a PDF, and a PDF into a response.
 *
 * The three documents share the same shell, the same footer and the same
 * headers, so the assembly lives here rather than in each controller.
 */
final readonly class DocumentPdfFactory
{
    public function __construct(
        private Environment $twig,
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
        $request = new PdfRenderRequest(
            html: $this->twig->render($template, $context + ['clinic' => $clinic]),
            footerHtml: $this->twig->render('clinic/document/_footer.html.twig', [
                'clinic'    => $clinic,
                'reference' => $reference,
                'subject'   => $subject,
            ]),
        );

        try {
            return $this->renderer->render($request);
        } catch (PdfRenderingFailedException $exception) {
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
     * click away. The name is what the browser suggests when saving.
     */
    public function response(string $pdf, string $filename): Response
    {
        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => \sprintf('inline; filename="%s.pdf"', self::sanitize($filename)),
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /**
     * The renderer is another service: when it is down or refuses a document,
     * this is a gateway failure, not a bug in the request.
     */
    private static function unavailable(PdfRenderingFailedException $exception): HttpException
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
