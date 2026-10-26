<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Quota;

/**
 * The thresholds each clinic crossed per quota month.
 */
interface SmsThresholdRepositoryInterface
{
    /**
     * Records the crossing if it is the first one of that month.
     *
     * @param string $month YYYY-MM (Europe/Paris)
     *
     * @return bool whether it was the first crossing (false: already announced)
     */
    public function markCrossed(string $clinicId, string $month, SmsThreshold $threshold, \DateTimeImmutable $at): bool;
}
