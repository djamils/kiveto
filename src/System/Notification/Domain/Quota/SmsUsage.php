<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Quota;

/**
 * A clinic's SMS consumption for one quota month, in billed segments.
 */
final readonly class SmsUsage
{
    /**
     * @param string $month              YYYY-MM, Europe/Paris
     * @param int    $overageAmountCents overage × unit price, in euro cents HT (rounded half up)
     */
    public function __construct(
        public string $month,
        public int $quota,
        public int $used,
        public int $queued,
        public int $overageAmountCents,
    ) {
    }

    /** Segments used beyond the quota. */
    public function overage(): int
    {
        return max(0, $this->used - $this->quota);
    }

    /** Share of the quota used, in whole percent (may exceed 100). */
    public function percent(): int
    {
        return intdiv($this->used * 100, $this->quota);
    }

    /**
     * Whether $newSegments more (on top of what is sent and still queued)
     * would take the overage beyond the cap.
     */
    public function wouldReachCap(int $newSegments, int $overageCap): bool
    {
        return max(0, $this->used + $this->queued + $newSegments - $this->quota) > $overageCap;
    }

    /** Whether the sent and queued segments already exceed quota + cap. */
    public function capReached(int $overageCap): bool
    {
        return max(0, $this->used + $this->queued - $this->quota) >= $overageCap;
    }
}
