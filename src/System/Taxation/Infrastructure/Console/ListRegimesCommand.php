<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Console;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Taxation\Application\Query\ListSupportedRegimes\ListSupportedRegimes;
use App\System\Taxation\Domain\TaxRegime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:taxation:list-regimes', description: 'List supported tax regimes')]
final class ListRegimesCommand extends Command
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<TaxRegime> $regimes */
        $regimes = $this->queryBus->ask(new ListSupportedRegimes());

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Country', 'Rates', 'Status']);

        foreach ($regimes as $regime) {
            $table->addRow([
                $regime->id()->toString(),
                $regime->name(),
                $regime->country()->toString(),
                \count($regime->rates()),
                $regime->isActive() ? 'active' : 'inactive',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
