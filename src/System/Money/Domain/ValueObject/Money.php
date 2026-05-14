<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;

/**
 * Immutable monetary amount expressed as integer minor units (e.g. 1850 = 18.50 EUR).
 *
 * All arithmetic goes through bcmath: no float is ever used.
 * Storing amounts as minor units eliminates rounding errors at the source.
 */
final class Money
{
    private int $minorUnits;
    private CurrencyCode $currency;

    private function __construct(int $minorUnits, CurrencyCode $currency)
    {
        $this->minorUnits = $minorUnits;
        $this->currency   = $currency;
    }

    public static function fromMinorUnits(int $minorUnits, CurrencyCode $currency): self
    {
        return new self($minorUnits, $currency);
    }

    public static function zero(CurrencyCode $currency): self
    {
        return new self(0, $currency);
    }

    public static function fromDecimalString(string $decimal, CurrencyCode $currency, CurrencyDecimals $decimals): self
    {
        if (1 !== preg_match('/^-?\d+(\.\d+)?$/', $decimal)) {
            throw new \InvalidArgumentException(\sprintf('Invalid decimal string: "%s".', $decimal));
        }

        $scale          = $decimals->value();
        $parts          = explode('.', $decimal);
        $fractionalPart = $parts[1] ?? '';

        if (mb_strlen($fractionalPart) > $scale) {
            throw new \InvalidArgumentException(\sprintf(
                'Decimal "%s" has more decimal places than allowed (%d) for this currency.',
                $decimal,
                $scale,
            ));
        }

        /** @var numeric-string $decimal */
        $multiplier = (string) (10 ** $scale);
        $result     = bcmul($decimal, $multiplier, 0);

        return new self((int) $result, $currency);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function toDecimalString(CurrencyDecimals $decimals): string
    {
        $divisor = (string) (10 ** $decimals->value());

        return bcdiv((string) $this->minorUnits, $divisor, $decimals->value());
    }

    public function isZero(): bool
    {
        return 0 === $this->minorUnits;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency->equals($other->currency);
    }
}
