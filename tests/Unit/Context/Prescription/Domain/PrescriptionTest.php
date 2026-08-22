<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Domain;

use App\Context\Prescription\Domain\Event\PrescriptionCancelled;
use App\Context\Prescription\Domain\Event\PrescriptionDrafted;
use App\Context\Prescription\Domain\Event\PrescriptionIssued;
use App\Context\Prescription\Domain\Event\PrescriptionRenewed;
use App\Context\Prescription\Domain\Exception\PrescriberCannotSignException;
use App\Context\Prescription\Domain\Exception\PrescriptionCannotBeCancelledException;
use App\Context\Prescription\Domain\Exception\PrescriptionCannotBeRenewedException;
use App\Context\Prescription\Domain\Exception\PrescriptionHasNoLinesException;
use App\Context\Prescription\Domain\Exception\PrescriptionNotEditableException;
use App\Context\Prescription\Domain\Prescription;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\DrugPrescriptionClass;
use App\Context\Prescription\Domain\ValueObject\OwnerId;
use App\Context\Prescription\Domain\ValueObject\OwnerSnapshot;
use App\Context\Prescription\Domain\ValueObject\PatientSnapshot;
use App\Context\Prescription\Domain\ValueObject\Prescriber;
use App\Context\Prescription\Domain\ValueObject\PrescriberId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLine;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLineId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionNumber;
use App\Context\Prescription\Domain\ValueObject\PrescriptionStatus;
use App\Context\Prescription\Domain\ValueObject\Renewal;
use App\Context\Prescription\Domain\ValueObject\RenewalId;
use App\Context\Prescription\Domain\ValueObject\Renewals;
use App\Context\Prescription\Domain\ValueObject\Validity;
use PHPUnit\Framework\TestCase;

final class PrescriptionTest extends TestCase
{
    private const string PRESCRIPTION_ID = '01960000-0000-7000-8000-0000000000a1';
    private const string CLINIC_ID       = '01960000-0000-7000-8000-0000000000a2';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-0000000000a3';
    private const string PRESCRIBER_ID   = '01960000-0000-7000-8000-0000000000a4';
    private const string OWNER_ID        = '01960000-0000-7000-8000-0000000000a5';

