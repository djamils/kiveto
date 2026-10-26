<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

/**
 * The in-app bell of the clinic's members (Notification's inbox).
 */
interface StaffBellInterface
{
    /**
     * « Stock critique »: the entry opens the stock page on the article.
     *
     * @param list<string> $userIds
     */
    public function announceCriticalStock(string $clinicId, array $userIds, string $articleId, string $title, string $detail, string $idempotencyKey): void;

    /**
     * « Péremption proche »: the entry opens the stock alerts.
     *
     * @param list<string> $userIds
     */
    public function announceExpiringLots(string $clinicId, array $userIds, string $title, string $detail, string $idempotencyKey): void;
}
