<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Adapter\Admission;

use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatus;
use App\Context\Consultation\Application\Port\AdmissionContextDto;
use App\Context\Consultation\Application\Port\AdmissionContextProviderInterface;
use App\Context\Consultation\Infrastructure\Adapter\Admission\MessengerAdmissionServiceCoordinator;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\TestCase;

final class MessengerAdmissionServiceCoordinatorTest extends TestCase
{
    private const string ADMISSION = '01960000-0000-7000-8000-0000000c0a01';
    private const string CLINIC    = '01960000-0000-7000-8000-0000000c0a02';

    /** @var list<object> */
    private array $dispatched = [];

    public function testThePatientInTheConsultationRoomIsSentBackToReception(): void
    {
        $this->coordinator(new AdmissionContextDto('p', self::CLINIC, true, 'in_consultation_room'))->sendToReception(self::ADMISSION, self::CLINIC);

        self::assertCount(1, $this->dispatched);
        $command = $this->dispatched[0];
        self::assertInstanceOf(UpdateAdmissionLocationStatus::class, $command);
        self::assertSame(['at_reception', self::ADMISSION, self::CLINIC], [$command->newLocationStatus, $command->admissionId, $command->clinicId]);
    }

    public function testNothingMovesWhenTheVisitIsClosedOrThePatientIsElsewhere(): void
    {
        $this->coordinator(new AdmissionContextDto('p', self::CLINIC, false, 'in_consultation_room'))->sendToReception(self::ADMISSION, self::CLINIC);
        $this->coordinator(new AdmissionContextDto('p', self::CLINIC, true, 'at_reception'))->sendToReception(self::ADMISSION, self::CLINIC);

        self::assertSame([], $this->dispatched);
    }

    public function record(object $command): void
    {
        $this->dispatched[] = $command;
    }

    private function coordinator(AdmissionContextDto $context): MessengerAdmissionServiceCoordinator
    {
        $test = $this;
        $bus  = new class($test) implements CommandBusInterface {
            public function __construct(private MessengerAdmissionServiceCoordinatorTest $test)
            {
            }

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->test->record($command);

                return null;
            }
        };

        $provider = $this->createStub(AdmissionContextProviderInterface::class);
        $provider->method('getAdmissionContext')->willReturn($context);

        return new MessengerAdmissionServiceCoordinator($bus, $provider);
    }
}
