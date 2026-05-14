<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Money\Application\Command\RegisterCurrency\RegisterCurrency;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Seeds the currency catalogue from currencies.yaml via RegisterCurrency commands.
 *
 * Idempotent: safe to run multiple times; existing entries are updated silently,
 * no duplicate records are created.
 */
#[AsCommand(
    name: 'app:money:load-currencies',
    description: 'Loads the canonical currency list from currencies.yaml into the database (idempotent).',
)]
final class LoadCurrenciesCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->projectDir . '/src/System/Money/Infrastructure/Resources/currencies.yaml';
        $data = Yaml::parseFile($file);

        \assert(\is_array($data) && \is_array($data['currencies']));

        foreach ($data['currencies'] as $code => $entry) {
            \assert(\is_string($code));
            \assert(\is_array($entry));
            \assert(\is_string($entry['symbol']));
            \assert(\is_int($entry['decimals']));
            \assert(\is_string($entry['displayName']));

            $this->commandBus->dispatch(new RegisterCurrency(
                code: $code,
                symbol: $entry['symbol'],
                decimals: $entry['decimals'],
                displayName: $entry['displayName'],
            ));
        }

        $output->writeln(\sprintf('<info>Loaded %d currencies.</info>', \count($data['currencies'])));

        return Command::SUCCESS;
    }
}
