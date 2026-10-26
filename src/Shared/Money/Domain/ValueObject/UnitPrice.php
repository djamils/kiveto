<?php

declare(strict_types=1);

namespace App\Shared\Money\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;

/**
 * A unit price carried at six decimals, as integer micro-units of the
 * currency unit (1 EUR = 1 000 000 micro-units).
 *
 * Money stops at the currency's minor unit, which is too coarse for a unit
 * price derived from a price including tax: 10,00 € TTC at 20 % is
 * 8,333333 € before tax, and an EN16931 invoice must print it that way so that
 * quantity × unit price gives back the line amount. Six decimals is the
 * precision the standard allows for BT-146.
 *
 * No float is ever involved: decimals are parsed from strings and divided
 * with bcmath, rounded half to even like every accounting amount.
 */
final class UnitPrice
{
    private const int SCALE = 6;

    private const int WORKING_SCALE = 12;

    private int $microUnits;
    private CurrencyCode $currency;

    private function __construct(int $microUnits, CurrencyCode $currency)
    {
        $this->microUnits = $microUnits;
        $this->currency   = $currency;
    }

    public static function fromMicroUnits(int $microUnits, CurrencyCode $currency): self
    {
        return new self($microUnits, $currency);
    }

    public static function fromMoney(Money $money, CurrencyDecimals $decimals): self
    {
        return new self($money->minorUnits() * self::microUnitsPerMinorUnit($decimals), $money->currency());
    }

    public static function fromDecimalString(string $decimal, CurrencyCode $currency): self
    {
        if (1 !== preg_match('/^-?\d+(\.\d{1,6})?$/', $decimal)) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid unit price "%s": expected a decimal with at most %d places.',
                $decimal,
                self::SCALE,
            ));
        }

        /** @var numeric-string $decimal */
        $microUnits = bcmul($decimal, (string) (10 ** self::SCALE), 0);

        return new self((int) $microUnits, $currency);
    }

    /**
     * The price before tax of a unit sold at $grossUnit tax included:
     * gross / (1 + rate), to six decimals.
     */
    public static function netOfTax(Money $grossUnit, string $ratePercent, CurrencyDecimals $decimals): self
    {
        if (1 !== preg_match('/^\d+(\.\d{1,4})?$/', $ratePercent)) {
            throw new \InvalidArgumentException(\sprintf('Invalid tax rate "%s".', $ratePercent));
        }

        $gross   = (string) ($grossUnit->minorUnits() * self::microUnitsPerMinorUnit($decimals));
        $divisor = bcadd('1', bcdiv($ratePercent, '100', self::WORKING_SCALE), self::WORKING_SCALE);

        return new self(
            (int) bcround(bcdiv($gross, $divisor, self::WORKING_SCALE), 0, \RoundingMode::HalfEven),
            $grossUnit->currency(),
        );
    }

    public function microUnits(): int
    {
        return $this->microUnits;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    /**
     * The price as a decimal string with exactly $scale places, rounded half
     * to even when fewer than six are asked for.
     */
    public function toDecimalString(int $scale = self::SCALE): string
    {
        if ($scale < 0 || $scale > self::SCALE) {
            throw new \InvalidArgumentException(\sprintf('Scale must be between 0 and %d, got %d.', self::SCALE, $scale));
        }

        $decimal = bcdiv((string) $this->microUnits, (string) (10 ** self::SCALE), self::SCALE);

        return bcround($decimal, $scale, \RoundingMode::HalfEven);
    }

    public function equals(self $other): bool
    {
        return $this->microUnits === $other->microUnits
            && $this->currency->equals($other->currency);
    }

    private static function microUnitsPerMinorUnit(CurrencyDecimals $decimals): int
    {
        return 10 ** (self::SCALE - $decimals->value());
    }
}
