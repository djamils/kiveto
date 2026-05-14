<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Service;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Exception\ExchangeRateNotFoundException;
use App\System\Money\Domain\Exception\StaleExchangeRateException;
use App\System\Money\Domain\Repository\ExchangeRateRepository;
use App\System\Money\Domain\RoundingPolicy\RoundingPolicy;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Domain\ValueObject\Money;

/**
 * Converts a monetary amount from one currency to another using historical exchange rates.
 *
 * The rate used is the most recent one whose effective date is ≤ the requested date.
 * If that rate is older than $maxStalenessInDays (default 7), StaleExchangeRateException
 * is thrown. Same-currency conversion returns the input amount immediately without
 * any repository lookup (same-currency short-circuit).
 */
final class ConversionService
{
    public function __construct(
        private readonly ExchangeRateRepository $exchangeRateRepository,
        private readonly CurrencyRegistry $currencyRegistry,
        private readonly int $maxStalenessInDays = 7,
    ) {
    }

    public function convert(
        Money $amount,
        CurrencyCode $targetCurrency,
        \DateTimeImmutable $date,
        RoundingPolicy $rounding,
    ): Money {
        if ($amount->currency()->equals($targetCurrency)) {
            return $amount;
        }

        $pair = ExchangeRatePair::of($amount->currency(), $targetCurrency);
        $rate = $this->exchangeRateRepository->findRateAt($pair, $date);

        if (null === $rate) {
            throw new ExchangeRateNotFoundException(
                $amount->currency()->toString(),
                $targetCurrency->toString(),
                $date->format('Y-m-d'),
            );
        }

        $rateDate  = $rate->effectiveDate()->setTime(0, 0, 0);
        $requested = $date->setTime(0, 0, 0);
        $diff      = $rateDate->diff($requested);
        $days      = (int) $diff->format('%r%a');

        if ($days > $this->maxStalenessInDays) {
            throw new StaleExchangeRateException(
                $amount->currency()->toString(),
                $targetCurrency->toString(),
                $rate->effectiveDate()->format('Y-m-d'),
                $date->format('Y-m-d'),
                $this->maxStalenessInDays,
            );
        }

        $targetCurrencyData = $this->currencyRegistry->get($targetCurrency);
        $decimals           = $targetCurrencyData->decimals();
        $scale              = $decimals->value() + 4;

        $fromDecimals = $this->currencyRegistry->get($amount->currency())->decimals();
        /** @var numeric-string $decimalAmount */
        $decimalAmount = bcdiv((string) $amount->minorUnits(), (string) (10 ** $fromDecimals->value()), $scale);

        /** @var numeric-string $rateStr */
        $rateStr = $rate->rate();
        /** @var numeric-string $converted */
        $converted = bcmul($decimalAmount, $rateStr, $scale);
        /** @var numeric-string $rounded */
        $rounded    = $rounding->round($converted, $targetCurrency, $decimals);
        $multiplier = (string) (10 ** $decimals->value());
        $minorUnits = (int) bcmul($rounded, $multiplier, 0);

        return Money::fromMinorUnits($minorUnits, $targetCurrency);
    }
}
