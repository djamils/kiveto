<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems;

use App\Context\Catalog\Application\Port\CatalogSearchRepositoryInterface;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchCatalogItemsHandler
{
    public function __construct(
        private CatalogSearchRepositoryInterface $searchRepository,
    ) {
    }

    /** @return list<CatalogSearchResult> */
    public function __invoke(SearchCatalogItems $query): array
    {
        return $this->searchRepository->search(
            $query->term,
            ClinicId::fromString($query->clinicId),
            $query->limit,
        );
    }
}
