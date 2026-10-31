<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RecordDocumentRenderFailure;

use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordDocumentRenderFailureHandler
{
    public function __construct(
        private PublishedDocumentRepositoryInterface $documents,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecordDocumentRenderFailure $command): void
    {
        $document = $this->documents->findByIdForUpdate($command->documentId);

        if (null === $document) {
            return;
        }

        $gaveUp = $document->renderFailed($this->clock->now());
        $this->documents->save($document);

        if ($gaveUp) {
            $this->logger->error('The file of a published document could not be rendered: it is no longer retried.', [
                'documentId' => $command->documentId,
                'type'       => $document->type()->value,
                'clinicId'   => $document->clinicId(),
            ]);
        }
    }
}
