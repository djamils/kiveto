<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/** The per-phone quotas of the one-time codes (`limiter.portal_otp_phone*`). */
interface PortalOtpQuotasInterface
{
    /** Forgets the phone's counters: nothing keyed by the number is kept. */
    public function forgetPhone(string $phone): void;
}
