<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile;

use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Only the Portal's own files: Billing's durable invoice PDFs (and any
 * other context's files) are never deleted from here.
 */
#[AsMessageHandler]
final readonly class DeleteStoredDocumentFileHandler
{
    public const string OWN_PREFIX = 'client_portal/';

    public function __construct(private FileStorageInterface $storage)
    {
    }

    public function __invoke(DeleteStoredDocumentFile $command): void
    {
        if (!str_starts_with($command->storageKey, self::OWN_PREFIX) || str_contains($command->storageKey, '..')) {
            throw new \LogicException(\sprintf('Refusing to delete "%s": not a Client Portal file.', $command->storageKey));
        }

        $this->storage->delete($command->storageKey);
    }
}
