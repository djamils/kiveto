<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query\GetWaitingRoomEntryDetails;

use App\Fixtures\Context\Scheduling\Factory\WaitingRoomEntryEntityFactory;
use App\Context\Scheduling\Application\Query\GetWaitingRoomEntryDetails\GetWaitingRoomEntryDetails;
use App\Context\Scheduling\Application\Query\GetWaitingRoomEntryDetails\GetWaitingRoomEntryDetailsHandler;
use App\Context\Scheduling\Application\Query\GetWaitingRoomEntryDetails\WaitingRoomEntryDetailsDTO;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryOrigin;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class GetWaitingRoomEntryDetailsHandlerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsEntryDetailsWhenFound(): void
    {
        $entryId  = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        WaitingRoomEntryEntityFactory::new()
            ->withId($entryId)
            ->withClinicId($clinicId)
            ->withOrigin(WaitingRoomEntryOrigin::WALK_IN)
            ->withArrivalMode(WaitingRoomArrivalMode::EMERGENCY)
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->withOwnerId('22222222-2222-2222-2222-222222222222')
            ->withAnimalId('33333333-3333-3333-3333-333333333333')
            ->create([
                'triageNotes' => 'Severe pain',
            ])
        ;

        $handler = self::getContainer()->get(GetWaitingRoomEntryDetailsHandler::class);
        \assert($handler instanceof GetWaitingRoomEntryDetailsHandler);

        $result = $handler(new GetWaitingRoomEntryDetails($entryId));

        self::assertInstanceOf(WaitingRoomEntryDetailsDTO::class, $result);
        self::assertSame($entryId, $result->waitingRoomEntryId);
        self::assertSame($clinicId, $result->clinicId);
        self::assertSame('WAITING', $result->status);
        self::assertSame('WALK_IN', $result->origin);
        self::assertSame('EMERGENCY', $result->arrivalMode);
        self::assertSame('22222222-2222-2222-2222-222222222222', $result->ownerId);
        self::assertSame('33333333-3333-3333-3333-333333333333', $result->animalId);
        self::assertSame('Severe pain', $result->triageNotes);
    }

    public function testThrowsWhenEntryNotFound(): void
    {
        $handler = self::getContainer()->get(GetWaitingRoomEntryDetailsHandler::class);
        \assert($handler instanceof GetWaitingRoomEntryDetailsHandler);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Waiting room entry "00000000-0000-0000-0000-000000000000" not found.');

        $handler(new GetWaitingRoomEntryDetails('00000000-0000-0000-0000-000000000000'));
    }
}
