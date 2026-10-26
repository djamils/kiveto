<?php

declare(strict_types=1);

namespace App\System\Notification\Application\EventSubscriber;

use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfileRepositoryInterface;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Event\SmsUsageThresholdCrossedIntegrationEvent;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Quota\SmsQuotaPolicy;
use App\System\Notification\Domain\Quota\SmsThreshold;
use App\System\Notification\Domain\Quota\SmsThresholdRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Once a clinic SMS is handed over (its segments now billed), the month's
 * usage is recomputed; each threshold (80 %, 100 %, cap) reached for the
 * first time this month is recorded (INSERT IGNORE: concurrent sends never
 * announce it twice) and published, in the same transaction.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnSmsSent
{
    public function __construct(
        private SmsQuotaPolicy $quota,
        private ClinicMessagingProfileRepositoryInterface $profiles,
        private SmsThresholdRepositoryInterface $thresholds,
        private IntegrationEventPublisher $publisher,
        private ClockInterface $clock,
        #[Autowire(param: 'notification.sms.default_overage_cap')]
        private int $defaultOverageCap,
    ) {
    }

    public function __invoke(OutboundMessageStatusChanged $event): void
    {
        $clinicId = $event->clinicId;

        if (null === $clinicId || Channel::SMS->value !== $event->channel || DeliveryStatus::SENT->value !== $event->status) {
            return;
        }

        $now   = $this->clock->now();
        $usage = $this->quota->usage($clinicId, $now);
        $cap   = $this->profiles->find($clinicId)->overageCapSegments ?? $this->defaultOverageCap;

        foreach (SmsThreshold::reachedBy($usage, $cap) as $threshold) {
            if ($this->thresholds->markCrossed($clinicId, $usage->month, $threshold, $now)) {
                $this->publisher->publish(new SmsUsageThresholdCrossedIntegrationEvent($clinicId, $usage->month, $threshold->value, $usage->used, $usage->quota, $cap));
            }
        }
    }
}
