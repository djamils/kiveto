<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Console;

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
    name: 'app:demo:simulate-consumption',
    description: 'Simulate N days of stock consumption for a demo clinic. Delegates to Inventory BC if available.',
)]
final class DemoSimulateConsumptionCommand extends Command
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
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Number of days to simulate', 14)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $clinicId = $input->getOption('clinic');
        $daysRaw  = $input->getOption('days');

        if (!\is_string($clinicId) || '' === $clinicId) {
            $io->error('--clinic option is required.');

            return Command::FAILURE;
        }

        $days = is_numeric($daysRaw) ? (int) $daysRaw : 14;

        // Guard: check ConsumeStock exists in Inventory BC
        $commandClass = \App\Context\Inventory\Application\Command\ConsumeStock\ConsumeStock::class;

        if (!class_exists($commandClass)) {
            $io->warning('ConsumeStock command not found in Inventory BC. Skipping consumption simulation.');

            return Command::SUCCESS;
        }

        // Load stock items with on-hand quantity for this clinic
        $stockItems = $this->connection->fetchAllAssociative(
            'SELECT id, article_id, total_on_hand_amount, total_on_hand_unit FROM inventory__stock_items WHERE clinic_id = :clinicId AND track_stock = 1 AND CAST(total_on_hand_amount AS DECIMAL(20,6)) > 0 LIMIT 20',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        if ([] === $stockItems) {
            $io->warning('No tracked stock items with on-hand quantity found for this clinic.');

            return Command::SUCCESS;
        }

        $io->title(\sprintf('Simulating %d day(s) of consumption for %d stock item(s)', $days, \count($stockItems)));

        $totalConsumed = 0;
        $errors        = 0;
        $now           = new \DateTimeImmutable();

        for ($day = 0; $day < $days; ++$day) {
            $date = $now->modify(\sprintf('-%d day', $days - $day - 1));

            // Consume a random subset of items each day
            $itemsThisDay = \array_slice($stockItems, 0, random_int(1, min(5, \count($stockItems))));

            foreach ($itemsThisDay as $item) {
                $stockItemId = Uuid::fromBinary(RowAccessor::string($item, 'id'))->toRfc4122();
                $unit        = RowAccessor::string($item, 'total_on_hand_unit');

                // Consume a small random quantity (1–3 units)
                $quantity = (string) random_int(1, 3);

                try {
                    $this->commandBus->dispatch(new $commandClass(
                        stockItemId: $stockItemId,
                        clinicId: $clinicId,
                        quantityAmount: $quantity,
                        quantityUnit: $unit,
                        occurredAt: $date->format(\DateTimeInterface::ATOM),
                        reference: null,
                        performedBy: null,
                        note: 'Demo simulation',
                        reason: 'CONSUMPTION_CONSULTATION',
                    ));
                    ++$totalConsumed;
                } catch (\Throwable $e) {
                    ++$errors;
                    // Skip individual consumption errors gracefully
                }
            }
        }

        $io->success(\sprintf(
            'Simulated %d day(s) of consumption. Consumption events dispatched: %d. Errors: %d.',
            $days,
            $totalConsumed,
            $errors,
        ));

        return Command::SUCCESS;
    }
}
