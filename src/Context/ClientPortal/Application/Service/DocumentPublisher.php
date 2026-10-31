<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\DocumentFileJobsInterface;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Shared\Application\Event\DomainEventPublisher;

/**
 * The steps every publication shares: whether the addressee can see it
 * (a confirmed link), the insert that keeps the first row of a source, and
 * the file request once a link exists.
 */
final readonly class DocumentPublisher
{
    public function __construct(
        private PublishedDocumentRepositoryInterface $documents,
        private ClientLinkRepositoryInterface $links,
        private DocumentFileJobsInterface $jobs,
        private DomainEventPublisher $eventPublisher,
    ) {
    }

    public function hasConfirmedLink(string $clinicId, string $clientId): bool
    {
        foreach ($this->links->findByClient($clinicId, $clientId) as $link) {
            if ($link->isConfirmed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inserts the document unless its source is already registered (an
     * earlier delivery or a withdrawal tombstone wins). Returns whether it
     * was inserted.
     */
    public function insert(PublishedDocument $document): bool
    {
        if (!$this->documents->insertIfAbsent($document)) {
            return false;
        }

        $this->requestFile($document, $document->pendingFileVersion());
        $this->eventPublisher->publish($document);

        return true;
    }

    public function save(PublishedDocument $document, ?int $requestedVersion): void
    {
        $this->documents->save($document);
        $this->requestFile($document, $requestedVersion);
        $this->eventPublisher->publish($document);
    }

    private function requestFile(PublishedDocument $document, ?int $version): void
    {
        if (null !== $version) {
            $this->jobs->storeFile($document->id()->toString(), $version);
        }
    }
}
