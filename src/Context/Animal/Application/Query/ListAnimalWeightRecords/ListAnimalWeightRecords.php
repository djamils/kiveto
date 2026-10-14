<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalWeightRecords;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListAnimalWeightRecords implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
    ) {
    }
}
