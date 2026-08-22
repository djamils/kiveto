<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Pdf;

use App\Shared\Application\Pdf\PdfRendererInterface;
use App\Shared\Application\Pdf\PdfRenderingFailedException;
use App\Shared\Application\Pdf\PdfRenderRequest;
use App\Shared\Infrastructure\Pdf\GotenbergPdfRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(GotenbergPdfRenderer::class)]
#[CoversClass(PdfRenderRequest::class)]
#[CoversClass(PdfRenderingFailedException::class)]
final class GotenbergPdfRendererTest extends TestCase
{
    public function testTheDocumentIsPostedAsMultipartAndThePdfComesBack(): void
    {
        $sent = null;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['method' => $method, 'url' => $url, 'body' => self::bodyOf($options)];

            return new MockResponse('%PDF-1.4 rendered');
        });

        $renderer = new GotenbergPdfRenderer($client, 'http://gotenberg:3000/');

        $pdf = $renderer->render(new PdfRenderRequest(
            html: '<html lang="fr"><body>Compte rendu</body></html>',
            footerHtml: '<html lang="fr"><body>page</body></html>',
        ));

        self::assertSame('%PDF-1.4 rendered', $pdf);
        self::assertIsArray($sent);
        self::assertSame('POST', $sent['method']);
        self::assertSame('http://gotenberg:3000/forms/chromium/convert/html', $sent['url']);

        $body = $sent['body'];

        self::assertStringContainsString('filename="index.html"', $body);
        self::assertStringContainsString('filename="footer.html"', $body);
        self::assertStringContainsString('Compte rendu', $body);
        self::assertStringContainsString('210mm', $body);
        self::assertStringContainsString('printBackground', $body);
    }

    public function testADocumentWithoutAFooterSendsNoFooterPart(): void
    {
        $sent = null;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = self::bodyOf($options);

            return new MockResponse('%PDF');
        });

        $renderer = new GotenbergPdfRenderer($client, 'http://gotenberg:3000');
        $renderer->render(new PdfRenderRequest(html: '<html lang="fr"><body>Facture</body></html>'));

        self::assertIsString($sent);
        self::assertStringContainsString('filename="index.html"', $sent);
        self::assertStringNotContainsString('footer.html', $sent);
    }

    public function testAnEmptyDocumentIsRefusedBeforeAnyCall(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot render an empty document.');

        new PdfRenderRequest(html: '   ');
    }

    public function testARejectedDocumentCarriesTheStatusAndTheReason(): void
    {
        $client   = new MockHttpClient(new MockResponse('malformed HTML', ['http_code' => 400]));
        $renderer = new GotenbergPdfRenderer($client, 'http://gotenberg:3000');

        $this->expectException(PdfRenderingFailedException::class);
        $this->expectExceptionMessage('The PDF renderer refused the document (HTTP 400): malformed HTML');

        $renderer->render(new PdfRenderRequest(html: '<html lang="fr"><body>x</body></html>'));
    }

    public function testARejectionWithoutABodyStillReportsTheStatus(): void
    {
        $client   = new MockHttpClient(new MockResponse('', ['http_code' => 503]));
        $renderer = new GotenbergPdfRenderer($client, 'http://gotenberg:3000');

        $this->expectException(PdfRenderingFailedException::class);
        $this->expectExceptionMessage('The PDF renderer refused the document (HTTP 503): no detail given');

        $renderer->render(new PdfRenderRequest(html: '<html lang="fr"><body>x</body></html>'));
    }

    public function testAnUnreachableRendererIsReportedAsSuch(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('Connection refused');
        });

        $renderer = new GotenbergPdfRenderer($client, 'http://gotenberg:3000');

        $this->expectException(PdfRenderingFailedException::class);
        $this->expectExceptionMessage('The PDF renderer could not be reached: Connection refused');

        $renderer->render(new PdfRenderRequest(html: '<html lang="fr"><body>x</body></html>'));
    }

    public function testDocumentsAreMergedInTheOrderGiven(): void
    {
        $sent = null;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['url' => $url, 'body' => self::bodyOf($options)];

            return new MockResponse('%PDF merged');
        });

        $renderer = new GotenbergPdfRenderer($client, 'http://gotenberg:3000');

        self::assertSame('%PDF merged', $renderer->merge(['%PDF first', '%PDF second']));
        self::assertIsArray($sent);
        self::assertSame('http://gotenberg:3000/forms/pdfengines/merge', $sent['url']);

        $body   = $sent['body'];
        $first  = mb_strpos($body, 'filename="000.pdf"');
        $second = mb_strpos($body, 'filename="001.pdf"');
        self::assertIsInt($first);
        self::assertIsInt($second);
        self::assertLessThan($second, $first);
    }

    public function testMergingASingleDocumentReturnsItUntouched(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            self::fail('A single document must not be sent to the renderer.');
        });

        $renderer = new GotenbergPdfRenderer($client, 'http://gotenberg:3000');

        self::assertSame('%PDF alone', $renderer->merge(['%PDF alone']));
    }

    public function testMergingNothingIsARefusal(): void
    {
        $renderer = new GotenbergPdfRenderer(new MockHttpClient(), 'http://gotenberg:3000');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot merge an empty list of documents.');

        $renderer->merge([]);
    }

    public function testTheRendererHonoursItsPort(): void
    {
        $renderer = new GotenbergPdfRenderer(new MockHttpClient(), 'http://gotenberg:3000');

        self::assertInstanceOf(PdfRendererInterface::class, $renderer);
    }

    /**
     * The multipart body is streamed, so it has to be pulled out of the iterable
     * the client was handed.
     *
     * @param array<mixed> $options
     */
    private static function bodyOf(array $options): string
    {
        $body = $options['body'] ?? null;

        if (\is_string($body)) {
            return $body;
        }

        // The client normalises an iterable body into a closure yielding chunks
        // until it returns an empty string.
        self::assertInstanceOf(\Closure::class, $body);

        $buffer = '';

        while ('' !== ($chunk = $body(16372))) {
            self::assertIsString($chunk);
            $buffer .= $chunk;
        }

        return $buffer;
    }
}
