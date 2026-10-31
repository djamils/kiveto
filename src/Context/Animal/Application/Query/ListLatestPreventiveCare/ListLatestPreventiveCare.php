<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListLatestPreventiveCare;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The latest act of each preventive protocol `(animal, kind, lower(label))`
 * of the given animals, whether or not it carries a next due date.
 */
final readonly class ListLatestPreventiveCare implements QueryInterface
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
