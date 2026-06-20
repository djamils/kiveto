<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Application\Command\AddPurchaseOrderLine\AddPurchaseOrderLine;
use App\Context\Procurement\Application\Command\CreatePurchaseOrder\CreatePurchaseOrder;
use App\Context\Procurement\Application\Command\PollSupplierDeliveries\PollSupplierDeliveries;
use App\Context\Procurement\Application\Command\SubmitPurchaseOrder\SubmitPurchaseOrder;
use App\Context\Procurement\Application\Command\ValidateSupplierReceipt\ValidateSupplierReceipt;
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
    name: 'app:demo:simulate-purchase-orders',
    description: 'Simulate purchase orders end-to-end for a demo clinic: create → add lines → submit → poll → validate.',
)]
final class DemoSimulatePurchaseOrdersCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clinic', null, InputOption::VALUE_REQUIRED, 'Clinic UUID')
            ->addOption('count', null, InputOption::VALUE_OPTIONAL, 'Number of purchase orders to simulate', 5)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $clinicId = $input->getOption('clinic');
        $countRaw = $input->getOption('count');

        if (!\is_string($clinicId) || '' === $clinicId) {
            $io->error('--clinic option is required.');

            return Command::FAILURE;
        }

        $count        = is_numeric($countRaw) ? (int) $countRaw : 5;
        $clinicBinary = Uuid::fromString($clinicId)->toBinary();

        // Load supplier accounts for this clinic
        $accountRows = $this->connection->fetchAllAssociative(
            "SELECT sa.id AS account_id, sa.supplier_id FROM procurement__supplier_accounts sa WHERE sa.clinic_id = :clinicId AND sa.status = 'ACTIVE' LIMIT 10",
            ['clinicId' => $clinicBinary],
        );

        if ([] === $accountRows) {
            $io->warning('No active supplier accounts found for this clinic. Run app:demo:register-suppliers first.');

            return Command::SUCCESS;
        }

        $io->title(\sprintf('Simulating %d purchase order(s)', $count));

        $validated = 0;
        $errors    = 0;

        for ($i = 0; $i < $count; ++$i) {
            // Round-robin over available accounts
            $accountRow        = $accountRows[$i % \count($accountRows)];
            $supplierAccountId = Uuid::fromBinary(RowAccessor::string($accountRow, 'account_id'))->toRfc4122();
            $supplierId        = Uuid::fromBinary(RowAccessor::string($accountRow, 'supplier_id'))->toRfc4122();

            // Load a few active catalog entries for this supplier
            $catalogEntries = $this->connection->fetchAllAssociative(
                "SELECT id, catalog_price_minor, catalog_price_currency FROM procurement__supplier_catalog_entries WHERE supplier_id = :supplierId AND status = 'ACTIVE' LIMIT 5",
                ['supplierId' => Uuid::fromString($supplierId)->toBinary()],
            );

            if ([] === $catalogEntries) {
                $io->warning(\sprintf('No catalog entries for supplier %s. Skipping PO #%d.', $supplierId, $i + 1));
                continue;
            }

            try {
                // 1. Create PO
                $poId = Uuid::v7()->toRfc4122();
                $this->commandBus->dispatch(new CreatePurchaseOrder(
                    clinicId: $clinicId,
                    supplierId: $supplierId,
                    supplierAccountId: $supplierAccountId,
                    deliveryAddress: [],
                ));

                // Retrieve the most recently created PO for this clinic/supplier
                $poRow = $this->connection->fetchAssociative(
                    'SELECT id FROM procurement__purchase_orders WHERE clinic_id = :clinicId AND supplier_id = :supplierId ORDER BY created_at DESC LIMIT 1',
                    [
                        'clinicId'   => $clinicBinary,
                        'supplierId' => Uuid::fromString($supplierId)->toBinary(),
                    ],
                );

                if (false === $poRow) {
                    $io->error(\sprintf('Could not retrieve created PO #%d.', $i + 1));
                    ++$errors;
                    continue;
                }

                $poId = Uuid::fromBinary(RowAccessor::string($poRow, 'id'))->toRfc4122();

                // 2. Add lines (take 1–3 entries)
                $lineCount = min(3, \count($catalogEntries));

                for ($j = 0; $j < $lineCount; ++$j) {
                    $entry      = $catalogEntries[$j];
                    $entryId    = Uuid::fromBinary(RowAccessor::string($entry, 'id'))->toRfc4122();
                    $priceMinor = RowAccessor::int($entry, 'catalog_price_minor');
                    $currency   = RowAccessor::string($entry, 'catalog_price_currency');

                    // Use a dummy article id (same as catalog entry id for simulation purposes)
                    $this->commandBus->dispatch(new AddPurchaseOrderLine(
                        purchaseOrderId: $poId,
                        clinicId: $clinicId,
                        articleId: $entryId,
                        catalogEntryId: $entryId,
                        orderedAmount: (string) random_int(1, 10),
                        orderedUnit: 'unit',
                        unitPriceMinor: $priceMinor,
                        unitPriceCurrency: $currency,
                        note: null,
                    ));
                }

                // 3. Submit PO
                $this->commandBus->dispatch(new SubmitPurchaseOrder(
                    purchaseOrderId: $poId,
                    clinicId: $clinicId,
                ));

                // 4. Poll deliveries for SIMULATION suppliers (triggers simulated receipt creation)
                $this->commandBus->dispatch(new PollSupplierDeliveries(
                    supplierId: $supplierId,
                    supplierAccountId: $supplierAccountId,
                    limit: 10,
                ));

                // 5. Validate pending receipts for this PO
                $receiptRows = $this->connection->fetchAllAssociative(
                    "SELECT id FROM procurement__supplier_receipts WHERE clinic_id = :clinicId AND purchase_order_id = :poId AND status = 'PENDING_REVIEW'",
                    [
                        'clinicId' => $clinicBinary,
                        'poId'     => Uuid::fromString($poId)->toBinary(),
                    ],
                );

                foreach ($receiptRows as $receiptRow) {
                    $receiptId = Uuid::fromBinary(RowAccessor::string($receiptRow, 'id'))->toRfc4122();
                    $this->commandBus->dispatch(new ValidateSupplierReceipt(
                        receiptId: $receiptId,
                        clinicId: $clinicId,
                    ));
                    ++$validated;
                }

                $io->text(\sprintf(' [OK] PO #%d → %d receipt(s) validated.', $i + 1, \count($receiptRows)));
            } catch (\Throwable $e) {
                ++$errors;
                $io->error(\sprintf('PO #%d failed: %s', $i + 1, $e->getMessage()));
            }
        }

        $io->success(\sprintf('Simulated %d PO(s). Receipts validated: %d. Errors: %d.', $count, $validated, $errors));

        return 0 === $errors ? Command::SUCCESS : Command::FAILURE;
    }
}
