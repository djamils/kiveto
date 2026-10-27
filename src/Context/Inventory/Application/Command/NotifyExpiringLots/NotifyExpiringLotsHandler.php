<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\NotifyExpiringLots;

use App\Context\Inventory\Application\Notification\StockNoticeRecipients;
use App\Context\Inventory\Application\Port\ClinicTimeZoneInterface;
use App\Context\Inventory\Application\Port\ExpiryNoticeRepositoryInterface;
use App\Context\Inventory\Application\Port\StaffBellInterface;
use App\Context\Inventory\Application\Port\StockNoticeJobsInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * « Péremption proche · 2 lots » — « Expirent dans moins de 30 jours · voir
 * Stocks », to the clinic's assistants (or its administrators), days counted
 * in the clinic's time zone. The lots are recorded as announced, so the next
 * days only announce new ones; the entry's key names the lots it announces,
 * so a second scan the same day still announces lots that arrived since. A
 * clinic with nobody to tell keeps its lots for a later scan.
 *
 * The daily run (no clinic id) only queues one scan per clinic with lots
 * expiring around the window (a day's margin each side covers every time
 * zone).
 */
#[AsMessageHandler]
final readonly class NotifyExpiringLotsHandler
{
    /** The INFO threshold of the expiry alerts. */
    public const int WITHIN_DAYS = 30;

    public function __construct(
        private ExpiryNoticeRepositoryInterface $notices,
        private StockNoticeRecipients $recipients,
        private StaffBellInterface $bell,
        private StockNoticeJobsInterface $jobs,
        private ClinicTimeZoneInterface $timeZones,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(NotifyExpiringLots $command): void
    {
        $now = $this->clock->now();

        if (null === $command->clinicId) {
            $today = $now->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);

            foreach ($this->notices->clinicsWithUnannouncedLotsExpiringBetween($today->modify('-1 day'), $today->modify(\sprintf('+%d days', self::WITHIN_DAYS + 1))) as $clinicId) {
                $this->jobs->notifyExpiringLots($clinicId);
            }

            return;
        }

        $clinicId = $command->clinicId;
        $today    = $now->setTimezone($this->timeZones->of($clinicId))->setTime(0, 0);
        $lotIds   = $this->notices->unannouncedLotsExpiringBetween($clinicId, $today, $today->modify(\sprintf('+%d days', self::WITHIN_DAYS)));
        $userIds  = [] !== $lotIds ? $this->recipients->of($clinicId) : [];

        if ([] === $userIds) {
            return;
        }

        $count = \count($lotIds);
        $this->bell->announceExpiringLots(
            clinicId: $clinicId,
            userIds: $userIds,
            title: \sprintf('Péremption proche · %d %s', $count, $count > 1 ? 'lots' : 'lot'),
            detail: \sprintf('%s dans moins de %d jours · voir Stocks', $count > 1 ? 'Expirent' : 'Expire', self::WITHIN_DAYS),
            idempotencyKey: \sprintf('expiring:%s:%s:%s', $clinicId, $today->format('Y-m-d'), mb_substr(hash('sha256', implode(',', $lotIds)), 0, 16)),
        );
        $this->notices->markAnnounced($lotIds, $now);
    }
}
