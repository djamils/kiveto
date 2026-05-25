<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetStockMovementHistory;

use App\Context\Inventory\Application\Query\SearchStockMovements\StockMovementView;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockMovement\Repository\StockMovementRepositoryInterface;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetStockMovementHistoryHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<StockMovementView>
     */
    public function __invoke(GetStockMovementHistory $query): array
    {
        $from = null !== $query->from ? new \DateTimeImmutable($query->from) : null;
        $to   = null !== $query->to ? new \DateTimeImmutable($query->to) : null;

        $movements = $this->repository->findByClinic(
            ClinicId::fromString($query->clinicId),
            $from,
            $to,
            null,
            null,
            $query->limit,
            $query->offset,
        );

        return array_map(
            static fn (StockMovement $m): StockMovementView => new StockMovementView(
                id: $m->id()->toString(),
                clinicId: $m->clinicId()->toString(),
                articleId: $m->articleId()->toString(),
                lotId: $m->lotId()?->toString(),
                type: $m->type()->value,
                reason: $m->reason()->value,
                quantityAmount: $m->quantity()->amount(),
                quantityUnit: $m->quantity()->unit()->toString(),
                occurredAt: $m->occurredAt()->format(\DateTimeInterface::ATOM),
                reference: $m->reference(),
                performedBy: $m->performedBy(),
                note: $m->note(),
            ),
            $movements
        );
    }
}
