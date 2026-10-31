<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ReconcilePublishedDocuments;

use App\Context\ClientPortal\Application\Port\DocumentFileJobsInterface;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Touching the row moves it out of the window, so a second run within 10
 * minutes does not ask twice. The render failures themselves are counted
 * by StorePublishedDocumentFile (failed after 5).
 */
#[AsMessageHandler]
final readonly class ReconcilePublishedDocumentsHandler
{
    public const string STALE_AFTER = '-10 minutes';
    public const int    BATCH       = 100;

    public function __construct(
        private PublishedDocumentRepositoryInterface $documents,
        private DocumentFileJobsInterface $jobs,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ReconcilePublishedDocuments $command): int
    {
        $now     = $this->clock->now();
        $retried = 0;

        foreach ($this->documents->findStalePendingFiles($now->modify(self::STALE_AFTER), self::BATCH) as $document) {
            $version = $document->requeueFile($now);
            $this->documents->save($document);

            if (null !== $version) {
                $this->jobs->storeFile($document->id()->toString(), $version);
                ++$retried;
            }
        }

        return $retried;
    }
}
