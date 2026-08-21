<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\RemovePlanAction;

use App\Context\Consultation\Application\Command\RemovePlanAction\RemovePlanAction;
use App\Context\Consultation\Application\Command\RemovePlanAction\RemovePlanActionHandler;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\BillingLineRecord;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\PlanActionRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RemovePlanActionHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ADMISSION_ID    = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID      = '66666666-6666-4666-8666-666666666666';
    private const string OTHER_CLINIC_ID = '77777777-7777-4777-8777-777777777777';
    private const string PLAN_ACTION_ID  = '88888888-8888-4888-8888-888888888888';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private ClockInterface&MockObject $clock;
    private RemovePlanActionHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);
        $this->handler       = new RemovePlanActionHandler($this->consultations, $this->clock);
    }

    public function testRemovePlanActionDropsItsBillingLine(): void
    {
        $consultation = $this->makeConsultationWithPlanActions();
        $planActionId = self::firstPlanActionId($consultation);

        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::once())->method('save')->with(self::identicalTo($consultation));

        ($this->handler)($this->command($planActionId));

        self::assertSame(['Repos strict'], self::descriptionsOf($consultation));
        self::assertSame([], self::billedLabelsOf($consultation));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)($this->command(self::PLAN_ACTION_ID));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationBelongsToAnotherClinic(): void
    {
        $this->consultations->expects(self::once())
            ->method('findById')
            ->willReturn($this->makeConsultation(self::OTHER_CLINIC_ID))
        ;
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)($this->command(self::PLAN_ACTION_ID));
    }

    public function testFailsWhenConsultationIsClosed(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeClosedConsultation());
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        ($this->handler)($this->command(self::PLAN_ACTION_ID));
    }

    private function command(string $planActionId): RemovePlanAction
    {
        return new RemovePlanAction(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            planActionId: $planActionId,
        );
    }

    private static function firstPlanActionId(Consultation $consultation): string
    {
        $planActions = $consultation->getPlanActions();

        if ([] === $planActions) {
            self::fail('Expected the consultation to hold a plan action.');
        }

        return $planActions[0]->getId();
    }

    /**
     * @return list<string>
     */
    private static function descriptionsOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PlanActionRecord $action): string => $action->getDescription(),
            $consultation->getPlanActions(),
        );
    }

    /**
     * @return list<string>
     */
    private static function billedLabelsOf(Consultation $consultation): array
    {
        return array_map(
            static fn (BillingLineRecord $line): string => $line->getLabel(),
            $consultation->getBillingLines(),
        );
    }

    private function makeConsultationWithPlanActions(): Consultation
    {
        $consultation = $this->makeConsultation();
        $consultation->addPlanAction(
            PlanActionKind::PERFORMED_ACT,
            'Détartrage',
            'ACT-001',
            null,
            null,
            null,
            1.0,
            2500,
            'EUR',
            'STANDARD',
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:05:00'),
        );
        $consultation->addPlanAction(
            PlanActionKind::ADVICE,
            'Repos strict',
            null,
            null,
            null,
            null,
            1.0,
            null,
            null,
            null,
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:06:00'),
        );

        return $consultation;
    }

    private function makeConsultation(string $clinicId = self::CLINIC_ID): Consultation
    {
        return Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString($clinicId),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }

    private function makeClosedConsultation(): Consultation
    {
        $consultation = $this->makeConsultation();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 09:30:00'),
        );

        return $consultation;
    }
}
