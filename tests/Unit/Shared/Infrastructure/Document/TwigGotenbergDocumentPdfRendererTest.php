<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Document;

use App\Shared\Application\Document\DocumentClinic;
use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentRenderingFailed;
use App\Shared\Application\Pdf\PdfRenderingFailedException;
use App\Shared\Infrastructure\Document\TwigGotenbergDocumentPdfRenderer;
use App\Tests\Support\Document\RecordingPdfRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(TwigGotenbergDocumentPdfRenderer::class)]
#[CoversClass(DocumentRenderingFailed::class)]
final class TwigGotenbergDocumentPdfRendererTest extends TestCase
{
    public function testTheDocumentAndItsFooterAreRenderedWithTheClinicThenTurnedIntoAPdf(): void
    {
        $pdf      = new RecordingPdfRenderer();
        $renderer = new TwigGotenbergDocumentPdfRenderer(self::twig(), $pdf);

        $result = $renderer->render(
            'clinic/document/invoice.html.twig',
            ['number' => 'FA-2026-0814'],
            new DocumentFooter(new DocumentClinic('Clinique Montmartre'), 'Facture · FA-2026-0814', 'Sophie Garnier'),
        );

        self::assertSame('%PDF', $result);
        self::assertNotNull($pdf->request);
        self::assertSame('<p>FA-2026-0814 by Clinique Montmartre</p>', $pdf->request->html);
        self::assertSame('<footer>Clinique Montmartre | Facture · FA-2026-0814 | Sophie Garnier</footer>', $pdf->request->footerHtml);
    }

    public function testADocumentWithoutClinicOrSubjectStillRenders(): void
    {
        $pdf = new RecordingPdfRenderer();

        new TwigGotenbergDocumentPdfRenderer(self::twig(), $pdf)->render(
            'clinic/document/invoice.html.twig',
            ['number' => 'FA-1'],
            new DocumentFooter(null, 'Facture · FA-1'),
        );

        self::assertSame('<footer> | Facture · FA-1 | </footer>', $pdf->request?->footerHtml);
    }

    public function testARendererFailureBecomesADocumentRenderingFailure(): void
    {
        $pdf       = new RecordingPdfRenderer();
        $pdf->fail = true;

        try {
            new TwigGotenbergDocumentPdfRenderer(self::twig(), $pdf)->render(
                'clinic/document/invoice.html.twig',
                ['number' => 'FA-1'],
                new DocumentFooter(null, 'Facture · FA-1'),
            );
            self::fail('No failure.');
        } catch (DocumentRenderingFailed $failure) {
            self::assertInstanceOf(PdfRenderingFailedException::class, $failure->getPrevious());
            self::assertStringContainsString('could not be reached: down', $failure->getMessage());
        }
    }

    private static function twig(): Environment
    {
        return new Environment(new ArrayLoader([
            'clinic/document/invoice.html.twig' => '<p>{{ number }} by {{ clinic.name ?? "nobody" }}</p>',
            'clinic/document/_footer.html.twig' => '<footer>{{ clinic.name ?? "" }} | {{ reference }} | {{ subject }}</footer>',
        ]));
    }
}
