<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * Archives the animal registered on the Portal for a booking that was
 * declined or expired (S4g), through the Animal context.
 */
interface BookedPetArchiverInterface
{
    /**
     * Archives the animal unless it has a clinical record (a patient linked
     * to it) or is no longer active; does nothing for an unknown animal.
     */
    public function archiveUnlessRecorded(string $clinicId, string $animalId): void;
}
