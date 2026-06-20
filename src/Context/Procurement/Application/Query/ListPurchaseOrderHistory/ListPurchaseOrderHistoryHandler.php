<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ListPurchaseOrderHistory;

use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPurchaseOrderHistoryHandler
{
    public function __construct(
        private PurchaseOrderReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(ListPurchaseOrderHistory $query): array
    {
        return $this->readRepository->findByClinic($query->clinicId);
    }
}
