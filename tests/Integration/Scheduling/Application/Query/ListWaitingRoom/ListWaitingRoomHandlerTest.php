<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduling\Application\Query\ListWaitingRoom;

use App\Fixtures\Scheduling\Factory\WaitingRoomEntryEntityFactory;
use App\Scheduling\Application\Query\ListWaitingRoom\ListWaitingRoom;
use App\Scheduling\Application\Query\ListWaitingRoom\ListWaitingRoomHandler;
use App\Scheduling\Application\Query\ListWaitingRoom\WaitingRoomEntryItem;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class ListWaitingRoomHandlerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsActiveEntriesForClinic(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->create()
        ;
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withStatus(WaitingRoomEntryStatus::CALLED)
            ->create()
        ;
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withStatus(WaitingRoomEntryStatus::IN_SERVICE)
            ->create()
        ;
        // CLOSED entries must be excluded
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withStatus(WaitingRoomEntryStatus::CLOSED)
            ->create()
        ;
        // Different clinic — must be excluded
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId('99999999-9999-9999-9999-999999999999')
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->create()
        ;

        $handler = self::getContainer()->get(ListWaitingRoomHandler::class);
        \assert($handler instanceof ListWaitingRoomHandler);

        $items = $handler(new ListWaitingRoom(clinicId: $clinicId));

        self::assertCount(3, $items);
        foreach ($items as $item) {
            self::assertInstanceOf(WaitingRoomEntryItem::class, $item);
            self::assertSame($clinicId, $item->clinicId);
            self::assertNotSame('CLOSED', $item->status);
        }
    }

    public function testReturnsEmptyArrayWhenNoActiveEntries(): void
    {
        $handler = self::getContainer()->get(ListWaitingRoomHandler::class);
        \assert($handler instanceof ListWaitingRoomHandler);

        $items = $handler(new ListWaitingRoom(clinicId: '12345678-9abc-def0-1234-56789abcdef0'));

        self::assertSame([], $items);
    }
}
