<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\ValueObject;

/**
 * How a catalogue price was entered: all taxes included (what a clinic
 * displays and types) or excluding tax. Catalog never converts one into the
 * other — the VAT rate is resolved at billing time by Taxation.
 */
enum PriceEntryMode: string
{
    case INCLUDING_TAX = 'incl';
    case EXCLUDING_TAX = 'excl';
}
