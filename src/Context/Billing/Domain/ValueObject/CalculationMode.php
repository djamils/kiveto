<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * How an invoice turns entered prices into net, VAT and gross amounts.
 *
 * - EXCLUDING_TAX_FIRST (professional buyers): EN16931 strict — line nets are
 *   quantity × unit net, VAT is computed once per rate on the base. These are
 *   the invoices an approved platform validates.
 * - INCLUDING_TAX_FIRST (private buyers): the client pays exactly the prices
 *   displayed — the total incl. VAT per rate is exact, the base and the line
 *   nets are derived from it.
 */
enum CalculationMode: string
{
    case EXCLUDING_TAX_FIRST = 'excl_first';
    case INCLUDING_TAX_FIRST = 'incl_first';
}
