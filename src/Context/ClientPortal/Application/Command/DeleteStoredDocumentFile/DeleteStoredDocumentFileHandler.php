<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile;

use App\Context\ClientPortal\Application\Port\BookletFileRepositoryInterface;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Only the Portal's own files: Billing's durable invoice PDFs (and any
 * other context's files) are never deleted from here. A key served again
 * by the time the delayed deletion runs (a booklet whose content went back
 * to an earlier state hashes to its earlier key) is kept.
 */
#[AsMessageHandler]
final readonly class DeleteStoredDocumentFileHandler
{
    public const string OWN_PREFIX = 'client_portal/';

    public function __construct(
        private FileStorageInterface $storage,
        private BookletFileRepositoryInterface $booklets,
        private PublishedDocumentRepositoryInterface $documents,
    ) {
    }

    public function __invoke(DeleteStoredDocumentFile $command): void
    {
        if (!str_starts_with($command->storageKey, self::OWN_PREFIX) || str_contains($command->storageKey, '..')) {
            throw new \LogicException(\sprintf('Refusing to delete "%s": not a Client Portal file.', $command->storageKey));
        }

        if ($this->booklets->isCurrentKey($command->storageKey) || $this->documents->isServedKey($command->storageKey)) {
            return;
        }

        $this->storage->delete($command->storageKey);
    }
}
