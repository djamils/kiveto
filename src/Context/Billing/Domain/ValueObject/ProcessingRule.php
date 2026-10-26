<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * Which French e-invoicing rule a document falls under: an invoice to a
 * business subject to French VAT is transmitted as an electronic invoice
 * (B2B); anything else is reported in totals (B2C, e-reporting).
 */
enum ProcessingRule: string
{
    case B2B = 'B2B';
    case B2C = 'B2C';

    public static function forPayer(Payer $payer): self
    {
        return $payer->isFrenchBusiness() ? self::B2B : self::B2C;
    }

    /**
     * Professionals are invoiced tax excluded first (EN16931 strict); private
     * clients pay exactly the prices displayed.
     */
    public function calculationMode(): CalculationMode
    {
        return match ($this) {
            self::B2B => CalculationMode::EXCLUDING_TAX_FIRST,
            self::B2C => CalculationMode::INCLUDING_TAX_FIRST,
        };
    }
}
