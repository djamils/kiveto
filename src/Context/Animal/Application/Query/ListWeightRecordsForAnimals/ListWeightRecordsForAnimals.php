<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListWeightRecordsForAnimals;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The weighings of several animals of a clinic at once, as
 * ListAnimalWeightRecords reads those of one: a page naming many animals
 * reads them in one query.
 */
final readonly class ListWeightRecordsForAnimals implements QueryInterface
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
