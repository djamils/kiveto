<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Document;

use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentPdfRendererInterface;
use App\Shared\Application\Document\DocumentRenderingFailed;
use App\Shared\Application\Pdf\PdfRendererInterface;
use App\Shared\Application\Pdf\PdfRenderingFailedException;
use App\Shared\Application\Pdf\PdfRenderRequest;
use Twig\Environment;

/**
 * Twig renders the document and its page footer, the PDF renderer (Gotenberg)
 * turns them into a PDF.
 */
final readonly class TwigGotenbergDocumentPdfRenderer implements DocumentPdfRendererInterface
{
    private const string FOOTER_TEMPLATE = 'clinic/document/_footer.html.twig';

    public function __construct(
        private Environment $twig,
        private PdfRendererInterface $renderer,
    ) {
    }

    public function render(string $template, array $context, DocumentFooter $footer): string
    {
        $request = new PdfRenderRequest(
            html: $this->twig->render($template, $context + ['clinic' => $footer->clinic]),
            footerHtml: $this->twig->render(self::FOOTER_TEMPLATE, [
                'clinic'    => $footer->clinic,
                'reference' => $footer->reference,
                'subject'   => $footer->subject,
            ]),
        );

        try {
            return $this->renderer->render($request);
        } catch (PdfRenderingFailedException $exception) {
            throw DocumentRenderingFailed::because($exception);
        }
    }
}
