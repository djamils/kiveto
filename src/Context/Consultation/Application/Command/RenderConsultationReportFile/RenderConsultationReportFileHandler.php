<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RenderConsultationReportFile;

use App\Context\Consultation\Application\Port\ConsultationReportPdfRendererInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Renders the consultation report (Gotenberg) and stores it: no database
 * write, so the caller's transaction holds no lock during the call.
 */
#[AsMessageHandler]
final readonly class RenderConsultationReportFileHandler
{
    public function __construct(
        private ConsultationReportPdfRendererInterface $renderer,
        private FileStorageInterface $storage,
    ) {
    }

    public function __invoke(RenderConsultationReportFile $command): ConsultationReportFile
    {
        $document = $this->renderer->report($command->clinicId, $command->consultationId);

        $this->storage->storeAt($command->storageKey, $document->content);

        return new ConsultationReportFile($command->storageKey, $document->filename);
    }
}
