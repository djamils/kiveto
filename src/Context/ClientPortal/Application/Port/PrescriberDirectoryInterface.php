<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Who in the clinic is told of a new Portal request (Clinic context). */
interface PrescriberDirectoryInterface
{
    /**
     * The veterinarians of the clinic whose membership is in force and
     * whose account is active.
     *
     * @return list<string> user ids
     */
    public function veterinarians(string $clinicId): array;

    /**
     * The members in force, account active, holding `MANAGE_ANIMALS`.
     *
     * @return list<string> user ids
     */
    public function animalManagers(string $clinicId): array;
}
