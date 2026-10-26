<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\NotifyExpiringLots;

use App\Context\Inventory\Application\Notification\StockNoticeRecipients;
use App\Context\Inventory\Application\Port\ExpiryNoticeRepositoryInterface;
use App\Context\Inventory\Application\Port\StaffBellInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * « Péremption proche · 2 lots » — « Expirent dans moins de 30 jours · voir
 * Stocks », to each clinic's assistants (or its administrators). The lots
 * are recorded as announced, so the next days only announce new ones. A
 * clinic with nobody to tell keeps its lots for a later scan. Days are
 * counted in Europe/Paris, like every scheduled scan.
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
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int how many lots were announced
     */
    public function __invoke(NotifyExpiringLots $command): int
    {
        $now       = $this->clock->now();
        $today     = $now->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $announced = 0;

        foreach ($this->notices->unannouncedLotsExpiringBetween($today, $today->modify(\sprintf('+%d days', self::WITHIN_DAYS))) as $clinicId => $lotIds) {
            $userIds = $this->recipients->of($clinicId);

            if ([] === $userIds) {
                continue;
            }

            $count = \count($lotIds);
            $this->bell->announceExpiringLots(
                clinicId: $clinicId,
                userIds: $userIds,
                title: \sprintf('Péremption proche · %d %s', $count, $count > 1 ? 'lots' : 'lot'),
                detail: \sprintf('%s dans moins de %d jours · voir Stocks', $count > 1 ? 'Expirent' : 'Expire', self::WITHIN_DAYS),
                idempotencyKey: \sprintf('expiring:%s:%s', $clinicId, $today->format('Y-m-d')),
            );
            $this->notices->markAnnounced($lotIds, $now);
            $announced += $count;
        }

        return $announced;
    }
}
