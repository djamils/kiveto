<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Adapter\Notification;

use App\Context\Inventory\Application\Port\StaffBellInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;

/**
 * Notification's PostInAppNotification, in the caller's transaction. The
 * stock page opens an article's sheet with `article`, a tab with `onglet`.
 */
final readonly class CommandBusStaffBell implements StaffBellInterface
{
    public const string ROUTE = 'clinic_stock';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function announceCriticalStock(string $clinicId, array $userIds, string $articleId, string $title, string $detail, string $idempotencyKey): void
    {
        $this->post($clinicId, $userIds, 'CRITICAL_STOCK', $title, $detail, ['article' => $articleId], $idempotencyKey);
    }

    public function announceExpiringLots(string $clinicId, array $userIds, string $title, string $detail, string $idempotencyKey): void
    {
        $this->post($clinicId, $userIds, 'EXPIRING_LOTS', $title, $detail, ['onglet' => 'alertes'], $idempotencyKey);
    }

    /**
     * @param list<string>          $userIds
     * @param array<string, string> $routeParams
     */
    private function post(string $clinicId, array $userIds, string $kind, string $title, string $detail, array $routeParams, string $idempotencyKey): void
    {
        $this->commandBus->dispatch(new PostInAppNotification(
            clinicId: $clinicId,
            userIds: $userIds,
            kind: $kind,
            title: $title,
            detail: $detail,
            route: self::ROUTE,
            routeParams: $routeParams,
            idempotencyKey: $idempotencyKey,
        ));
    }
}
