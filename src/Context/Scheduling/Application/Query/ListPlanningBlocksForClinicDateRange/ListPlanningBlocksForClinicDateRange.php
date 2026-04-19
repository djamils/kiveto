<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListPlanningBlocksForClinicDateRange implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $fromDate,
        public string $toDate,
    ) {
    }
}
