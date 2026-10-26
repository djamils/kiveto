<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

interface TaxResolverInterface
{
    /**
     * The VAT rate, nature and legal mentions of one tax category for one
     * clinic; null when the category cannot be resolved (unknown category,
     * clinic regime not configured…). Amounts are the invoice's business.
     */
    public function resolve(string $clinicId, ?string $taxCategoryCode, string $currency): ?ResolvedTaxDto;
}
