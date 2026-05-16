<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\ImportSnapshot;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImporterInterface;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Snapshot;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ImportSnapshotHandler
{
    public function __construct(
        private readonly SnapshotRepositoryInterface $snapshotRepository,
        private readonly RegistryImporterInterface $registryImporter,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ImportSnapshot $command): string
    {
        $source     = ImportSource::from($command->source);
        $snapshotId = SnapshotId::fromString($this->uuidGenerator->generate());
        $now        = $this->clock->now();

        $snapshot = Snapshot::create($snapshotId, $source, $now);
        $this->snapshotRepository->save($snapshot);

        $snapshot->markAsRunning($now);
        $this->snapshotRepository->save($snapshot);

        $this->registryImporter->stage($snapshot, $command->filePath, $command->dictionaryPath);

        return $snapshotId->toString();
    }
}
