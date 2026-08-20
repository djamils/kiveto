<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListMedicalAlertsForAnimal implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
    ) {
    }
}
