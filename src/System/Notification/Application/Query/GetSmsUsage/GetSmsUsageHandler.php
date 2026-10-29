<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\GetSmsUsage;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfileRepositoryInterface;
use App\System\Notification\Domain\Quota\SmsQuotaPolicy;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The quota, usage and overage of the month; the cap is the clinic's, or
 * the default one while the clinic has no messaging profile.
 */
#[AsMessageHandler]
final readonly class GetSmsUsageHandler
{
    public function __construct(
        private SmsQuotaPolicy $quota,
        private ClinicMessagingProfileRepositoryInterface $profiles,
        private ClockInterface $clock,
        #[Autowire(param: 'notification.sms.default_overage_cap')]
        private int $defaultOverageCap,
    ) {
    }

    public function __invoke(GetSmsUsage $query): SmsUsageView
    {
        $usage = $this->quota->usage($query->clinicId, $this->clock->now());
        $cap   = $this->profiles->find($query->clinicId)->overageCapSegments ?? $this->defaultOverageCap;

        return new SmsUsageView(
            month: $usage->month,
            quota: $usage->quota,
            used: $usage->used,
            queued: $usage->queued,
            overage: $usage->overage(),
            overageAmountCents: $usage->overageAmountCents,
            cap: $cap,
            percent: $usage->percent(),
            capReached: $usage->capReached($cap),
        );
    }
}
