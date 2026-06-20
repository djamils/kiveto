<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Application\Command\CreateSupplierAccount\CreateSupplierAccount;
use App\Context\Procurement\Application\Command\ImportSupplierCatalog\ImportSupplierCatalog;
use App\Context\Procurement\Application\Command\RegisterSupplier\RegisterSupplier;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:demo:register-suppliers',
    description: 'Register demo suppliers (CENTRAVET, ALCYON, HIPPOCAMPE) and link accounts to a clinic.',
)]
final class DemoRegisterSuppliersCommand extends Command
{
    /**
     * Demo suppliers: code => [name, type, integrationMode, adapterIdentifier].
     *
     * @var array<string, array{name: string, type: string, integrationMode: string, adapterIdentifier: string|null}>
     */
    private const array DEMO_SUPPLIERS = [
        'CENTRAVET'  => ['name' => 'Centravet', 'type' => 'DISTRIBUTOR', 'integrationMode' => 'SIMULATION', 'adapterIdentifier' => null],
        'ALCYON'     => ['name' => 'Alcyon', 'type' => 'DISTRIBUTOR', 'integrationMode' => 'SIMULATION', 'adapterIdentifier' => null],
        'HIPPOCAMPE' => ['name' => 'Hippocampe', 'type' => 'DISTRIBUTOR', 'integrationMode' => 'MANUAL_EXPORT', 'adapterIdentifier' => null],
    ];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly SupplierRepositoryInterface $supplierRepository,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('clinic', null, InputOption::VALUE_REQUIRED, 'Clinic UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $clinicId = $input->getOption('clinic');

        if (!\is_string($clinicId) || '' === $clinicId) {
            $io->error('--clinic option is required.');

            return Command::FAILURE;
        }

        $io->title('Registering demo suppliers');
        $clinicBinary = Uuid::fromString($clinicId)->toBinary();

        foreach (self::DEMO_SUPPLIERS as $code => $spec) {
            $io->section($code);

            // Register supplier if it does not exist yet
            $existingSupplier = $this->supplierRepository->findByCode(SupplierCode::fromString($code));

            if (null === $existingSupplier) {
                try {
                    $this->commandBus->dispatch(new RegisterSupplier(
                        name: $spec['name'],
                        code: $code,
                        type: $spec['type'],
                        countryCode: 'FR',
                        defaultCurrency: 'EUR',
                        integrationMode: $spec['integrationMode'],
                        adapterIdentifier: $spec['adapterIdentifier'],
                    ));
                    $io->text(\sprintf(' [OK] Supplier "%s" registered.', $code));
                } catch (\Throwable $e) {
                    $io->error(\sprintf('Failed to register supplier "%s": %s', $code, $e->getMessage()));
                    continue;
                }

                // Reload after registration
                $existingSupplier = $this->supplierRepository->findByCode(SupplierCode::fromString($code));
            } else {
                $io->text(\sprintf(' [SKIP] Supplier "%s" already exists.', $code));
            }

            if (null === $existingSupplier) {
                $io->error(\sprintf('Supplier "%s" not found after registration.', $code));
                continue;
            }

            $supplierId     = $existingSupplier->id()->toString();
            $supplierBinary = Uuid::fromString($supplierId)->toBinary();

            // Create supplier account for this clinic if it does not exist yet
            $accountRow = $this->connection->fetchAssociative(
                'SELECT id FROM procurement__supplier_accounts WHERE clinic_id = :clinicId AND supplier_id = :supplierId LIMIT 1',
                ['clinicId' => $clinicBinary, 'supplierId' => $supplierBinary],
            );

            if (false === $accountRow) {
                try {
                    $this->commandBus->dispatch(new CreateSupplierAccount(
                        clinicId: $clinicId,
                        supplierId: $supplierId,
                        customerCode: 'DEMO-' . $code,
                        notes: 'Demo account created by bootstrap command.',
                    ));
                    $io->text(\sprintf(' [OK] Supplier account for "%s" created.', $code));
                } catch (\Throwable $e) {
                    $io->error(\sprintf('Failed to create account for "%s": %s', $code, $e->getMessage()));
                }
            } else {
                $io->text(\sprintf(' [SKIP] Account for "%s" already exists.', $code));
            }

            // Import catalog entries from the configured adapter
            try {
                $this->commandBus->dispatch(new ImportSupplierCatalog(supplierId: $supplierId));
                $io->text(\sprintf(' [OK] Catalog imported for "%s".', $code));
            } catch (\Throwable $e) {
                $io->warning(\sprintf('Catalog import skipped for "%s": %s', $code, $e->getMessage()));
            }
        }

        $io->success('Demo suppliers registered.');

        return Command::SUCCESS;
    }
}
