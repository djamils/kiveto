<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** How the clinic's practitioners are named (Clinic context). */
interface PractitionerNamesInterface
{
    /**
     * The printed name (« Dr Prénom Nom »), the one online booking lists
     * the practitioners with; a user without a known name is left out.
     *
     * @param list<string> $userIds
     *
     * @return array<string, string> printed name by user id
     */
    public function namesOf(string $clinicId, array $userIds): array;
}
