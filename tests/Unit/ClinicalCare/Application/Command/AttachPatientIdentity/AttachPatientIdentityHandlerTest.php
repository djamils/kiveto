<?php

declare(strict_types=1);

namespace App\Tests\Unit\ClinicalCare\Application\Command\AttachPatientIdentity;

use App\ClinicalCare\Application\Command\AttachPatientIdentity\AttachPatientIdentity;
use App\ClinicalCare\Application\Command\AttachPatientIdentity\AttachPatientIdentityHandler;
use App\ClinicalCare\Application\Port\AnimalExistenceCheckerInterface;
use App\ClinicalCare\Application\Port\OwnerExistenceCheckerInterface;
use App\ClinicalCare\Domain\Consultation;
use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\ClinicalCare\Domain\ValueObject\ClinicId;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AttachPatientIdentityHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string OWNER_ID        = '66666666-6666-4666-8666-666666666666';
    private const string ANIMAL_ID       = '77777777-7777-4777-8777-777777777777';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private OwnerExistenceCheckerInterface&MockObject $ownerChecker;
    private AnimalExistenceCheckerInterface&MockObject $animalChecker;
    private ClockInterface&MockObject $clock;
    private AttachPatientIdentityHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->ownerChecker  = $this->createMock(OwnerExistenceCheckerInterface::class);
        $this->animalChecker = $this->createMock(AnimalExistenceCheckerInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);

        $this->handler = new AttachPatientIdentityHandler(
            $this->consultations,
            $this->ownerChecker,
            $this->animalChecker,
            $this->clock,
        );
    }

    public function testAttachOwnerAndAnimalSuccessfully(): void
    {
        $consultation = $this->makeConsultation();
        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->ownerChecker->expects(self::once())->method('exists')->willReturn(true);
        $this->animalChecker->expects(self::once())->method('exists')->willReturn(true);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:05:00'));
        $this->consultations->expects(self::once())->method('save');

        ($this->handler)(new AttachPatientIdentity(
            consultationId: self::CONSULTATION_ID,
            ownerId: self::OWNER_ID,
            animalId: self::ANIMAL_ID,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)(new AttachPatientIdentity(
            consultationId: self::CONSULTATION_ID,
            ownerId: self::OWNER_ID,
            animalId: null,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenOwnerDoesNotExist(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeConsultation());
        $this->ownerChecker->expects(self::once())->method('exists')->willReturn(false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Owner does not exist');

        ($this->handler)(new AttachPatientIdentity(
            consultationId: self::CONSULTATION_ID,
            ownerId: self::OWNER_ID,
            animalId: null,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAnimalDoesNotExist(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeConsultation());
        $this->animalChecker->expects(self::once())->method('exists')->willReturn(false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Animal does not exist');

        ($this->handler)(new AttachPatientIdentity(
            consultationId: self::CONSULTATION_ID,
            ownerId: null,
            animalId: self::ANIMAL_ID,
        ));
    }

    private function makeConsultation(): Consultation
    {
        return Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            UserId::fromString(self::USER_ID),
            null,
            null,
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }
}
