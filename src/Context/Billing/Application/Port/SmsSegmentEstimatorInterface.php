<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * How many SMS segments a reminder SMS of the clinic would take, rendered as
 * Notification would send it.
 */
interface SmsSegmentEstimatorInterface
{
    /**
     * @param array<string, string> $parameters the INVOICE_REMINDER SMS parameters
     */
    public function reminderSegments(string $clinicId, array $parameters): int;
}
