<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport;

use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycle;
use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycleHandler;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFileLogInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RequestRegistryImportHandler
{
    public function __construct(
        private RegistryImportFilesInterface $files,
        private RegistryImportFileLogInterface $log,
        private RunFullImportCycleHandler $cycle,
        private ClockInterface $clock,
    ) {
    }

    /**
     * A failed import keeps its files where they are, for another try.
     *
     * @return bool whether an import ran
     */
    public function __invoke(RequestRegistryImport $command): bool
    {
        $waiting = $this->files->current($command->source);

        // None, or the very files of the last import that went through.
        if (null === $waiting || $waiting['fileHash'] === $this->log->lastAppliedRegistryHash($command->source)) {
            return false;
        }

        $snapshotId = $this->cycle->handle(new RunFullImportCycle($command->source, $waiting['file'], $waiting['dictionary']));
        $kept       = $this->files->archive($command->source, $this->clock->now());
        $this->log->record($snapshotId, $command->source, RegistryImportFile::pair($waiting, $kept, $command->requestedBy));

        return true;
    }
}
