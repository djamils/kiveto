<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\ResolveTax;

use App\System\Taxation\Domain\Exception\CurrencyMismatchForRegimeException;
use App\System\Taxation\Domain\Service\TaxResolver;
use App\System\Taxation\Domain\ValueObject\TaxableItem;
use App\System\Taxation\Domain\ValueObject\TaxApplication;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ResolveTaxHandler
{
    public function __construct(private TaxResolver $taxResolver)
    {
    }

    public function __invoke(ResolveTax $query): TaxApplication
    {
        $expected = $this->deriveExpectedCurrency($query->regimeId);

        if ($query->netAmount->currency()->toString() !== $expected) {
            throw new CurrencyMismatchForRegimeException(
                $query->regimeId->toString(),
                $expected,
                $query->netAmount->currency()->toString(),
            );
        }

        $item = TaxableItem::of($query->netAmount, $query->categoryCode);

        return $this->taxResolver->resolve($item, $query->regimeId, $query->fiscalContext);
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
