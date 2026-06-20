<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\GetStaleOpenPurchaseOrders;

use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetStaleOpenPurchaseOrdersHandler
{
    public function __construct(
        private PurchaseOrderReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(GetStaleOpenPurchaseOrders $query): array
    {
        return $this->readRepository->findStale($query->daysThreshold);
    }
}
