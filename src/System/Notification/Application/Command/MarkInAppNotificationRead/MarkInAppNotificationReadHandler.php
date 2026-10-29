<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\MarkInAppNotificationRead;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Domain\Inbox\InAppNotificationId;
use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkInAppNotificationReadHandler
{
    public function __construct(
        private InAppNotificationRepositoryInterface $notifications,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return bool whether an unread entry of that user was marked read
     */
    public function __invoke(MarkInAppNotificationRead $command): bool
    {
        return $this->notifications->markRead(InAppNotificationId::fromString($command->notificationId), $command->userId, $this->clock->now());
    }
}
