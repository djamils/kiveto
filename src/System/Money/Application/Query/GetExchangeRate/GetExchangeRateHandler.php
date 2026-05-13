<?php

declare(strict_types=1);

namespace App\System\Money\Application\Query\GetExchangeRate;

use App\System\Money\Domain\Exception\ExchangeRateNotFoundException;
use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\Repository\ExchangeRateRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetExchangeRateHandler
{
    public function __construct(private ExchangeRateRepository $exchangeRateRepository)
    {
    }

    public function __invoke(GetExchangeRate $query): ExchangeRate
    {
        $rate = $this->exchangeRateRepository->findRateAt($query->pair, $query->date);

        if (null === $rate) {
            throw new ExchangeRateNotFoundException(
                $query->pair->from()->toString(),
                $query->pair->to()->toString(),
                $query->date->format('Y-m-d'),
            );
        }

        return $rate;
    }
}
