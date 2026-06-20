<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ListSuppliers;

use App\Context\Procurement\Application\Port\SupplierReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListSuppliersHandler
{
    public function __construct(
        private SupplierReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(ListSuppliers $query): array
    {
        return $this->readRepository->findAll();
    }
}
