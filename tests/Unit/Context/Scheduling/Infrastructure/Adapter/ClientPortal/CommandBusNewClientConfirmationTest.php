<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter\ClientPortal;

use App\Context\ClientPortal\Application\Command\ConfirmBookingRequest\ConfirmBookingRequest;
use App\Context\Scheduling\Infrastructure\Adapter\ClientPortal\CommandBusNewClientConfirmation;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;

/** S7-01: Scheduling's paths confirm a new client's request through ClientPortal's one command. */
final class CommandBusNewClientConfirmationTest extends TestCase
{
    public function testTheBookingRequestIsConfirmedByItsAppointmentWithTheDefaultChoices(): void
    {
        $bus = new AnsweringCommandBus(static fn (): bool => true);

        self::assertTrue(new CommandBusNewClientConfirmation($bus)->confirm('clinic-1', 'rdv-1', 'pet-1', true));

        $command = $bus->commands[0];
        self::assertInstanceOf(ConfirmBookingRequest::class, $command);
        self::assertSame(
            ['clinic-1', null, 'rdv-1', ConfirmBookingRequest::DEFAULT, null, ConfirmBookingRequest::DEFAULT, 'pet-1', null, null, true],
            [$command->clinicId, $command->requestId, $command->appointmentId, $command->clientChoice, $command->clientId, $command->animalChoice, $command->animalId, $command->actorUserId, $command->actorName, $command->silent],
        );
    }

    public function testNoPendingBookingRequestAnswersFalse(): void
    {
        self::assertFalse(new CommandBusNewClientConfirmation(new AnsweringCommandBus(static fn (): bool => false))->confirm('clinic-1', 'rdv-1', null, false));
    }
}
