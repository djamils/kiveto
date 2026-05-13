<?php

declare(strict_types=1);

namespace App\System\Money\Application\Query\ConvertMoney;

use App\System\Money\Domain\Service\ConversionService;
use App\System\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ConvertMoneyHandler
{
    public function __construct(private ConversionService $conversionService)
    {
    }

    public function __invoke(ConvertMoney $query): Money
    {
        return $this->conversionService->convert(
            $query->amount,
            $query->targetCurrency,
            $query->date,
            $query->rounding,
        );
    }
}
