<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Console;

use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ValueObject\Money;
use App\System\Taxation\Application\Query\ResolveTax\ResolveTax;
use App\System\Taxation\Domain\ValueObject\AnimalUsage;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxApplication;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:taxation:resolve-test', description: 'Test tax resolution')]
final class ResolveTaxCommand extends Command
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Tax category code')
            ->addOption('regime', null, InputOption::VALUE_REQUIRED, 'Regime ID')
            ->addOption('net', null, InputOption::VALUE_REQUIRED, 'Net amount in minor units')
            ->addOption('currency', null, InputOption::VALUE_REQUIRED, 'Currency code')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country code (ISO 3166)')
            ->addOption('animal-usage', null, InputOption::VALUE_OPTIONAL, 'Animal usage (companion/livestock/equine)')
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Sale date (Y-m-d)', date('Y-m-d'))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $regimeRaw */
        $regimeRaw = $input->getOption('regime');
        /** @var string $categoryRaw */
        $categoryRaw = $input->getOption('category');
        /** @var string $currencyRaw */
        $currencyRaw = $input->getOption('currency');
        /** @var string $netRaw */
        $netRaw = $input->getOption('net');
        /** @var string $countryRaw */
        $countryRaw = $input->getOption('country');
        /** @var string $dateRaw */
        $dateRaw = $input->getOption('date');
        /** @var string|null $animalUsageStr */
        $animalUsageStr = $input->getOption('animal-usage');

        $regimeId    = TaxRegimeId::fromString($regimeRaw);
        $category    = TaxCategoryCode::fromString($categoryRaw);
        $currency    = CurrencyCode::fromString($currencyRaw);
        $netAmount   = Money::fromMinorUnits((int) $netRaw, $currency);
        $country     = CountryCode::fromString($countryRaw);
        $saleDate    = new \DateTimeImmutable($dateRaw);
        $animalUsage = null !== $animalUsageStr ? AnimalUsage::from($animalUsageStr) : null;

        $context = FiscalContext::of($country, $saleDate, animalUsage: $animalUsage);

        /** @var TaxApplication $result */
        $result = $this->queryBus->ask(new ResolveTax($netAmount, $category, $regimeId, $context));

        $output->writeln([
            \sprintf('Regime:    %s', $result->taxRegimeId()->toString()),
            \sprintf('Category:  %s', $result->taxCategoryCode()->toString()),
            \sprintf('Rate:      %s%%', $result->appliedRate()->value()->toPercentString()),
            \sprintf('Net:       %d minor units', $result->netAmount()->minorUnits()),
            \sprintf('Tax:       %d minor units', $result->taxAmount()->minorUnits()),
            \sprintf('Gross:     %d minor units', $result->grossAmount()->minorUnits()),
            \sprintf('Source:    %s', $result->appliedRate()->source()),
        ]);

        if (!$result->legalMentions()->isEmpty()) {
            $output->writeln('Mentions:');

            foreach ($result->legalMentions()->toArray() as $mention) {
                $output->writeln(\sprintf('  [%s] %s', $mention->code, $mention->text));
            }
        }

        return Command::SUCCESS;
    }
}
