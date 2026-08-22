<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Pdf;

use App\Shared\Application\Pdf\PdfRendererInterface;
use App\Shared\Application\Pdf\PdfRenderingFailedException;
use App\Shared\Application\Pdf\PdfRenderRequest;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Gotenberg is Chromium behind an HTTP API: it prints the document exactly as
 * the browser would, which is why the layouts can rely on `mm`, `@page` and a
 * repeated footer.
 *
 * Uploaded parts are identified by their file name, not by the form field they
 * are sent under — hence the file name used as the field name throughout.
 */
final readonly class GotenbergPdfRenderer implements PdfRendererInterface
{
    /** A4, in the same units as the margins. */
    private const string PAPER_WIDTH  = '210mm';
    private const string PAPER_HEIGHT = '297mm';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(GOTENBERG_URL)%')]
        private string $baseUrl,
    ) {
    }

    public function render(PdfRenderRequest $request): string
    {
        $fields = [
            'index.html'      => new DataPart($request->html, 'index.html', 'text/html'),
            'paperWidth'      => self::PAPER_WIDTH,
            'paperHeight'     => self::PAPER_HEIGHT,
            'marginTop'       => $request->marginTop,
            'marginBottom'    => $request->marginBottom,
            'marginLeft'      => $request->marginLeft,
            'marginRight'     => $request->marginRight,
            'printBackground' => $request->printBackground ? 'true' : 'false',
            // The request sizes the paper; letting the CSS drive it would make
            // the output depend on whichever `@page` rule wins.
            'preferCssPageSize' => 'false',
        ];

        if (null !== $request->footerHtml) {
            $fields['footer.html'] = new DataPart($request->footerHtml, 'footer.html', 'text/html');
        }

        return $this->post('/forms/chromium/convert/html', $fields);
    }

    public function merge(array $documents): string
    {
        if ([] === $documents) {
            throw new \InvalidArgumentException('Cannot merge an empty list of documents.');
        }

        if (1 === \count($documents)) {
            return $documents[0];
        }

        $fields = [];

        foreach ($documents as $index => $document) {
            // Gotenberg merges in alphabetical order, so the names have to sort
            // the way the caller listed them.
            $name          = \sprintf('%03d.pdf', $index);
            $fields[$name] = new DataPart($document, $name, 'application/pdf');
        }

        return $this->post('/forms/pdfengines/merge', $fields);
    }

    /**
     * @param array<string, string|DataPart> $fields
     */
    private function post(string $path, array $fields): string
    {
        $form = new FormDataPart($fields);

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . $path, [
                'headers' => $form->getPreparedHeaders()->toArray(),
                'body'    => $form->bodyToIterable(),
            ]);

            $statusCode = $response->getStatusCode();
            $body       = $response->getContent(false);
        } catch (HttpExceptionInterface $exception) {
            throw PdfRenderingFailedException::unreachable($exception->getMessage(), $exception);
        }

        if (200 !== $statusCode) {
            throw PdfRenderingFailedException::rejected($statusCode, $body);
        }

        return $body;
    }
}
