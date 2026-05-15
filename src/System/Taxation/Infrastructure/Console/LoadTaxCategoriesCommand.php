<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Taxation\Application\Command\RegisterTaxCategory\RegisterTaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'app:taxation:load-categories', description: 'Load tax categories from YAML')]
final class LoadTaxCategoriesCommand extends Command
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
        $path = $this->projectDir . '/src/System/Taxation/Infrastructure/Resources/categories.yaml';

        /** @var array{categories: list<array{code: string, displayName: string, description: string|null}>} $data */
        $data  = Yaml::parseFile($path);
        $count = 0;

        foreach ($data['categories'] as $cat) {
            $this->commandBus->dispatch(new RegisterTaxCategory(
                TaxCategoryCode::fromString($cat['code']),
                $cat['displayName'],
                $cat['description'] ?? null,
            ));
            ++$count;
        }

        $output->writeln(\sprintf('Loaded %d categories.', $count));

        return Command::SUCCESS;
    }
}
