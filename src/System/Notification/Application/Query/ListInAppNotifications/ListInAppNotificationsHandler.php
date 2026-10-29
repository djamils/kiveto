<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\ListInAppNotifications;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Domain\Inbox\InAppNotification;
use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListInAppNotificationsHandler
{
    public function __construct(
        private InAppNotificationRepositoryInterface $notifications,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<InAppNotificationView>
     */
    public function __invoke(ListInAppNotifications $query): array
    {
        $since = $this->clock->now()->modify(\sprintf('-%d days', InAppNotification::RETENTION_DAYS));

        return array_map(
            static fn (InAppNotification $n): InAppNotificationView => new InAppNotificationView(
                id: $n->id()->toString(),
                kind: $n->kind()->value,
                filter: $n->kind()->filter(),
                title: $n->title(),
                detail: $n->detail(),
                route: $n->route(),
                routeParams: $n->routeParams(),
                actorInitials: $n->actorInitials(),
                createdAt: $n->createdAt()->format(\DateTimeInterface::ATOM),
                read: $n->isRead(),
            ),
            $this->notifications->listFor($query->userId, $query->clinicId, $since, $query->limit),
        );
    }
}
