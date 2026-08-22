<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

interface TaxResolverInterface
{
    /**
     * Splits a net amount into net + tax for one VAT category of one clinic.
     */
    public function resolve(
        int $netMinorUnits,
        string $currency,
        ?string $taxCategoryCode,
        string $clinicId,
    ): ResolvedTaxDto;
}