    public function testADraftedPrescriptionRecordsItsCreation(): void
    {
        $prescription = $this->draft();

        self::assertSame(PrescriptionStatus::DRAFT, $prescription->status());
        self::assertNull($prescription->number());
        self::assertNull($prescription->validity());
        self::assertSame(self::CONSULTATION_ID, $prescription->consultationId()->toString());
        self::assertSame(self::CLINIC_ID, $prescription->clinicId()->toString());
        self::assertSame('Rousseau', $prescription->prescriber()->displayName());
        self::assertSame('Rex', $prescription->patient()->name());
        self::assertSame('Sophie Dupont', $prescription->owner()->displayName());
        self::assertSame(0, $prescription->renewals()->allowed());
        self::assertSame([], $prescription->renewalHistory());

        $events = $prescription->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PrescriptionDrafted::class, $events[0]);
    }

    public function testSigningTakesANumberAndStartsTheValidity(): void
    {
        $prescription = $this->draftWithOneLine();
        (void) $prescription->pullDomainEvents();

        $prescription->issue(PrescriptionNumber::of(2026, 42), $this->now());

        self::assertSame(PrescriptionStatus::ISSUED, $prescription->status());
        self::assertSame('ORD-2026-000042', $prescription->number()?->toString());
        self::assertSame(
            $this->now()->format('Y-m-d'),
            $prescription->validity()?->issuedAt()->format('Y-m-d'),
        );
        // An ordinary prescription runs a year.
        self::assertSame('2027-08-22', $prescription->validity()->validUntil()->format('Y-m-d'));
        self::assertTrue($prescription->isDispensableAt($this->now()));
        self::assertFalse($prescription->isExpiredAt($this->now()));

        $events = $prescription->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PrescriptionIssued::class, $events[0]);
    }

    public function testAPrescriptionWithoutMedicinesCannotBeSigned(): void
    {
        $prescription = $this->draft();

        $this->expectException(PrescriptionHasNoLinesException::class);
        $this->expectExceptionMessage('cannot be issued without a single medicine');

        $prescription->issue(PrescriptionNumber::of(2026, 1), $this->now());
    }

    public function testAPractitionerWithoutARegistrationNumberCannotSign(): void
    {
        $prescription = $this->draftWithOneLine(
            prescriber: Prescriber::create(PrescriberId::fromString(self::PRESCRIBER_ID), 'Martin'),
        );

        $this->expectException(PrescriberCannotSignException::class);
        $this->expectExceptionMessage('has no registration number and cannot sign');

        $prescription->issue(PrescriptionNumber::of(2026, 1), $this->now());
    }

    public function testASignedPrescriptionCanNoLongerBeEdited(): void
    {
        $prescription = $this->issued();

        $this->expectException(PrescriptionNotEditableException::class);
        $this->expectExceptionMessage('can no longer be edited: it is ISSUED');

        $prescription->addLine($this->line(id: '01960000-0000-7000-8000-0000000000b2'), $this->now());
    }

    public function testMedicinesCanBeDroppedWhileItIsADraft(): void
    {
        $prescription = $this->draft();
        $kept         = $this->line();
        $dropped      = $this->line(id: '01960000-0000-7000-8000-0000000000b2', label: 'Cerenia');
        $prescription->addLine($kept, $this->now());
        $prescription->addLine($dropped, $this->now());

        $prescription->removeLine($dropped->id(), $this->now());

        self::assertCount(1, $prescription->lines());
        self::assertSame('Meloxidyl', $prescription->lines()[0]->label());
    }

    public function testNotesAreTrimmedAndEmptiedToNull(): void
    {
        $prescription = $this->draft();

        $prescription->setNotes('  Au cours du repas  ', $this->now());
        self::assertSame('Au cours du repas', $prescription->notes());

        $prescription->setNotes('   ', $this->now());
        self::assertNull($prescription->notes());

        $prescription->setNotes(null, $this->now());
        self::assertNull($prescription->notes());
    }

    public function testNotesCannotChangeOnceSigned(): void
    {
        $prescription = $this->issued();

        $this->expectException(PrescriptionNotEditableException::class);

        $prescription->setNotes('Trop tard', $this->now());
    }

    public function testARenewalAllowanceCannotBeSetOnceSigned(): void
    {
        $prescription = $this->issued();

        $this->expectException(PrescriptionNotEditableException::class);

        $prescription->allowRenewals(2, $this->now());
    }

    // ── The controlled-substance regime ──

    public function testOneControlledMedicineShortensTheWholePrescription(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line(), $this->now());
        $prescription->addLine(
            $this->line(
                id: '01960000-0000-7000-8000-0000000000b3',
                label: 'Medetomidine',
                class: DrugPrescriptionClass::RX_CONTROLLED,
            ),
            $this->now(),
        );

        self::assertTrue($prescription->carriesControlledSubstance());
        self::assertSame(28, $prescription->validityDays());

        $prescription->issue(PrescriptionNumber::of(2026, 1), $this->now());

        self::assertSame('2026-09-19', $prescription->validity()?->validUntil()->format('Y-m-d'));
    }

    public function testAddingAControlledMedicineWipesAnyRenewalAllowance(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line(), $this->now());
        $prescription->allowRenewals(3, $this->now());
        self::assertSame(3, $prescription->renewals()->allowed());

        $prescription->addLine(
            $this->line(
                id: '01960000-0000-7000-8000-0000000000b4',
                label: 'Ketamine',
                class: DrugPrescriptionClass::RX_NARCOTIC,
            ),
            $this->now(),
        );

        self::assertSame(0, $prescription->renewals()->allowed());
    }

    public function testARenewalAllowanceIsRefusedOnAControlledPrescription(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line(class: DrugPrescriptionClass::RX_NARCOTIC), $this->now());

        $this->expectException(PrescriptionCannotBeRenewedException::class);
        $this->expectExceptionMessage('it carries a controlled substance');

        $prescription->allowRenewals(1, $this->now());
    }

    public function testClearingTheAllowanceIsAlwaysAllowed(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line(class: DrugPrescriptionClass::RX_NARCOTIC), $this->now());

        $prescription->allowRenewals(0, $this->now());

        self::assertSame(0, $prescription->renewals()->allowed());
    }

    public function testAControlledPrescriptionCannotBeRenewed(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line(class: DrugPrescriptionClass::RX_NARCOTIC), $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 1), $this->now());

        $this->expectException(PrescriptionCannotBeRenewedException::class);
        $this->expectExceptionMessage('it carries a controlled substance');

        $prescription->renew(RenewalId::fromString($this->renewalId()), null, $this->now());
    }

    // ── Renewals ──

    public function testRenewingPushesTheValidityOutAndKeepsATrace(): void
    {
        $prescription = $this->draftWithOneLine();
        $prescription->allowRenewals(2, $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 1), $this->now());
        (void) $prescription->pullDomainEvents();

        $prescription->renew(RenewalId::fromString($this->renewalId()), 'user-1', $this->now());

        self::assertSame(1, $prescription->renewals()->used());
        self::assertSame(1, $prescription->renewals()->remaining());
        self::assertSame('2028-08-21', $prescription->validity()?->validUntil()->format('Y-m-d'));
        self::assertCount(1, $prescription->renewalHistory());
        self::assertSame('user-1', $prescription->renewalHistory()[0]->renewedByUserId());

        $events = $prescription->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PrescriptionRenewed::class, $events[0]);
    }

    public function testRenewingBeyondTheAllowanceIsRefused(): void
    {
        $prescription = $this->draftWithOneLine();
        $prescription->allowRenewals(1, $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 1), $this->now());
        $prescription->renew(RenewalId::fromString($this->renewalId()), null, $this->now());

        $this->expectException(PrescriptionCannotBeRenewedException::class);
        $this->expectExceptionMessage('no renewal is left');

        $prescription->renew(RenewalId::fromString($this->renewalId('c2')), null, $this->now());
    }

    public function testADraftCannotBeRenewed(): void
    {
        $prescription = $this->draftWithOneLine();

        $this->expectException(PrescriptionCannotBeRenewedException::class);
        $this->expectExceptionMessage('it is DRAFT');

        $prescription->renew(RenewalId::fromString($this->renewalId()), null, $this->now());
    }

    public function testAnExpiredPrescriptionCannotBeRenewed(): void
    {
        $prescription = $this->draftWithOneLine();
        $prescription->allowRenewals(2, $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 1), $this->now());

        $later = $this->now()->modify('+2 years');

        self::assertTrue($prescription->isExpiredAt($later));
        self::assertFalse($prescription->isDispensableAt($later));

        $this->expectException(PrescriptionCannotBeRenewedException::class);
        $this->expectExceptionMessage('it has expired');

        $prescription->renew(RenewalId::fromString($this->renewalId()), null, $later);
    }

    public function testAnIssuedPrescriptionWithoutValidityCannotBeRenewed(): void
    {
        // Only reachable from a corrupted row, but the guard has to hold.
        $prescription = Prescription::reconstitute(
            id: PrescriptionId::fromString(self::PRESCRIPTION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            consultationId: ConsultationId::fromString(self::CONSULTATION_ID),
            number: PrescriptionNumber::of(2026, 1),
            status: PrescriptionStatus::ISSUED,
            prescriber: $this->prescriber(),
            patient: $this->patient(),
            owner: $this->owner(),
            lines: [$this->line()],
            validity: null,
            renewals: Renewals::of(1),
            renewalHistory: [],
            notes: null,
            createdAt: $this->now(),
            updatedAt: $this->now(),
            cancelledAt: null,
            cancellationReason: null,
        );

        $this->expectException(PrescriptionCannotBeRenewedException::class);
        $this->expectExceptionMessage('it has no validity');

        $prescription->renew(RenewalId::fromString($this->renewalId()), null, $this->now());
    }

    // ── Cancellation ──

    public function testCancellingRecordsTheReason(): void
    {
        $prescription = $this->issued();
        (void) $prescription->pullDomainEvents();

        $prescription->cancel('Erreur de posologie', $this->now());

        self::assertSame(PrescriptionStatus::CANCELLED, $prescription->status());
        self::assertSame('Erreur de posologie', $prescription->cancellationReason());
        self::assertSame(
            $this->now()->format('Y-m-d'),
            $prescription->cancelledAt()?->format('Y-m-d'),
        );
        self::assertFalse($prescription->isDispensableAt($this->now()));

        $events = $prescription->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PrescriptionCancelled::class, $events[0]);
    }

    public function testADraftCanBeCancelledToo(): void
    {
        $prescription = $this->draftWithOneLine();

        $prescription->cancel('Abandonnée', $this->now());

        self::assertSame(PrescriptionStatus::CANCELLED, $prescription->status());
    }

    public function testAnAlreadyCancelledPrescriptionCannotBeCancelledTwice(): void
    {
        $prescription = $this->issued();
        $prescription->cancel('Erreur', $this->now());

        $this->expectException(PrescriptionCannotBeCancelledException::class);
        $this->expectExceptionMessage('it is already cancelled');

        $prescription->cancel('Encore', $this->now());
    }

    public function testCancellingNeedsAReason(): void
    {
        $prescription = $this->issued();

        $this->expectException(PrescriptionCannotBeCancelledException::class);
        $this->expectExceptionMessage('a reason is required');

        $prescription->cancel('   ', $this->now());
    }

    public function testACancelledPrescriptionIsNeitherDispensableNorExpired(): void
    {
        $prescription = $this->issued();
        $prescription->cancel('Erreur', $this->now());

        $later = $this->now()->modify('+2 years');

        self::assertFalse($prescription->isDispensableAt($later));
        self::assertFalse($prescription->isExpiredAt($later));
    }

    public function testItIsReconstitutedWithEverythingItHeld(): void
    {
        $prescription = Prescription::reconstitute(
            id: PrescriptionId::fromString(self::PRESCRIPTION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            consultationId: ConsultationId::fromString(self::CONSULTATION_ID),
            number: PrescriptionNumber::of(2026, 7),
            status: PrescriptionStatus::ISSUED,
            prescriber: $this->prescriber(),
            patient: $this->patient(),
            owner: $this->owner(),
            lines: [$this->line()],
            validity: Validity::of($this->now(), 365),
            renewals: Renewals::reconstitute(2, 1),
            renewalHistory: [
                Renewal::create(
                    RenewalId::fromString($this->renewalId()),
                    $this->now(),
                    $this->now()->modify('+2 years'),
                    'user-1',
                ),
            ],
            notes: 'Au cours du repas',
            createdAt: $this->now(),
            updatedAt: $this->now(),
            cancelledAt: null,
            cancellationReason: null,
        );

        self::assertSame('ORD-2026-000007', $prescription->number()?->toString());
        self::assertSame('Au cours du repas', $prescription->notes());
        self::assertSame(1, $prescription->renewals()->remaining());
        self::assertCount(1, $prescription->renewalHistory());
        self::assertSame(self::PRESCRIPTION_ID, $prescription->id()->toString());
        self::assertSame(
            $this->now()->format('Y-m-d'),
            $prescription->createdAt()->format('Y-m-d'),
        );
        self::assertSame(
            $this->now()->format('Y-m-d'),
            $prescription->updatedAt()->format('Y-m-d'),
        );
        self::assertNull($prescription->cancelledAt());
        self::assertNull($prescription->cancellationReason());
    }

    private function draft(?Prescriber $prescriber = null): Prescription
    {
        return Prescription::draft(
            id: PrescriptionId::fromString(self::PRESCRIPTION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            consultationId: ConsultationId::fromString(self::CONSULTATION_ID),
            prescriber: $prescriber ?? $this->prescriber(),
            patient: $this->patient(),
            owner: $this->owner(),
            now: $this->now(),
        );
    }

    private function draftWithOneLine(?Prescriber $prescriber = null): Prescription
    {
        $prescription = $this->draft($prescriber);
        $prescription->addLine($this->line(), $this->now());

        return $prescription;
    }

    private function issued(): Prescription
    {
        $prescription = $this->draftWithOneLine();
        $prescription->issue(PrescriptionNumber::of(2026, 1), $this->now());

        return $prescription;
    }

    private function prescriber(): Prescriber
    {
        return Prescriber::create(
            PrescriberId::fromString(self::PRESCRIBER_ID),
            'Rousseau',
            'FR-12345',
            'DR',
        );
    }

    private function patient(): PatientSnapshot
    {
        return PatientSnapshot::create(
            animalId: '01960000-0000-7000-8000-0000000000a6',
            name: 'Rex',
            species: 'dog',
            breed: 'Labrador',
            birthDate: new \DateTimeImmutable('2016-05-01'),
            weightKg: '28.5',
            microchipNumber: '250269801234567',
        );
    }

    private function owner(): OwnerSnapshot
    {
        return OwnerSnapshot::create(
            OwnerId::fromString(self::OWNER_ID),
            'Sophie Dupont',
            ['12 rue des Lilas', '75011 Paris'],
        );
    }

    private function line(
        string $id = '01960000-0000-7000-8000-0000000000b1',
        string $label = 'Meloxidyl',
        DrugPrescriptionClass $class = DrugPrescriptionClass::RX,
    ): PrescriptionLine {
        return PrescriptionLine::create(
            id: PrescriptionLineId::fromString($id),
            articleId: '01960000-0000-7000-8000-0000000000c1',
            code: 'MELOXIDYL-1',
            label: $label,
            pharmaceuticalForm: 'Suspension buvable',
            activeSubstances: ['Méloxicam'],
            dose: '0,1 mg/kg',
            frequency: '1×/j',
            durationDays: 7,
            route: 'per os',
            quantity: '1',
            prescriptionClass: $class,
        );
    }

    private function renewalId(string $suffix = 'c1'): string
    {
        return '01960000-0000-7000-8000-0000000000' . $suffix;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
