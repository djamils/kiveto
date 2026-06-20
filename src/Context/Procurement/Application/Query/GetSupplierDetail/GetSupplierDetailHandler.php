<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\GetSupplierDetail;

use App\Context\Procurement\Application\Port\SupplierReadRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetSupplierDetailHandler
{
    public function __construct(
        private SupplierReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetSupplierDetail $query): array
    {
        $result = $this->readRepository->findById($query->supplierId);

        if (null === $result) {
            throw new SupplierNotFoundException($query->supplierId);
        }

        return $result;
    }
}
