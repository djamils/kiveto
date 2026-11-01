<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * The VetApp bell (Notification context) for a « Rattachement » request:
 * one entry per acceptance, opening the clinic's request of that account.
 */
interface LinkRequestBellInterface
{
    /**
     * @param list<string> $userIds the staff told
     */
    public function announce(string $clinicId, array $userIds, string $ownerUserId, string $key, string $title, string $detail): void;
}
