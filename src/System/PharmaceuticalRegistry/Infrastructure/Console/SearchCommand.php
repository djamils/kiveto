<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\MarketingAuthorizationSearchResult;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\SearchMarketingAuthorizations;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:pharmaceutical-registry:search', description: 'Search marketing authorizations')]
final class SearchCommand extends Command
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('term', InputArgument::REQUIRED, 'Search term');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $term */
        $term = $input->getArgument('term');

        /** @var MarketingAuthorizationSearchResult[] $results */
        $results = $this->queryBus->ask(new SearchMarketingAuthorizations(term: $term));

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Status', 'ATCvet', 'Holder']);

        foreach ($results as $result) {
            $table->addRow([
                $result->id,
                $result->commercialName,
                $result->status,
                $result->atcVetCode ?? '-',
                $result->holderLaboratory,
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
