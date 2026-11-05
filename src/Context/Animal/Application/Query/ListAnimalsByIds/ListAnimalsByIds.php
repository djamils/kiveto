<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalsByIds;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Several animals of a clinic at once, as GetAnimalById reads one: a page
 * naming many animals reads them in one query instead of one per animal.
 */
final readonly class ListAnimalsByIds implements QueryInterface
{
    /**
     * @param list<string> $animalIds UUID strings; duplicates are read once
     */
    public function __construct(
        public string $clinicId,
        public array $animalIds,
    ) {
    }
}
