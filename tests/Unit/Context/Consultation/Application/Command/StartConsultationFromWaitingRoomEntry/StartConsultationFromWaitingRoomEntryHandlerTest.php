<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\StartConsultationFromWaitingRoomEntry;

use App\Context\Consultation\Application\Command\StartConsultationFromWaitingRoomEntry\StartConsultationFromWaitingRoomEntry as StartFromEntryCmd; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\Context\Consultation\Application\Command\StartConsultationFromWaitingRoomEntry\StartConsultationFromWaitingRoomEntryHandler as StartFromEntryHandler; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\Context\Consultation\Application\Port\PractitionerEligibilityCheckerInterface;
use App\Context\Consultation\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Scheduling\Application\Query\GetWaitingRoomEntryDetails\WaitingRoomEntryDetailsDTO;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StartConsultationFromWaitingRoomEntryHandlerTest extends TestCase
{
    private const string ENTRY_ID  = '44444444-4444-4444-8444-444444444444';
    private const string CLINIC_ID = '22222222-2222-4222-8222-222222222222';
    private const string USER_ID   = '55555555-5555-4555-8555-555555555555';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private PractitionerEligibilityCheckerInterface&MockObject $eligibility;
    private SchedulingServiceCoordinatorInterface&MockObject $coordinator;
    private QueryBusInterface&MockObject $queryBus;
    private ClockInterface&MockObject $clock;
    private StartFromEntryHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->eligibility   = $this->createMock(PractitionerEligibilityCheckerInterface::class);
        $this->coordinator   = $this->createMock(SchedulingServiceCoordinatorInterface::class);
        $this->queryBus      = $this->createMock(QueryBusInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);

        $this->handler = new StartFromEntryHandler(
            $this->consultations,
            $this->eligibility,
            $this->coordinator,
            $this->queryBus,
            $this->clock,
        );
    }

    public function testStartConsultationSuccessfully(): void
    {
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->queryBus->expects(self::once())
            ->method('ask')
            ->willReturn(new WaitingRoomEntryDetailsDTO(
                waitingRoomEntryId: self::ENTRY_ID,
                clinicId: self::CLINIC_ID,
                status: 'IN_SERVICE',
                origin: 'WALK_IN',
                arrivalMode: 'STANDARD',
                linkedAppointmentId: null,
                ownerId: '66666666-6666-4666-8666-666666666666',
                animalId: '77777777-7777-4777-8777-777777777777',
                triageNotes: null,
                arrivedAtUtc: '2026-04-10T08:55:00+00:00',
                calledAtUtc: null,
                serviceStartedAtUtc: null,
                closedAtUtc: null,
            ))
        ;
        $this->eligibility->expects(self::once())->method('isEligibleForClinicAt')->willReturn(true);
        $this->coordinator->expects(self::once())->method('ensureWaitingRoomEntryInService');
        $this->consultations->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Consultation::class))
        ;

        $consultationId = ($this->handler)(new StartFromEntryCmd(
            waitingRoomEntryId: self::ENTRY_ID,
            startedByUserId: self::USER_ID,
        ));

        self::assertNotEmpty($consultationId);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenPractitionerNotEligible(): void
    {
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->queryBus->expects(self::once())
            ->method('ask')
            ->willReturn(new WaitingRoomEntryDetailsDTO(
                waitingRoomEntryId: self::ENTRY_ID,
                clinicId: self::CLINIC_ID,
                status: 'IN_SERVICE',
                origin: 'WALK_IN',
                arrivalMode: 'STANDARD',
                linkedAppointmentId: null,
                ownerId: null,
                animalId: null,
                triageNotes: null,
                arrivedAtUtc: '2026-04-10T08:55:00+00:00',
                calledAtUtc: null,
                serviceStartedAtUtc: null,
                closedAtUtc: null,
            ))
        ;
        $this->eligibility->expects(self::once())->method('isEligibleForClinicAt')->willReturn(false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User is not eligible as practitioner for this clinic');

        ($this->handler)(new StartFromEntryCmd(
            waitingRoomEntryId: self::ENTRY_ID,
            startedByUserId: self::USER_ID,
        ));
    }
}
