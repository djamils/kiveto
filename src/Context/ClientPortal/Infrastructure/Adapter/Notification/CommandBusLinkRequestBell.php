<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Notification;

use App\Context\ClientPortal\Application\Port\LinkRequestBellInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;

/**
 * Notification's PostInAppNotification, in the caller's transaction: a
 * `PORTAL_REQUEST` entry opening the « Rattachement » request of the
 * account in VetApp « Demandes Portal ».
 */
final readonly class CommandBusLinkRequestBell implements LinkRequestBellInterface
{
    public const string ROUTE = 'clinic_portal_link_requests_show';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function announce(string $clinicId, array $userIds, string $ownerUserId, string $key, string $title, string $detail): void
    {
        if ([] === $userIds) {
            return;
        }

        $this->commandBus->dispatch(new PostInAppNotification(
            clinicId: $clinicId,
            userIds: array_values(array_unique($userIds)),
            kind: CommandBusPortalRequestBell::KIND,
            title: $title,
            detail: $detail,
            route: self::ROUTE,
            routeParams: ['userId' => $ownerUserId],
            idempotencyKey: $key,
        ));
    }
}
