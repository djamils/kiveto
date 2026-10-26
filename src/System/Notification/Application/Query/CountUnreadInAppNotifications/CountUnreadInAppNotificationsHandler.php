<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\CountUnreadInAppNotifications;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Domain\Inbox\InAppNotification;
use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CountUnreadInAppNotificationsHandler
{
    public function __construct(
        private InAppNotificationRepositoryInterface $notifications,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CountUnreadInAppNotifications $query): int
    {
        return $this->notifications->countUnread($query->userId, $query->clinicId, $this->clock->now()->modify(\sprintf('-%d days', InAppNotification::RETENTION_DAYS)));
    }
}
