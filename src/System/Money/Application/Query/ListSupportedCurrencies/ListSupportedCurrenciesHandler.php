<?php

declare(strict_types=1);

namespace App\System\Money\Application\Query\ListSupportedCurrencies;

use App\System\Money\Domain\Service\CurrencyRegistry;
use App\System\Money\Domain\ValueObject\Currency;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListSupportedCurrenciesHandler
{
    public function __construct(private CurrencyRegistry $currencyRegistry)
    {
    }

    /** @return list<Currency> */
    public function __invoke(ListSupportedCurrencies $query): array
    {
        return $this->currencyRegistry->listAll();
    }
}
