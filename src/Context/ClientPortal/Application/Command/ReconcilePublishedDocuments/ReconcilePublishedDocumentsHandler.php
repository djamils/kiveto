<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ReconcilePublishedDocuments;

use App\Context\ClientPortal\Application\Port\DocumentFileJobsInterface;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Touching the row moves it out of the window, so a second run within 10
 * minutes does not ask twice. The render failures themselves are counted
 * by StorePublishedDocumentFile (failed after 5). A document still waiting
 * for a link whereas its client has a confirmed one (published while the
 * link was being confirmed, so missed by ReleaseAwaitingDocuments) is
 * released here. Rows are locked; one held by another writer waits for the
 * next run.
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

        $before = $now->modify(self::STALE_AFTER);

        foreach ($this->documents->findStalePendingFilesForUpdate($before, self::BATCH) as $document) {
            $retried += $this->request($document, $document->requeueFile($now));
        }

        foreach ($this->documents->findStaleReleasableForUpdate($before, self::BATCH) as $document) {
            $retried += $this->request($document, $document->linkConfirmed($now));
        }

        return $retried;
    }

    private function request(PublishedDocument $document, ?int $version): int
    {
        $this->documents->save($document);

        if (null === $version) {
            return 0;
        }

        $this->jobs->storeFile($document->id()->toString(), $version);

        return 1;
    }
}
