<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

use App\Context\ClientPortal\Domain\Exception\PortalRequestRateLimitedException;

/**
 * `limiter.portal_requests`: 10 change or renewal requests per 24 hours per
 * account and clinic, kept outside any transaction. The Portal API consumes
 * a token before dispatching a submit command.
 */
interface PortalRequestQuotaInterface
{
    /** @throws PortalRequestRateLimitedException when the account used its 10 requests in that clinic */
    public function consume(string $userId, string $clinicId): void;
}
