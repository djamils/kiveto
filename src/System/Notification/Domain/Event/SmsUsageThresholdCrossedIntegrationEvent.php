<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * A clinic's billed SMS usage reached a threshold for the first time this
 * quota month: '80' or '100' (% of the quota), or 'CAP' (quota + overage
 * cap: non-critical SMS are held back). Published once per clinic, month
 * and threshold; Clinic tells its administrators (bell).
 */
final readonly class SmsUsageThresholdCrossedIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'notification';
    protected const int    VERSION         = 1;

    /**
     * @param string $month     YYYY-MM (Europe/Paris)
     * @param string $threshold 80 | 100 | CAP
     */
    public function __construct(
        public string $clinicId,
        public string $month,
        public string $threshold,
        public int $used,
        public int $quota,
        public int $cap,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clinicId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'clinicId'  => $this->clinicId,
            'month'     => $this->month,
            'threshold' => $this->threshold,
            'used'      => $this->used,
            'quota'     => $this->quota,
            'cap'       => $this->cap,
        ];
    }
}
