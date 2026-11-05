<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListMedicalAlertsForAnimals;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The medical alerts of several animals of a clinic at once, as
 * ListMedicalAlertsForAnimal reads those of one: a page naming many animals
 * reads them in one query.
 */
final readonly class ListMedicalAlertsForAnimals implements QueryInterface
{
    /**
     * @param list<string> $animalIds UUID strings
     */
    public function __construct(
        public string $clinicId,
        public array $animalIds,
    ) {
    }
}
