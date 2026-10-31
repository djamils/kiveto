<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AttachPublishedDocumentFile;

use App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile\StorePublishedDocumentFileHandler;
use App\Context\ClientPortal\Application\Port\DocumentFileJobsInterface;
use App\Context\ClientPortal\Domain\Document\DocumentReadRepositoryInterface;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A new version (re-share) is unread again for everyone; the file it
 * replaces is deleted later, so that a URL signed before the swap still
 * serves it for its five minutes.
 */
#[AsMessageHandler]
final readonly class AttachPublishedDocumentFileHandler
{
    public function __construct(
        private PublishedDocumentRepositoryInterface $documents,
        private DocumentReadRepositoryInterface $reads,
        private DocumentFileJobsInterface $jobs,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AttachPublishedDocumentFile $command): void
    {
        $document = $this->documents->findByIdForUpdate($command->documentId);

        if (null === $document) {
            return;
        }

        if (!$document->expectsFile($command->version)) {
            // Superseded (a newer share, a withdrawal) or already attached: a
            // file of ours that is not the served one is never served.
            if (str_starts_with($command->storageKey, StorePublishedDocumentFileHandler::KEY_PREFIX) && $command->storageKey !== $document->storageKey()) {
                $this->jobs->deleteFileLater($command->storageKey);
            }

            return;
        }

        $previousKey = $document->attachFile($command->version, $command->storageKey, $this->clock->now());

        $this->documents->save($document);

        if ($command->version >= 2) {
            $this->reads->forgetReads($command->documentId);
        }

        if (null !== $previousKey && str_starts_with($previousKey, StorePublishedDocumentFileHandler::KEY_PREFIX)) {
            $this->jobs->deleteFileLater($previousKey);
        }
    }
}
