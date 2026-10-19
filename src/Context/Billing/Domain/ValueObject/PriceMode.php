<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * Whether a unit price was entered tax included or tax excluded.
 *
 * Kept with the line rather than converted on entry: the amount the clinic
 * typed is the one it promised the client, and the calculation mode decides
 * how the other side is derived from it.
 */
enum PriceMode: string
{
    case INCLUDING_TAX = 'incl';
    case EXCLUDING_TAX = 'excl';
}
