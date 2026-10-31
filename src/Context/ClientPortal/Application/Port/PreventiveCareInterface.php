<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Preventive care of the animals (Animal context). */
interface PreventiveCareInterface
{
    /**
     * The latest act of each protocol `(animal, kind, lower(label))`, with or
     * without a next due date.
     *
     * @param list<string> $animalIds
     *
     * @return list<PreventiveAct>
     */
    public function latestActs(string $clinicId, array $animalIds): array;
}
