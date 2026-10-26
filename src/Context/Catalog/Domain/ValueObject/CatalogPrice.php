<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\ValueObject;

use App\Context\Catalog\Domain\Exception\InvalidPriceEntryModeException;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * A catalogue price exactly as the clinic entered it: the amount (integer
 * minor units) and whether it includes tax. The amount is never converted.
 */
final class CatalogPrice
{
    private Money $amount;
    private PriceEntryMode $mode;

    private function __construct(Money $amount, PriceEntryMode $mode)
    {
        $this->amount = $amount;
        $this->mode   = $mode;
    }

    public static function of(Money $amount, PriceEntryMode $mode): self
    {
        return new self($amount, $mode);
    }

    public static function includingTax(Money $amount): self
    {
        return new self($amount, PriceEntryMode::INCLUDING_TAX);
    }

    public static function excludingTax(Money $amount): self
    {
        return new self($amount, PriceEntryMode::EXCLUDING_TAX);
    }

    public static function fromPrimitives(int $minorUnits, string $currency, string $mode): self
    {
        $entryMode = PriceEntryMode::tryFrom($mode);

        if (null === $entryMode) {
            throw new InvalidPriceEntryModeException($mode);
        }

        return new self(Money::fromMinorUnits($minorUnits, CurrencyCode::fromString($currency)), $entryMode);
    }

    /** The same mode, another amount — e.g. after a price rule adjusted it. */
    public function withAmount(Money $amount): self
    {
        return new self($amount, $this->mode);
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function mode(): PriceEntryMode
    {
        return $this->mode;
    }

    public function minorUnits(): int
    {
        return $this->amount->minorUnits();
    }

    public function currency(): CurrencyCode
    {
        return $this->amount->currency();
    }

    public function equals(self $other): bool
    {
        return $this->mode === $other->mode
            && $this->amount->equals($other->amount);
    }
}
