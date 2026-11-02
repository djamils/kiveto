<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeleteUserDataExports;

use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Idempotent: a retry finds nothing left. An archive stored by a build
 * still running at that moment is deleted when its outcome is recorded
 * (RecordDataExportHandler, waiting on the row lock, then finds no export).
 */
#[AsMessageHandler]
final readonly class DeleteUserDataExportsHandler
{
    public function __construct(
        private DataExportRepositoryInterface $exports,
        private FileStorageInterface $storage,
    ) {
    }

    public function __invoke(DeleteUserDataExports $command): void
    {
        foreach ($this->exports->findByUserForUpdate($command->userId) as $export) {
            $this->storage->delete($export->storageKey());
            $this->exports->remove($export);
        }
    }
}
