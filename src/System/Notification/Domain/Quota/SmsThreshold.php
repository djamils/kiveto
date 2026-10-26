<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Quota;

/**
 * The SMS usage levels a clinic is told about, once per quota month: 80 %
 * and 100 % of the quota, and the overage cap (quota + cap used: the
 * non-critical SMS are held back from then on).
 */
enum SmsThreshold: string
{
    case EIGHTY  = '80';
    case HUNDRED = '100';
    case CAP     = 'CAP';

    /**
     * @return list<self> the thresholds the billed usage has reached
     */
    public static function reachedBy(SmsUsage $usage, int $overageCap): array
    {
        return array_values(array_filter([
            $usage->percent() >= 80 ? self::EIGHTY : null,
            $usage->percent() >= 100 ? self::HUNDRED : null,
            $usage->used >= $usage->quota + $overageCap ? self::CAP : null,
        ]));
    }
}
