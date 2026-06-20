<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\GetPurchaseOrderDetail;

use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPurchaseOrderDetailHandler
{
    public function __construct(
        private PurchaseOrderReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetPurchaseOrderDetail $query): array
    {
        $result = $this->readRepository->findById($query->purchaseOrderId);

        if (null === $result) {
            throw new PurchaseOrderNotFoundException($query->purchaseOrderId);
        }

        return $result;
    }
}
