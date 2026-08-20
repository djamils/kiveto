<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * Access to the clinic's catalog of acts and medications, and to the active
 * substances behind a medication (used by the prescription allergy check).
 */
interface CatalogItemProviderInterface
{
    /**
     * @return list<CatalogItemDto>
     */
    public function search(string $term, string $clinicId, int $limit = 20): array;

    /**
     * Returns null when the item does not exist for that clinic.
     */
    public function detail(string $itemType, string $itemId, string $clinicId): ?CatalogItemDto;

    /**
     * Price to snapshot, resolved against the clinic's default price list.
     *
     * Takes the already-loaded item so callers never pay for a second lookup;
     * falls back to the item's base price when the clinic has no price list.
     */
    public function resolvePrice(CatalogItemDto $item, string $clinicId): CatalogPriceDto;

    /**
     * Active substance labels of a drug article, empty when the article carries
     * no marketing-authorization reference.
     *
     * @return list<string>
     */
    public function activeSubstances(string $articleId, string $clinicId): array;
}
