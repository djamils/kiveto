<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ReleaseAwaitingDocuments;

use App\Context\ClientPortal\Application\Port\DocumentFileJobsInterface;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReleaseAwaitingDocumentsHandler
{
    public const int BATCH = 100;

    public function __construct(
        private PublishedDocumentRepositoryInterface $documents,
        private DocumentFileJobsInterface $jobs,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ReleaseAwaitingDocuments $command): void
    {
        $now = $this->clock->now();

        do {
            $batch = $this->documents->findAwaitingLink($command->clinicId, $command->clientId, self::BATCH);

            foreach ($batch as $document) {
                $version = $document->linkConfirmed($now);
                $this->documents->save($document);

                if (null !== $version) {
                    $this->jobs->storeFile($document->id()->toString(), $version);
                }
            }
        } while (self::BATCH === \count($batch));
    }
}
