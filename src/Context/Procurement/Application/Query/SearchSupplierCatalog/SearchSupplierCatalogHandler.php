<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\SearchSupplierCatalog;

use App\Context\Procurement\Application\Port\SupplierCatalogReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchSupplierCatalogHandler
{
    public function __construct(
        private SupplierCatalogReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(SearchSupplierCatalog $query): array
    {
        return $this->readRepository->search($query->term, $query->supplierId);
    }
}
