<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Twig;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Service\CurrencyRegistry;

/**
 * Formats an amount expressed in integer minor units for display.
 *
 * Usage:
 *   {{ 1480|money('EUR') }}        → 14,80 €
 *   {{ 123456|money('EUR') }}      → 1 234,56 €
 *
 * Symbol and minor-unit count come from the shared CurrencyRegistry; unknown
 * currency codes fall back to the raw code with 2 decimals. French-style
 * formatting: comma decimal separator, narrow no-break space as thousands
 * separator, currency symbol suffixed with a no-break space.
 * Mirrors the JS fmtMoney() helper in assets/js/money.js.
 */
final class MoneyFormatRuntime
{
    private const string THOUSANDS_SEPARATOR = "\u{202F}";
    private const string NO_BREAK_SPACE      = "\u{00A0}";

    public function __construct(
        private readonly CurrencyRegistry $currencyRegistry,
    ) {
    }

    public function moneyFormat(mixed $minorUnits, string $currency = 'EUR'): string
    {
        if (!\is_int($minorUnits)) {
            return '';
        }

        $symbol   = $currency;
        $decimals = 2;

        try {
            $code = CurrencyCode::fromString($currency);

            if ($this->currencyRegistry->has($code)) {
                $currencyData = $this->currencyRegistry->get($code);
                $symbol       = $currencyData->symbol()->toString();
                $decimals     = $currencyData->decimals()->value();
            }
        } catch (\InvalidArgumentException) {
            // Malformed code: keep the raw string as symbol, 2 decimals
        }

        $negative = $minorUnits < 0;
        $absolute = abs($minorUnits);
        $divisor  = 10 ** $decimals;

        $integerPart    = number_format(intdiv($absolute, $divisor), 0, ',', self::THOUSANDS_SEPARATOR);
        $fractionalPart = $decimals > 0
            ? ',' . str_pad((string) ($absolute % $divisor), $decimals, '0', \STR_PAD_LEFT)
            : '';

        return ($negative ? '-' : '') . $integerPart . $fractionalPart . self::NO_BREAK_SPACE . $symbol;
    }
}
