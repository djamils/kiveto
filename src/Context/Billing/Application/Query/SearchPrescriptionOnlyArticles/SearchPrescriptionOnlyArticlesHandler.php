<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchPrescriptionOnlyArticles;

use App\Context\Billing\Application\Port\CounterCatalogInterface;
use App\Context\Billing\Application\Port\PrescriptionOnlyArticleDto;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Only a typed search — two characters at least — brings them up: the
 * counter's list does not offer medicines it cannot sell.
 */
#[AsMessageHandler]
final readonly class SearchPrescriptionOnlyArticlesHandler
{
    private const int MIN_TERM = 2;

    public function __construct(private CounterCatalogInterface $catalog)
    {
    }

    /**
     * @return list<PrescriptionOnlyArticleDto>
     */
    public function __invoke(SearchPrescriptionOnlyArticles $query): array
    {
        $term = mb_trim($query->term);

        if (mb_strlen($term) < self::MIN_TERM) {
            return [];
        }

        return $this->catalog->prescriptionOnly($query->clinicId, $term, max(1, min(20, $query->limit)));
    }
}
