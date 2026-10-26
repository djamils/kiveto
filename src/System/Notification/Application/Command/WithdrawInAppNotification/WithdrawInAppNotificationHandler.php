<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\WithdrawInAppNotification;

use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class WithdrawInAppNotificationHandler
{
    public function __construct(private InAppNotificationRepositoryInterface $notifications)
    {
    }

    /**
     * @return int how many unread entries were withdrawn
     */
    public function __invoke(WithdrawInAppNotification $command): int
    {
        return $this->notifications->withdrawUnread($command->idempotencyKey);
    }
}
