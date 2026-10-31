<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RenderConsultationReportFile;

/**
 * A consultation report PDF stored under the key its caller gave.
 */
final readonly class ConsultationReportFile
{
    public const string MIME = 'application/pdf';

    /**
     * @param string $filename « compte-rendu-milo-2026-10-16.pdf »
     */
    public function __construct(
        public string $storageKey,
        public string $filename,
    ) {
    }
}
