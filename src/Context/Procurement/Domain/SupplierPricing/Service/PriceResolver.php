<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierPricing\Service;

use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierPricing\Exception\NoEffectivePriceException;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\EffectivePrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingSource;

final class PriceResolver
{
    public function resolve(
        ?SupplierPricing $pricing,
        SupplierCatalogEntry $entry,
        \DateTimeImmutable $referenceDate,
    ): EffectivePrice {
        // Step 1: Use negotiated pricing when present and not yet expired
        if (null !== $pricing) {
            $expiresAt = $pricing->expiresAt();

            if (null === $expiresAt || $expiresAt > $referenceDate) {
                return new EffectivePrice(
                    amount: $pricing->negotiatedPrice()->amount,
                    source: SupplierPricingSource::NEGOTIATED,
                    resolvedAt: $referenceDate,
                );
            }
        }

        // Step 2: Fall back to catalog price when valid at the reference date
        if ($entry->catalogPrice()->isValidAt($referenceDate)) {
            return new EffectivePrice(
                amount: $entry->catalogPrice()->amount,
                source: SupplierPricingSource::CATALOG_DEFAULT,
                resolvedAt: $referenceDate,
            );
        }

        // Step 3: No price available for the requested date
        throw new NoEffectivePriceException($entry->id()->toString());
    }
}
