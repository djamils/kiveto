<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * The catalogue articles the desk may sell without a visit — food, parasite
 * treatments, accessories: active articles that need no prescription — with
 * the clinic's price for each.
 */
interface CounterCatalogInterface
{
    /**
     * @return list<CounterArticleDto>
     */
    public function search(string $clinicId, string $term, int $limit): array;

    /**
     * Null when the article is unknown, archived or prescription-only.
     */
    public function article(string $clinicId, string $articleId): ?CounterArticleDto;

    /**
     * The active prescription-only articles matching the term: the desk
     * points to the ordonnance they are dispensed from instead of selling them.
     *
     * @return list<PrescriptionOnlyArticleDto>
     */
    public function prescriptionOnly(string $clinicId, string $term, int $limit): array;
}
