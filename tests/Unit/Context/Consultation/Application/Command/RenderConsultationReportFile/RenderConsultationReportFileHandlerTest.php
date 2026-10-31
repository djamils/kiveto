<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\RenderConsultationReportFile;

use App\Context\Consultation\Application\Command\RenderConsultationReportFile\ConsultationReportFile;
use App\Context\Consultation\Application\Command\RenderConsultationReportFile\RenderConsultationReportFile;
use App\Context\Consultation\Application\Command\RenderConsultationReportFile\RenderConsultationReportFileHandler;
use App\Context\Consultation\Application\Port\ConsultationReportPdfRendererInterface;
use App\Shared\Application\Document\RenderedDocument;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use PHPUnit\Framework\TestCase;

final class RenderConsultationReportFileHandlerTest extends TestCase
{
    public function testTheReportIsStoredUnderTheCallersKey(): void
    {
        $renderer = new class implements ConsultationReportPdfRendererInterface {
            /** @var list<string> */
            public array $rendered = [];

            public function report(string $clinicId, string $consultationId): RenderedDocument
            {
                $this->rendered[] = $clinicId . '/' . $consultationId;

                return new RenderedDocument('compte-rendu-milo-2026-10-16.pdf', '%PDF report');
            }
        };
        $storage = new InMemoryFiles();

        $file = new RenderConsultationReportFileHandler($renderer, $storage)(new RenderConsultationReportFile('clinic-1', 'consultation-1', 'client_portal/documents/doc-1-v1.pdf'));

        self::assertSame(['client_portal/documents/doc-1-v1.pdf', 'compte-rendu-milo-2026-10-16.pdf'], [$file->storageKey, $file->filename]);
        self::assertSame(['client_portal/documents/doc-1-v1.pdf' => '%PDF report'], $storage->files);
        self::assertSame(['clinic-1/consultation-1'], $renderer->rendered);
        self::assertSame('application/pdf', ConsultationReportFile::MIME);
    }
}
