<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * The name of an appointment's animal, as its messages print it.
 */
interface AnimalDirectoryInterface
{
    /** Null when the animal cannot be read in that clinic. */
    public function nameOf(string $clinicId, string $animalId): ?string;
}
