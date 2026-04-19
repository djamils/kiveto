<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;

interface PlanningBlockRepositoryInterface
{
    public function save(PlanningBlock $block): void;

    public function findById(PlanningBlockId $id): ?PlanningBlock;

    public function delete(PlanningBlock $block): void;
}
