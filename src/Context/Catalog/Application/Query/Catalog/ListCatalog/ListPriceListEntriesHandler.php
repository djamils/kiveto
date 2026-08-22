<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

use App\Context\Catalog\Application\Port\CatalogListReadRepositoryInterface;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPriceListEntriesHandler
{
    public function __construct(private CatalogListReadRepositoryInterface $catalog)
    {
    }

    /**
     * @return list<PriceListEntryRow>
     */
    public function __invoke(ListPriceListEntries $query): array
    {
        return $this->catalog->priceListEntries(
            ClinicId::fromString($query->clinicId),
            $query->priceListId,
        );
    }
}
