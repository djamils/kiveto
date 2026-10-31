<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile;

use App\Context\ClientPortal\Application\Command\AttachPublishedDocumentFile\AttachPublishedDocumentFile;
use App\Context\ClientPortal\Application\Command\RecordDocumentRenderFailure\RecordDocumentRenderFailure;
use App\Context\ClientPortal\Application\Port\ConsultationDocumentsInterface;
use App\Context\ClientPortal\Application\Port\InvoiceDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A render that throws is counted (five failures make the document
 * `failed`) and rethrown, so that Messenger retries it too.
 */
#[AsMessageHandler(bus: 'messenger.bus.external_api')]
final readonly class StorePublishedDocumentFileHandler
{
    public const string KEY_PREFIX = 'client_portal/documents/';

    public function __construct(
        private PublishedDocumentRepositoryInterface $documents,
        private InvoiceDocumentsInterface $invoices,
        private PrescriptionDocumentsInterface $prescriptions,
        private ConsultationDocumentsInterface $consultations,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(StorePublishedDocumentFile $command): void
    {
        // Outside any transaction: the row lock ends with the statement.
        $document = $this->documents->findByIdForUpdate($command->documentId);

        if (null === $document || $document->isWithdrawn() || $command->version !== $document->pendingFileVersion()) {
            return;
        }

        try {
            $key = $this->render($document, $command->version);
        } catch (\Throwable $failure) {
            $this->commandBus->dispatch(new RecordDocumentRenderFailure($command->documentId));

            throw $failure;
        }

        $this->commandBus->dispatch(new AttachPublishedDocumentFile($command->documentId, $command->version, $key));
    }

    public static function storageKey(string $documentId, int $version): string
    {
        return self::KEY_PREFIX . $documentId . '-v' . $version . '.pdf';
    }

    private function render(PublishedDocument $document, int $version): string
    {
        $key = self::storageKey($document->id()->toString(), $version);

        match ($document->type()) {
            DocumentType::INVOICE      => $key = $this->invoices->ensureFile($document->clinicId(), $document->sourceRef()),
            DocumentType::PRESCRIPTION => $this->prescriptions->renderFile($document->clinicId(), $document->sourceRef(), $key),
            DocumentType::REPORT       => $this->consultations->renderReport($document->clinicId(), $document->sourceRef(), $key),
            DocumentType::LAB_RESULT, DocumentType::CERTIFICATE => throw new \LogicException(\sprintf('No file producer for "%s" documents.', $document->type()->value)),
        };

        return $key;
    }
}
