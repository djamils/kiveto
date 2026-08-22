<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListArticleIdsInStateHandler
{
    public function __construct(private StockOverviewReadRepositoryInterface $stock)
    {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListArticleIdsInState $query): array
    {
        return $this->stock->articleIdsInState(
            ClinicId::fromString($query->clinicId),
            $query->state,
            $query->expiringWithinDays,
        );
    }
}
