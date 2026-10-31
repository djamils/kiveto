<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** How the clinic's practitioners are named (Clinic context). */
interface PractitionerNamesInterface
{
    /**
     * In one lookup; a user without a known display name is left out.
     *
     * @param list<string> $userIds
     *
     * @return array<string, string> display name by user id
     */
    public function namesOf(string $clinicId, array $userIds): array;
}
