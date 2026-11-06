<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport;

use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycle;
use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycleHandler;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RequestRegistryImportHandler
{
    public function __construct(
        private RegistryImportFilesInterface $files,
        private RunFullImportCycleHandler $cycle,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return bool whether an import ran
     */
    public function __invoke(RequestRegistryImport $command): bool
    {
        $files = $this->files->current($command->source);

        if (null === $files) {
            return false;
        }

        $this->cycle->handle(new RunFullImportCycle($command->source, $files['file'], $files['dictionary']));
        $this->files->archive($command->source, $this->clock->now());

        return true;
    }
}
