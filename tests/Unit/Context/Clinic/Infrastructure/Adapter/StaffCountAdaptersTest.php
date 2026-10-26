<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Adapter;

use App\Context\Clinic\Infrastructure\Adapter\IdentityAccess\QueryBusUserAccountStatus;
use App\Context\Clinic\Infrastructure\Adapter\Notification\NotificationMessagingProfileSync;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Query\GetUserStatuses\GetUserStatuses;
use App\System\Notification\Application\Command\RecordVeterinarianCount\RecordVeterinarianCount;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class StaffCountAdaptersTest extends TestCase
{
    public function testOnlyActiveAccountsAreKept(): void
    {
        $bus = new AnsweringQueryBus()->answer(GetUserStatuses::class, ['a' => 'ACTIVE', 'b' => 'PENDING', 'c' => 'DISABLED']);

        self::assertSame(['a'], new QueryBusUserAccountStatus($bus)->activeUserIds(['a', 'b', 'c', 'unknown']));
        self::assertInstanceOf(GetUserStatuses::class, $bus->asked[0]);
        self::assertSame(['a', 'b', 'c', 'unknown'], $bus->asked[0]->userIds);
    }

    public function testTheCountIsHandedToNotification(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(new RecordVeterinarianCount('c', '2026-10-26', 3));

        new NotificationMessagingProfileSync($bus)->pushVeterinarianCount('c', '2026-10-26', 3);
    }
}
