<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

/**
 * The clinic's current members whom the stock concerns (Clinic's staff).
 */
interface ClinicStaffInterface
{
    /**
     * @return list<string> user ids of the VETERINARY_ASSISTANT members
     */
    public function assistants(string $clinicId): array;

    /**
     * @return list<string> user ids of the clinic administrators
     */
    public function administrators(string $clinicId): array;
}
