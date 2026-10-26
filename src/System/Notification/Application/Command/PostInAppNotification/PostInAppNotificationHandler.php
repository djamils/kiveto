<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PostInAppNotification;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Domain\Inbox\InAppKind;
use App\System\Notification\Domain\Inbox\InAppNotification;
use App\System\Notification\Domain\Inbox\InAppNotificationId;
use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PostInAppNotificationHandler
{
    public function __construct(
        private InAppNotificationRepositoryInterface $notifications,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int how many entries were created (users already notified under that key are skipped)
     */
    public function __invoke(PostInAppNotification $command): int
    {
        $kind    = InAppKind::tryFrom($command->kind) ?? throw new InvalidNotificationRequestException(\sprintf('Unknown bell entry kind "%s".', $command->kind));
        $now     = $this->clock->now();
        $created = 0;

        foreach (array_unique($command->userIds) as $userId) {
            $created += (int) $this->notifications->add(InAppNotification::post(
                InAppNotificationId::fromString($this->uuids->generate()),
                $command->clinicId,
                $userId,
                $kind,
                $command->title,
                $command->detail,
                $command->route,
                $command->routeParams,
                $command->actorInitials,
                $command->idempotencyKey,
                $now,
            ));
        }

        return $created;
    }
}
