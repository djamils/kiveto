<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Application\Command;

use App\Context\Prescription\Application\Command\AllowPrescriptionRenewals\AllowPrescriptionRenewals;
use App\Context\Prescription\Application\Command\AllowPrescriptionRenewals\AllowPrescriptionRenewalsHandler;
use App\Context\Prescription\Application\Command\CancelPrescription\CancelPrescription;
use App\Context\Prescription\Application\Command\CancelPrescription\CancelPrescriptionHandler;
use App\Context\Prescription\Application\Command\IssuePrescription\IssuePrescription;
use App\Context\Prescription\Application\Command\IssuePrescription\IssuePrescriptionHandler;
use App\Context\Prescription\Application\Command\RemovePrescriptionLine\RemovePrescriptionLine;
use App\Context\Prescription\Application\Command\RemovePrescriptionLine\RemovePrescriptionLineHandler;
use App\Context\Prescription\Application\Command\RenewPrescription\RenewPrescription;
use App\Context\Prescription\Application\Command\RenewPrescription\RenewPrescriptionHandler;
use App\Context\Prescription\Application\Command\SetPrescriptionNotes\SetPrescriptionNotes;
use App\Context\Prescription\Application\Command\SetPrescriptionNotes\SetPrescriptionNotesHandler;
use App\Context\Prescription\Domain\Prescription;
use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\Service\PrescriptionNumberGeneratorInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\DrugPrescriptionClass;
use App\Context\Prescription\Domain\ValueObject\OwnerSnapshot;
use App\Context\Prescription\Domain\ValueObject\PatientSnapshot;
use App\Context\Prescription\Domain\ValueObject\Prescriber;
use App\Context\Prescription\Domain\ValueObject\PrescriberId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLine;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLineId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionNumber;
use App\Context\Prescription\Domain\ValueObject\PrescriptionStatus;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class PrescriptionCommandHandlersTest extends TestCase
{
    private const string CLINIC_ID       = '01960000-0000-7000-8000-000000000501';
    private const string PRESCRIPTION_ID = '01960000-0000-7000-8000-000000000502';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-000000000503';
    private const string LINE_ID         = '01960000-0000-7000-8000-000000000504';
    private const string NEW_ID          = '01960000-0000-7000-8000-000000000505';
    private const string PRESCRIBER_ID   = '01960000-0000-7000-8000-000000000506';

    private PrescriptionRepositoryInterface&MockObject $prescriptions;
    private ClockInterface&MockObject $clock;

    protected function setUp(): void
    {
        $this->prescriptions = $this->createMock(PrescriptionRepositoryInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn($this->now());
    }

    public function testSigningAsksTheGeneratorForThisYearsNumber(): void
    {
        $prescription = $this->draftWithOneLine();
        $this->prescriptions->expects(self::once())->method('get')->willReturn($prescription);
        $this->prescriptions->expects(self::once())->method('save')->with($prescription);

        $numbers = $this->createMock(PrescriptionNumberGeneratorInterface::class);
        $numbers->expects(self::once())
            ->method('next')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                2026,
            )
            ->willReturn(PrescriptionNumber::of(2026, 12))
        ;

        (new IssuePrescriptionHandler($this->prescriptions, $numbers, $this->clock))(
            new IssuePrescription(self::CLINIC_ID, self::PRESCRIPTION_ID),
        );

        self::assertSame('ORD-2026-000012', $prescription->number()?->toString());
        self::assertSame(PrescriptionStatus::ISSUED, $prescription->status());
    }

    public function testRenewingConsumesOneAllowanceAndKeepsWhoDidIt(): void
    {
        $prescription = $this->issuedWithRenewals(2);
        $this->prescriptions->expects(self::once())->method('get')->willReturn($prescription);
        $this->prescriptions->expects(self::once())->method('save')->with($prescription);

        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        (new RenewPrescriptionHandler($this->prescriptions, $uuids, $this->clock))(
            new RenewPrescription(self::CLINIC_ID, self::PRESCRIPTION_ID, 'user-9'),
        );

        self::assertSame(1, $prescription->renewals()->used());
        self::assertCount(1, $prescription->renewalHistory());
        self::assertSame('user-9', $prescription->renewalHistory()[0]->renewedByUserId());
        self::assertSame(self::NEW_ID, $prescription->renewalHistory()[0]->id()->toString());
    }

    public function testCancellingPassesTheReasonThrough(): void
    {
        $prescription = $this->issuedWithRenewals(0);
        $this->prescriptions->expects(self::once())->method('get')->willReturn($prescription);
        $this->prescriptions->expects(self::once())->method('save')->with($prescription);

        (new CancelPrescriptionHandler($this->prescriptions, $this->clock))(
            new CancelPrescription(self::CLINIC_ID, self::PRESCRIPTION_ID, 'Doublon'),
        );

        self::assertSame(PrescriptionStatus::CANCELLED, $prescription->status());
        self::assertSame('Doublon', $prescription->cancellationReason());
    }

    public function testTheRenewalAllowanceIsSetOnTheDraft(): void
    {
        $prescription = $this->draftWithOneLine();
        $this->prescriptions->expects(self::once())->method('get')->willReturn($prescription);
        $this->prescriptions->expects(self::once())->method('save')->with($prescription);

        (new AllowPrescriptionRenewalsHandler($this->prescriptions, $this->clock))(
            new AllowPrescriptionRenewals(self::CLINIC_ID, self::PRESCRIPTION_ID, 3),
        );

        self::assertSame(3, $prescription->renewals()->allowed());
    }

    public function testNotesAreSetOnTheDraft(): void
    {
        $prescription = $this->draftWithOneLine();
        $this->prescriptions->expects(self::once())->method('get')->willReturn($prescription);
        $this->prescriptions->expects(self::once())->method('save')->with($prescription);

        (new SetPrescriptionNotesHandler($this->prescriptions, $this->clock))(
            new SetPrescriptionNotes(self::CLINIC_ID, self::PRESCRIPTION_ID, '  Au cours du repas  '),
        );

        self::assertSame('Au cours du repas', $prescription->notes());
    }

    public function testRemovingALineDropsItFromTheDraft(): void
    {
        $prescription = $this->draftWithOneLine();
        $this->prescriptions->expects(self::once())->method('get')->willReturn($prescription);
        $this->prescriptions->expects(self::once())->method('save')->with($prescription);

        (new RemovePrescriptionLineHandler($this->prescriptions, $this->clock))(
            new RemovePrescriptionLine(self::CLINIC_ID, self::PRESCRIPTION_ID, self::LINE_ID),
        );

        self::assertSame([], $prescription->lines());
    }

    private function draftWithOneLine(): Prescription
    {
        $prescription = Prescription::draft(
            id: PrescriptionId::fromString(self::PRESCRIPTION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            consultationId: ConsultationId::fromString(self::CONSULTATION_ID),
            prescriber: Prescriber::create(
                PrescriberId::fromString(self::PRESCRIBER_ID),
                'Rousseau',
                'FR-12345',
                'DR',
            ),
            patient: PatientSnapshot::create(null, 'Rex'),
            owner: OwnerSnapshot::unknown(),
            now: $this->now(),
        );

        $prescription->addLine(
            PrescriptionLine::create(
                id: PrescriptionLineId::fromString(self::LINE_ID),
                articleId: null,
                code: 'MELOXIDYL-1',
                label: 'Meloxidyl',
                pharmaceuticalForm: 'Suspension buvable',
                activeSubstances: ['Méloxicam'],
                dose: '0,1 mg/kg',
                frequency: '1×/j',
                durationDays: 7,
                route: 'per os',
                quantity: '1',
                prescriptionClass: DrugPrescriptionClass::RX,
            ),
            $this->now(),
        );

        return $prescription;
    }

    private function issuedWithRenewals(int $allowance): Prescription
    {
        $prescription = $this->draftWithOneLine();
        $prescription->allowRenewals($allowance, $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 1), $this->now());

        return $prescription;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
