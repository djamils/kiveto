<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\DeleteMessageFiles;

use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A file already gone is fine (the storage deletes silently): a retried
 * run deletes the rest.
 */
#[AsMessageHandler]
final readonly class DeleteMessageFilesHandler
{
    public function __construct(private FileStorageInterface $storage)
    {
    }

    public function __invoke(DeleteMessageFiles $command): void
    {
        foreach ($command->storageKeys as $key) {
            $this->storage->delete($key);
        }
    }
}
