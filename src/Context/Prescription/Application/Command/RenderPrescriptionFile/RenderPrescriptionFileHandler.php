<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\RenderPrescriptionFile;

use App\Context\Prescription\Application\Port\PrescriptionPdfRendererInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Renders the ordonnance (Gotenberg) and stores it: no database write, so
 * the caller's transaction holds no lock during the call.
 */
#[AsMessageHandler]
final readonly class RenderPrescriptionFileHandler
{
    public function __construct(
        private PrescriptionPdfRendererInterface $renderer,
        private FileStorageInterface $storage,
    ) {
    }

    public function __invoke(RenderPrescriptionFile $command): PrescriptionFile
    {
        $document = $this->renderer->prescription($command->clinicId, $command->prescriptionId);

        $this->storage->storeAt($command->storageKey, $document->content);

        return new PrescriptionFile($command->storageKey, $document->filename);
    }
}
