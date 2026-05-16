<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;

/**
 * Immutable reference entry for an ISO 4217 currency supported by the application.
 *
 * The currency catalogue is a static dictionary loaded from a YAML resource at boot;
 * entries are never mutated at runtime — refusing or restricting a currency for a
 * given clinic is a separate concern that belongs to its own bounded context.
 */
final class Currency
{
    private CurrencyCode $code;
    private CurrencySymbol $symbol;
    private CurrencyDecimals $decimals;
    private string $displayName;

    private function __construct(
        CurrencyCode $code,
        CurrencySymbol $symbol,
        CurrencyDecimals $decimals,
        string $displayName,
    ) {
        $this->code        = $code;
        $this->symbol      = $symbol;
        $this->decimals    = $decimals;
        $this->displayName = $displayName;
    }

    public static function of(
        CurrencyCode $code,
        CurrencySymbol $symbol,
        CurrencyDecimals $decimals,
        string $displayName,
    ): self {
        return new self($code, $symbol, $decimals, $displayName);
    }

    public function code(): CurrencyCode
    {
        return $this->code;
    }

    public function symbol(): CurrencySymbol
    {
        return $this->symbol;
    }

    public function decimals(): CurrencyDecimals
    {
        return $this->decimals;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function equals(self $other): bool
    {
        return $this->code->equals($other->code)
            && $this->symbol->equals($other->symbol)
            && $this->decimals->equals($other->decimals)
            && $this->displayName === $other->displayName;
    }
}
