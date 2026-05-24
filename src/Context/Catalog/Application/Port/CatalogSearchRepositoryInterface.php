<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Port;

use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Domain\ValueObject\ClinicId;

interface CatalogSearchRepositoryInterface
{
    /** @return list<CatalogSearchResult> */
    public function search(string $term, ClinicId $clinicId, int $limit): array;
}
