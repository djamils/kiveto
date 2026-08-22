<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListMovementsHandler
{
    public function __construct(private StockOverviewReadRepositoryInterface $stock)
    {
    }

    public function __invoke(ListMovements $query): MovementPage
    {
        $result = $this->stock->movements(ClinicId::fromString($query->clinicId), $query->criteria);

        return new MovementPage(
            items: $result['items'],
            total: $result['total'],
            page: $query->criteria->page,
            perPage: $query->criteria->perPage,
        );
    }
}
