<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The weights entered on the animal file (Animal context). */
interface AnimalWeightsInterface
{
    /** The most recent weight record of the animal, or null. */
    public function latestOf(string $clinicId, string $animalId): ?RecordedWeight;
}
