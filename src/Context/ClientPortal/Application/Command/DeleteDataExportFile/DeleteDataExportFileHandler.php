<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeleteDataExportFile;

use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Idempotent: an export already expired or removed (and so locked out) changes nothing. */
#[AsMessageHandler]
final readonly class DeleteDataExportFileHandler
{
    public function __construct(
        private DataExportRepositoryInterface $exports,
        private FileStorageInterface $storage,
    ) {
    }

    public function __invoke(DeleteDataExportFile $command): void
    {
        $export = $this->exports->findByIdForUpdate($command->exportId);

        if (null === $export || !$export->expire()) {
            return;
        }

        $this->storage->delete($export->storageKey());
        $this->exports->update($export);
    }
}
