<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalPreventiveCare;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListAnimalPreventiveCare implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
    ) {
    }
}
