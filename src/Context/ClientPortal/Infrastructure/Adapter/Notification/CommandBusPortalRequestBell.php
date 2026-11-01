<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Notification;

use App\Context\ClientPortal\Application\Port\PortalRequestBellInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;

/**
 * Notification's PostInAppNotification, in the caller's transaction. The
 * entry opens the request in VetApp « Demandes Portal »; one entry per
 * request (`portal-request:<id>`).
 */
final readonly class CommandBusPortalRequestBell implements PortalRequestBellInterface
{
    public const string KIND = 'PORTAL_REQUEST';

    public const string ROUTE = 'clinic_portal_requests_show';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function announce(string $clinicId, array $userIds, string $requestId, string $title, string $detail): void
    {
        if ([] === $userIds) {
            return;
        }

        $this->commandBus->dispatch(new PostInAppNotification(
            clinicId: $clinicId,
            userIds: array_values(array_unique($userIds)),
            kind: self::KIND,
            title: $title,
            detail: $detail,
            route: self::ROUTE,
            routeParams: ['id' => $requestId],
            idempotencyKey: 'portal-request:' . $requestId,
        ));
    }
}
