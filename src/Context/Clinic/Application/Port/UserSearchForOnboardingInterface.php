<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

interface UserSearchForOnboardingInterface
{
    /**
     * Searches users by email fragment. Enforces a minimum fragment length of 2 characters.
     *
     * @return list<UserSearchResultItem>
     */
    public function searchByEmail(string $emailFragment, int $limit): array;
}
