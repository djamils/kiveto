<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Application\Command;

use App\Context\Prescription\Application\Command\RenderPrescriptionFile\PrescriptionFile;
use App\Context\Prescription\Application\Command\RenderPrescriptionFile\RenderPrescriptionFile;
use App\Context\Prescription\Application\Command\RenderPrescriptionFile\RenderPrescriptionFileHandler;
use App\Context\Prescription\Application\Port\PrescriptionPdfRendererInterface;
use App\Shared\Application\Document\RenderedDocument;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use PHPUnit\Framework\TestCase;

final class RenderPrescriptionFileHandlerTest extends TestCase
{
    public function testTheOrdonnanceIsStoredUnderTheCallersKey(): void
    {
        $renderer = new class implements PrescriptionPdfRendererInterface {
            /** @var list<string> */
            public array $rendered = [];

            public function prescription(string $clinicId, string $prescriptionId): RenderedDocument
            {
                $this->rendered[] = $clinicId . '/' . $prescriptionId;

                return new RenderedDocument('ORD-2026-000391.pdf', '%PDF ordonnance');
            }
        };
        $storage = new InMemoryFiles();

        $file = new RenderPrescriptionFileHandler($renderer, $storage)(new RenderPrescriptionFile('clinic-1', 'rx-1', 'notification/attachments/abc/ordonnance-rx-1.pdf'));

        self::assertSame(['notification/attachments/abc/ordonnance-rx-1.pdf', 'ORD-2026-000391.pdf'], [$file->storageKey, $file->filename]);
        self::assertSame(['notification/attachments/abc/ordonnance-rx-1.pdf' => '%PDF ordonnance'], $storage->files);
        self::assertSame(['clinic-1/rx-1'], $renderer->rendered);
        self::assertSame('application/pdf', PrescriptionFile::MIME);
    }
}
