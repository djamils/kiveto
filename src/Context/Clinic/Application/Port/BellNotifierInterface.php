<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

/**
 * Posts an entry in the bell of some members (Notification owns the bell).
 * The idempotency key makes a replay harmless.
 */
interface BellNotifierInterface
{
    /**
     * @param list<string> $userIds
     * @param string       $route   a route of the clinic application, without parameters
     */
    public function post(string $clinicId, array $userIds, string $kind, string $title, string $detail, string $route, string $idempotencyKey): void;
}
