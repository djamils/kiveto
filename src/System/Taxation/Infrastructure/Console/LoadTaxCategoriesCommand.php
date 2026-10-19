<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Taxation\Infrastructure\Catalogue\YamlTaxCategoryCatalogue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:taxation:load-categories', description: 'Load tax categories from YAML')]
final class LoadTaxCategoriesCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly YamlTaxCategoryCatalogue $catalogue,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;

        foreach ($this->catalogue->commands() as $command) {
            $this->commandBus->dispatch($command);
            ++$count;
        }

        $output->writeln(\sprintf('Loaded %d categories.', $count));

        return Command::SUCCESS;
    }
}
