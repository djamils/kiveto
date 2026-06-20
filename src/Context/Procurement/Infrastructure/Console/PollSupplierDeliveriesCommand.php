<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Application\Command\PollSupplierDeliveries\PollSupplierDeliveries;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:procurement:poll-deliveries',
    description: 'Poll suppliers for new deliveries. Runs for every AUTOMATIC or SIMULATION supplier.',
)]
final class PollSupplierDeliveriesCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly SupplierRepositoryInterface $supplierRepository,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Max deliveries per supplier account per run', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $limitRaw  = $input->getOption('limit');
        $limit     = is_numeric($limitRaw) ? (int) $limitRaw : 100;
        $suppliers = $this->supplierRepository->findAll();

        $processed = 0;
        $errors    = 0;

        foreach ($suppliers as $supplier) {
            // Only poll suppliers that support automated delivery polling
            $supportedModes = [SupplierIntegrationMode::AUTOMATIC, SupplierIntegrationMode::SIMULATION];
            if (!\in_array($supplier->integrationMode(), $supportedModes, true)) {
                continue;
            }

            // Find all ACTIVE accounts for this supplier via raw DBAL
            $accountRows = $this->connection->fetchAllAssociative(
                'SELECT id FROM procurement__supplier_accounts WHERE supplier_id = :supplierId AND status = :status',
                [
                    'supplierId' => Uuid::fromString($supplier->id()->toString())->toBinary(),
                    'status'     => 'ACTIVE',
                ],
            );

            if ([] === $accountRows) {
                continue;
            }

            foreach ($accountRows as $accountRow) {
                $accountId = Uuid::fromBinary(RowAccessor::string($accountRow, 'id'))->toRfc4122();

                try {
                    $this->commandBus->dispatch(new PollSupplierDeliveries(
                        supplierId: $supplier->id()->toString(),
                        supplierAccountId: $accountId,
                        limit: $limit,
                    ));
                    ++$processed;
                } catch (\Throwable $e) {
                    ++$errors;
                    $io->error(\sprintf(
                        'Failed to poll supplier "%s" / account "%s": %s',
                        $supplier->code()->toString(),
                        $accountId,
                        $e->getMessage(),
                    ));
                }
            }
        }

        $io->success(\sprintf('Polled %d supplier account(s). Errors: %d.', $processed, $errors));

        return 0 === $errors ? Command::SUCCESS : Command::FAILURE;
    }
}
