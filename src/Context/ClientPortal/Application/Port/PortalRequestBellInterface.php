<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The VetApp bell (Notification context): one entry per request, never owner free text. */
interface PortalRequestBellInterface
{
    /**
     * @param list<string> $userIds
     */
    public function announce(string $clinicId, array $userIds, string $requestId, string $title, string $detail): void;
}
