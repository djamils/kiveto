<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetStockItem;

use App\Context\Inventory\Application\Port\StockItemReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetStockItemHandler
{
    public function __construct(
        private readonly StockItemReadRepositoryInterface $readRepository,
    ) {
    }

    public function __invoke(GetStockItem $query): StockItemView
    {
        $view = $this->readRepository->findById(
            StockItemId::fromString($query->stockItemId),
            ClinicId::fromString($query->clinicId),
        );

        if (null === $view) {
            throw new StockItemNotFoundException($query->stockItemId);
        }

        return $view;
    }
}
