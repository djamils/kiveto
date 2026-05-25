<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\SearchStockMovements;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockMovement\Repository\StockMovementRepositoryInterface;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SearchStockMovementsHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<StockMovementView>
     */
    public function __invoke(SearchStockMovements $query): array
    {
        $from   = null !== $query->from ? new \DateTimeImmutable($query->from) : null;
        $to     = null !== $query->to ? new \DateTimeImmutable($query->to) : null;
        $type   = null !== $query->type ? MovementType::from($query->type) : null;
        $reason = null !== $query->reason ? MovementReason::from($query->reason) : null;

        $movements = $this->repository->findByClinic(
            ClinicId::fromString($query->clinicId),
            $from,
            $to,
            $type,
            $reason,
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
