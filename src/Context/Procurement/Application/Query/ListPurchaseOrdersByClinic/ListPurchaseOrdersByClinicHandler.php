<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ListPurchaseOrdersByClinic;

use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPurchaseOrdersByClinicHandler
{
    public function __construct(
        private PurchaseOrderReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(ListPurchaseOrdersByClinic $query): array
    {
        return $this->readRepository->findByClinic($query->clinicId);
    }
}
