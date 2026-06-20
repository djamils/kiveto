<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:demo:bootstrap-clinic',
    description: 'Bootstrap a demo clinic. Dispatches CreateClinic from the Clinic BC if available.',
)]
final class DemoBootstrapClinicCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Clinic name', 'Clinique Demo')
            ->addOption('country', null, InputOption::VALUE_OPTIONAL, 'ISO-3166-1 alpha-2 country code', 'FR')
            ->addOption('currency', null, InputOption::VALUE_OPTIONAL, 'ISO-4217 currency code', 'EUR')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $nameOpt  = $input->getOption('name');
        $country  = $input->getOption('country');
        $currency = $input->getOption('currency');

        $name     = \is_string($nameOpt) && '' !== $nameOpt ? $nameOpt : 'Clinique Demo';
        $country  = \is_string($country) && '' !== $country ? strtoupper($country) : 'FR';
        $currency = \is_string($currency) && '' !== $currency ? strtoupper($currency) : 'EUR';

        // Guard: check the cross-BC command exists before dispatching
        $commandClass = \App\Context\Clinic\Application\Command\Clinic\CreateClinic\CreateClinic::class;

        if (!class_exists($commandClass)) {
            $io->warning('CreateClinic command not found in Clinic BC. Skipping clinic creation.');

            return Command::SUCCESS;
        }

        $slug     = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? 'demo');
        $clinicId = Uuid::v7()->toRfc4122();

        try {
            $this->commandBus->dispatch(new $commandClass(
                name: $name,
                slug: $slug . '-' . substr($clinicId, 0, 8),
                timeZone: 'Europe/Paris',
                locale: 'fr',
                countryCode: $country,
                currencyCode: $currency,
            ));
        } catch (\Throwable $e) {
            $io->error(\sprintf('Failed to create clinic: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Demo clinic "%s" bootstrapped.', $name));
        $io->text(json_encode(['clinicId' => $clinicId], \JSON_PRETTY_PRINT) ?: '{}');

        return Command::SUCCESS;
    }
}
