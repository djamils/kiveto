<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\ListStockItemsByClinic;

use App\Context\Inventory\Application\Port\StockItemReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ListStockItemsByClinicHandler
{
    public function __construct(
        private readonly StockItemReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<StockItemSummaryView>
     */
    public function __invoke(ListStockItemsByClinic $query): array
    {
        $status = null !== $query->status ? StockItemStatus::from($query->status) : null;

        return $this->readRepository->findByClinicAndStatus(
            ClinicId::fromString($query->clinicId),
            $status,
            $query->limit,
            $query->offset,
        );
    }
}
