<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ListPendingReceipts;

use App\Context\Procurement\Application\Port\SupplierReceiptReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPendingReceiptsHandler
{
    public function __construct(
        private SupplierReceiptReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(ListPendingReceipts $query): array
    {
        return $this->readRepository->findPending($query->clinicId);
    }
}
