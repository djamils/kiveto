<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Application\Command\ImportSupplierCatalog\ImportSupplierCatalog;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:procurement:import-catalogs',
    description: 'Import supplier product catalogs from configured adapters.',
)]
final class ImportSupplierCatalogsCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly SupplierRepositoryInterface $supplierRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('supplier-code', null, InputOption::VALUE_OPTIONAL, 'Import only for a specific supplier code (e.g. CENTRAVET)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io           = new SymfonyStyle($input, $output);
        $supplierCode = $input->getOption('supplier-code');

        // Filter to a single supplier when --supplier-code is provided
        if (\is_string($supplierCode) && '' !== $supplierCode) {
            $supplier  = $this->supplierRepository->findByCode(SupplierCode::fromString(strtoupper($supplierCode)));
            $suppliers = null !== $supplier ? [$supplier] : [];
        } else {
            $suppliers = $this->supplierRepository->findAll();
        }

        if ([] === $suppliers) {
            $io->warning('No suppliers found matching the criteria.');

            return Command::SUCCESS;
        }

        $processed = 0;
        $errors    = 0;

        foreach ($suppliers as $supplier) {
            try {
                $this->commandBus->dispatch(new ImportSupplierCatalog(
                    supplierId: $supplier->id()->toString(),
                ));
                ++$processed;
                $io->text(\sprintf(' [OK] %s (%s)', $supplier->name()->toString(), $supplier->code()->toString()));
            } catch (\Throwable $e) {
                ++$errors;
                $io->error(\sprintf(
                    'Failed to import catalog for supplier "%s": %s',
                    $supplier->code()->toString(),
                    $e->getMessage(),
                ));
            }
        }

        $io->success(\sprintf('Imported catalogs for %d supplier(s). Errors: %d.', $processed, $errors));

        return 0 === $errors ? Command::SUCCESS : Command::FAILURE;
    }
}
