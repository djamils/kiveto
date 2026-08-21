<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

interface TaxRateProviderInterface
{
    /**
     * Effective VAT rate for a tax category, as a percentage (e.g. 20.0).
     *
     * Returns null when no rate can be determined — the caller then treats the
     * lines of that category as untaxed rather than guessing a rate.
     */
    public function effectiveRatePercent(string $taxCategoryCode, string $clinicId): ?float;
}
