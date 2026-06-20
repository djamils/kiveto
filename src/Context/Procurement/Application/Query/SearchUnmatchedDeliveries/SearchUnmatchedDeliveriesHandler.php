<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\SearchUnmatchedDeliveries;

use App\Context\Procurement\Application\Port\SupplierReceiptReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchUnmatchedDeliveriesHandler
{
    public function __construct(
        private SupplierReceiptReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(SearchUnmatchedDeliveries $query): array
    {
        return $this->readRepository->findUnmatched($query->clinicId);
    }
}
