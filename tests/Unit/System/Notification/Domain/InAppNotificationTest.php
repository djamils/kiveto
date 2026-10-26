<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Domain;

use App\System\Notification\Domain\Inbox\InAppKind;
use App\System\Notification\Domain\Inbox\InAppNotification;
use App\System\Notification\Domain\Inbox\InAppNotificationId;
use PHPUnit\Framework\TestCase;

final class InAppNotificationTest extends TestCase
{
    public function testAnEntryIsTrimmedAndBounded(): void
    {
        $entry = InAppNotification::post(
            InAppNotificationId::fromString('01960000-0000-7000-8000-0000000000b1'),
            'c-1',
            'u-1',
            InAppKind::NEW_APPOINTMENT,
            '  Nouveau RDV  ',
            str_repeat('d', 600),
            'clinic_agenda',
            ['date' => '2026-10-22'],
            '  cl ',
            ' new-appointment:a-1 ',
            new \DateTimeImmutable('2026-10-21 09:00:00'),
        );

        self::assertSame(['Nouveau RDV', 500, 'cl', 'new-appointment:a-1', false, 'activite'], [$entry->title(), mb_strlen($entry->detail()), $entry->actorInitials(), $entry->idempotencyKey(), $entry->isRead(), $entry->kind()->filter()]);
        self::assertNull($entry->readAt());
        self::assertNull(InAppNotification::post(InAppNotificationId::fromString('01960000-0000-7000-8000-0000000000b2'), 'c', 'u', InAppKind::SMS_QUOTA, 'Quota SMS', '', 'r', [], ' ', 'k', new \DateTimeImmutable())->actorInitials());
    }

    public function testAnEntryNeedsATitleARouteAndAKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        InAppNotification::post(InAppNotificationId::fromString('01960000-0000-7000-8000-0000000000b1'), 'c', 'u', InAppKind::CRITICAL_STOCK, 'Stock critique', '', 'r', [], null, str_repeat('k', 191), new \DateTimeImmutable());
    }
}
