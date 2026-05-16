<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle;

use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Application\Command\ApplySnapshotDiff\ApplySnapshotDiff;
use App\System\PharmaceuticalRegistry\Application\Command\ApplySnapshotDiff\ApplySnapshotDiffHandler;
use App\System\PharmaceuticalRegistry\Application\Command\CalculateSnapshotDiff\CalculateSnapshotDiff;
use App\System\PharmaceuticalRegistry\Application\Command\CalculateSnapshotDiff\CalculateSnapshotDiffHandler;
use App\System\PharmaceuticalRegistry\Application\Command\ImportSnapshot\ImportSnapshot;
use App\System\PharmaceuticalRegistry\Application\Command\ImportSnapshot\ImportSnapshotHandler;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;

/**
 * NOT AsMessageHandler — called directly as a service from FullImportCycleCommand.
 * This avoids wrapping all sub-commands in a single doctrine_transaction middleware transaction,
 * which would break per-step recovery (if Apply fails, Import's RUNNING status would be rolled back).
 */
final class RunFullImportCycleHandler
{
    public function __construct(
        private readonly ImportSnapshotHandler $importSnapshotHandler,
        private readonly CalculateSnapshotDiffHandler $calculateSnapshotDiffHandler,
        private readonly ApplySnapshotDiffHandler $applySnapshotDiffHandler,
        private readonly SnapshotRepositoryInterface $snapshotRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function handle(RunFullImportCycle $command): void
    {
        $snapshotId = ($this->importSnapshotHandler)(new ImportSnapshot(
            source: $command->source,
            filePath: $command->filePath,
            dictionaryPath: $command->dictionaryPath,
        ));

        try {
            ($this->calculateSnapshotDiffHandler)(new CalculateSnapshotDiff(snapshotId: $snapshotId));
            ($this->applySnapshotDiffHandler)(new ApplySnapshotDiff(snapshotId: $snapshotId));
        } catch (\Throwable $e) {
            $snapshot = $this->snapshotRepository->findById(SnapshotId::fromString($snapshotId));

            if (null !== $snapshot) {
                $snapshot->markAsFailed($e->getMessage(), $this->clock->now());
                $this->snapshotRepository->save($snapshot);
            }

            throw $e;
        }
    }
}
