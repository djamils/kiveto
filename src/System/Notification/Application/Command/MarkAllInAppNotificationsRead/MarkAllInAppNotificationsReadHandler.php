<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\MarkAllInAppNotificationsRead;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkAllInAppNotificationsReadHandler
{
    public function __construct(
        private InAppNotificationRepositoryInterface $notifications,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int how many entries were marked read
     */
    public function __invoke(MarkAllInAppNotificationsRead $command): int
    {
        return $this->notifications->markAllRead($command->userId, $command->clinicId, $this->clock->now());
    }
}
