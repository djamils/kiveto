<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Adapter\Scheduling;

use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Infrastructure\Adapter\Scheduling\MessengerSchedulingServiceCoordinator;
use App\Context\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MessengerSchedulingServiceCoordinatorTest extends TestCase
{
    private const string APPOINTMENT_ID = '11111111-1111-4111-8111-111111111111';
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

    public function testCompleteAppointmentDispatchesCommand(): void
    {
        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (CompleteAppointment $cmd): bool => self::APPOINTMENT_ID === $cmd->appointmentId,
            ))
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
