<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\GetSmsUsage;

/**
 * SMS usage of a clinic for a quota month, in billed segments.
 */
final readonly class SmsUsageView
{
    /**
     * @param string $month              YYYY-MM (Europe/Paris)
     * @param int    $overageAmountCents overage × unit price, euro cents HT
     * @param int    $cap                overage cap (segments beyond the quota)
     * @param int    $percent            share of the quota used, whole percent (may exceed 100)
     * @param bool   $capReached         the sent and queued segments reach quota + cap: non-critical SMS are held back
     */
    public function __construct(
        public string $month,
        public int $quota,
        public int $used,
        public int $queued,
        public int $overage,
        public int $overageAmountCents,
        public int $cap,
        public int $percent,
        public bool $capReached,
    ) {
    }
}
