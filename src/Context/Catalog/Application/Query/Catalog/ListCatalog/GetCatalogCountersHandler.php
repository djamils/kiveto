<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

use App\Context\Catalog\Application\Port\CatalogListReadRepositoryInterface;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCatalogCountersHandler
{
    public function __construct(private CatalogListReadRepositoryInterface $catalog)
    {
    }

    public function __invoke(GetCatalogCounters $query): CatalogCounters
    {
        return $this->catalog->counters(ClinicId::fromString($query->clinicId));
    }
}
