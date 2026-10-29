<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\Notification;

use App\Context\Clinic\Application\Port\BellNotifierInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;

final readonly class CommandBusBellNotifier implements BellNotifierInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function post(string $clinicId, array $userIds, string $kind, string $title, string $detail, string $route, string $idempotencyKey): void
    {
        $this->commandBus->dispatch(new PostInAppNotification(
            clinicId: $clinicId,
            userIds: $userIds,
            kind: $kind,
            title: $title,
            detail: $detail,
            route: $route,
            routeParams: [],
            idempotencyKey: $idempotencyKey,
        ));
    }
}
