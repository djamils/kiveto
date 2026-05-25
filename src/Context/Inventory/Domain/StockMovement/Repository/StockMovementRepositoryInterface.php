<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockMovement\Repository;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId;

interface StockMovementRepositoryInterface
{
    public function save(StockMovement $movement): void;

    public function findById(StockMovementId $id): ?StockMovement;

    /**
     * @return list<StockMovement>
     */
    public function findByClinic(
        ClinicId $clinicId,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        ?MovementType $type,
        ?MovementReason $reason,
        int $limit,
        int $offset,
    ): array;
}
