<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

/**
 * How the snapshotted unit price was entered in the Catalog: all taxes
 * included or excluding tax. Consultation keeps it as is and never converts
 * the amount; the VAT rate is resolved at billing time.
 */
enum UnitPriceMode: string
{
    case INCLUDING_TAX = 'incl';
    case EXCLUDING_TAX = 'excl';
}
