<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\GetEffectiveRateForUI;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\Service\TaxResolver;
use App\System\Taxation\Domain\ValueObject\TaxableItem;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetEffectiveRateForUIHandler
{
    public function __construct(private TaxResolver $taxResolver)
    {
    }

    public function __invoke(GetEffectiveRateForUI $query): EffectiveRateResult
    {
        $currency  = CurrencyCode::fromString($this->deriveExpectedCurrency($query->regimeId));
        $netAmount = Money::zero($currency);
        $item      = TaxableItem::of($netAmount, $query->categoryCode);

        $application = $this->taxResolver->resolve($item, $query->regimeId, $query->fiscalContext);

        return new EffectiveRateResult(
            ratePercent: $application->appliedRate()->value()->toPercentString(),
            regimeId: $query->regimeId->toString(),
        );
    }

    private function deriveExpectedCurrency(TaxRegimeId $regimeId): string
    {
        return match (true) {
            \in_array($regimeId->toString(), ['FR', 'BE', 'ES', 'DE'], true) => 'EUR',
            \in_array($regimeId->toString(), ['CH'], true)                   => 'CHF',
            \in_array($regimeId->toString(), ['UK', 'GB'], true)             => 'GBP',
            default                                                          => 'EUR',
        };
    }
}
