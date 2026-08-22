<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

interface PrescriberDirectoryInterface
{
    /**
     * The practitioner's signing identity in a clinic.
     *
     * Returns null when the user has no staff profile there — someone who
     * cannot be named on a prescription at all.
     */
    public function forUser(string $userId, string $clinicId): ?PrescriberSnapshotDto;
}
