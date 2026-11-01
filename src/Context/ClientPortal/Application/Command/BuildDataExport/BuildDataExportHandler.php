<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\BuildDataExport;

use App\Context\ClientPortal\Application\Command\RecordDataExport\RecordDataExport;
use App\Context\ClientPortal\Application\Port\DataExportBuilderInterface;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Context\ClientPortal\Domain\Export\DataExportStatus;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A failed build is recorded and not retried: the export turns `failed`
 * and the account may ask again at once (quota refunded). A message
 * replayed after the outcome changes nothing.
 */
#[AsMessageHandler(bus: 'messenger.bus.external_api')]
final readonly class BuildDataExportHandler
{
    public function __construct(
        private DataExportRepositoryInterface $exports,
        private DataExportBuilderInterface $builder,
        private FileStorageInterface $storage,
        private CommandBusInterface $commandBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BuildDataExport $command): void
    {
        $export = $this->exports->findById($command->exportId);

        if (null === $export || DataExportStatus::PENDING !== $export->status()) {
            return;
        }

        try {
            $this->storage->storeAt($export->storageKey(), $this->builder->build($export->userId()));
        } catch (\Throwable $failure) {
            $this->logger->error('A data export could not be built.', ['exportId' => $command->exportId, 'exception' => $failure]);
            $this->commandBus->dispatch(new RecordDataExport($command->exportId, built: false));

            return;
        }

        $this->commandBus->dispatch(new RecordDataExport($command->exportId, built: true));
    }
}
