<?php

declare(strict_types=1);

namespace App\System\Money\Application\Command\ImportExchangeRates;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Money\Application\Port\ExchangeRateProvider;
use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\Repository\ExchangeRateRepository;
use App\System\Money\Domain\ValueObject\ExchangeRateId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ImportExchangeRatesHandler
{
    public function __construct(
        private ExchangeRateProvider $exchangeRateProvider,
        private ExchangeRateRepository $exchangeRateRepository,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ImportExchangeRates $command): void
    {
        $rates = $this->exchangeRateProvider->fetchSnapshot($command->date);
        $now   = $this->clock->now();

        foreach ($rates as $historicalRate) {
            $id           = ExchangeRateId::generate($this->uuidGenerator);
            $exchangeRate = ExchangeRate::create(
                $id,
                $historicalRate->pair(),
                $historicalRate->rate(),
                $historicalRate->effectiveDate(),
                $command->source,
                $now,
            );

            $this->exchangeRateRepository->save($exchangeRate);
            $this->domainEventPublisher->publish($exchangeRate);
        }
    }
}
