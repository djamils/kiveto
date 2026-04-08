<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicalCare\Infrastructure\Adapter\Scheduling;

use App\Context\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\Context\ClinicalCare\Domain\ValueObject\UserId;
use App\Context\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
use App\Context\ClinicalCare\Infrastructure\Adapter\Scheduling\MessengerSchedulingServiceCoordinator;
use App\Context\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment;
use App\Context\Scheduling\Application\Command\StartServiceForWaitingRoomEntry\StartServiceForWaitingRoomEntry;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MessengerSchedulingServiceCoordinatorTest extends TestCase
{
    private const string APPOINTMENT_ID = '11111111-1111-4111-8111-111111111111';
    private const string ENTRY_ID       = '22222222-2222-4222-8222-222222222222';
    private const string USER_ID        = '33333333-3333-4333-8333-333333333333';

    private CommandBusInterface&MockObject $commandBus;
    private MessengerSchedulingServiceCoordinator $coordinator;

    protected function setUp(): void
    {
        $this->commandBus  = $this->createMock(CommandBusInterface::class);
        $this->coordinator = new MessengerSchedulingServiceCoordinator($this->commandBus);
    }

    public function testEnsureAppointmentInServiceIsANoopForNow(): void
    {
        // The Scheduling BC does not yet expose a StartServiceForAppointment command,
        // so this method must currently NOT call the bus.
        $this->commandBus->expects(self::never())->method('dispatch');

        $this->coordinator->ensureAppointmentInService(
            AppointmentId::fromString(self::APPOINTMENT_ID),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testEnsureWaitingRoomEntryInServiceDispatchesCommand(): void
    {
        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (StartServiceForWaitingRoomEntry $cmd): bool => self::ENTRY_ID === $cmd->waitingRoomEntryId
                && self::USER_ID === $cmd->serviceStartedByUserId))
            ->willReturn(new \stdClass())
        ;

        $this->coordinator->ensureWaitingRoomEntryInService(
            WaitingRoomEntryId::fromString(self::ENTRY_ID),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testEnsureWaitingRoomEntryInServiceSwallowsBusFailures(): void
    {
        // The contract is "best effort" — exceptions from the bus must not bubble up.
        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('downstream failure'))
        ;

        $this->coordinator->ensureWaitingRoomEntryInService(
            WaitingRoomEntryId::fromString(self::ENTRY_ID),
            UserId::fromString(self::USER_ID),
        );

        $this->addToAssertionCount(1);
    }

    public function testCompleteAppointmentDispatchesCommand(): void
    {
        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (CompleteAppointment $cmd): bool => self::APPOINTMENT_ID === $cmd->appointmentId))
            ->willReturn(new \stdClass())
        ;

        $this->coordinator->completeAppointment(
            AppointmentId::fromString(self::APPOINTMENT_ID),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCompleteAppointmentSwallowsBusFailures(): void
    {
        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('downstream failure'))
        ;

        $this->coordinator->completeAppointment(
            AppointmentId::fromString(self::APPOINTMENT_ID),
            UserId::fromString(self::USER_ID),
        );

        $this->addToAssertionCount(1);
    }
}
