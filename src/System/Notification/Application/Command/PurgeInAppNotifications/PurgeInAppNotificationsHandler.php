<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PurgeInAppNotifications;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Domain\Inbox\InAppNotification;
use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeInAppNotificationsHandler
{
    public function __construct(
        private InAppNotificationRepositoryInterface $notifications,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeInAppNotifications $command): int
    {
        $purged = $this->notifications->purgeCreatedBefore($this->clock->now()->modify(\sprintf('-%d days', InAppNotification::RETENTION_DAYS)));
        $this->logger->info('Bell entries purged.', ['count' => $purged]);

        return $purged;
    }
}
