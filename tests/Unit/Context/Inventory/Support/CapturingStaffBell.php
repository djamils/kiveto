<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Support;

use App\Context\Inventory\Application\Port\StaffBellInterface;

final class CapturingStaffBell implements StaffBellInterface
{
    /** @var list<array{kind: string, clinicId: string, userIds: list<string>, articleId: ?string, title: string, detail: string, key: string}> */
    public array $posted = [];

    public function announceCriticalStock(string $clinicId, array $userIds, string $articleId, string $title, string $detail, string $idempotencyKey): void
    {
        $this->posted[] = ['kind' => 'CRITICAL_STOCK', 'clinicId' => $clinicId, 'userIds' => $userIds, 'articleId' => $articleId, 'title' => $title, 'detail' => $detail, 'key' => $idempotencyKey];
    }

    public function announceExpiringLots(string $clinicId, array $userIds, string $title, string $detail, string $idempotencyKey): void
    {
        $this->posted[] = ['kind' => 'EXPIRING_LOTS', 'clinicId' => $clinicId, 'userIds' => $userIds, 'articleId' => null, 'title' => $title, 'detail' => $detail, 'key' => $idempotencyKey];
    }
}
