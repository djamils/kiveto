<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Domain\ValueObject;

use App\Context\Prescription\Domain\Exception\InvalidOwnerSnapshotException;
use App\Context\Prescription\Domain\Exception\InvalidPatientSnapshotException;
use App\Context\Prescription\Domain\Exception\InvalidPrescriberException;
use App\Context\Prescription\Domain\Exception\InvalidPrescriptionLineException;
use App\Context\Prescription\Domain\Exception\InvalidPrescriptionNumberException;
use App\Context\Prescription\Domain\Exception\InvalidRenewalsException;
use App\Context\Prescription\Domain\Exception\InvalidValidityException;
use App\Context\Prescription\Domain\ValueObject\DrugPrescriptionClass;
use App\Context\Prescription\Domain\ValueObject\OwnerId;
use App\Context\Prescription\Domain\ValueObject\OwnerSnapshot;
use App\Context\Prescription\Domain\ValueObject\PatientSnapshot;
use App\Context\Prescription\Domain\ValueObject\Prescriber;
use App\Context\Prescription\Domain\ValueObject\PrescriberId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLine;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLineId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionNumber;
use App\Context\Prescription\Domain\ValueObject\PrescriptionStatus;
use App\Context\Prescription\Domain\ValueObject\Renewal;
use App\Context\Prescription\Domain\ValueObject\RenewalId;
use App\Context\Prescription\Domain\ValueObject\Renewals;
use App\Context\Prescription\Domain\ValueObject\Validity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrescriptionValueObjectsTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-0000000000d1';

    // ── PrescriptionNumber ──

    public function testANumberIsBuiltFromAYearAndASequence(): void
    {
        $number = PrescriptionNumber::of(2026, 42);

        self::assertSame('ORD-2026-000042', $number->toString());
        self::assertSame(2026, $number->year());
        self::assertSame(42, $number->sequence());
        self::assertTrue($number->equals(PrescriptionNumber::fromString('ORD-2026-000042')));
        self::assertFalse($number->equals(PrescriptionNumber::of(2026, 43)));
    }

    #[DataProvider('provideAMalformedNumberIsRejectedCases')]
    public function testAMalformedNumberIsRejected(string $value): void
    {
        $this->expectException(InvalidPrescriptionNumberException::class);
        $this->expectExceptionMessage('Expected ORD-YYYY-NNNNNN');

        PrescriptionNumber::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAMalformedNumberIsRejectedCases(): iterable
    {
        yield 'empty' => [''];
        yield 'invoice prefix' => ['FAC-2026-000042'];
        yield 'short sequence' => ['ORD-2026-42'];
        yield 'lowercase' => ['ord-2026-000042'];
    }

    public function testAnOutOfRangeNumberIsRejected(): void
    {
        $this->expectException(InvalidPrescriptionNumberException::class);

        PrescriptionNumber::of(99, 1);
    }

    public function testASequenceOutsideSixDigitsIsRejected(): void
    {
        $this->expectException(InvalidPrescriptionNumberException::class);

        PrescriptionNumber::of(2026, 0);
    }

    public function testASequenceAboveAMillionIsRejected(): void
    {
        $this->expectException(InvalidPrescriptionNumberException::class);

        PrescriptionNumber::of(2026, 1000000);
    }

    // ── Prescriber ──

    public function testAPrescriberWithANumberCanSign(): void
    {
        $prescriber = Prescriber::create(
            PrescriberId::fromString(self::ID),
            '  Rousseau  ',
            '  FR-12345  ',
            '  DR  ',
        );

        self::assertTrue($prescriber->canSign());
        self::assertSame('Rousseau', $prescriber->displayName());
        self::assertSame('FR-12345', $prescriber->registrationNumber());
        self::assertSame('DR', $prescriber->professionalTitle());
        self::assertSame('Dr Rousseau', $prescriber->printedName());
        self::assertSame(self::ID, $prescriber->userId()->toString());
    }

    public function testAPrescriberWithoutATitlePrintsTheirNameAlone(): void
    {
        $prescriber = Prescriber::create(PrescriberId::fromString(self::ID), 'Martin', 'FR-1', '  ');

        self::assertSame('Martin', $prescriber->printedName());
        self::assertNull($prescriber->professionalTitle());
    }

    public function testAPrescriberWithoutARegistrationNumberCannotSign(): void
    {
        $prescriber = Prescriber::create(PrescriberId::fromString(self::ID), 'Martin');

        self::assertFalse($prescriber->canSign());
        self::assertNull($prescriber->registrationNumber());
    }

    public function testAnUnnamedPrescriberIsRejected(): void
    {
        $this->expectException(InvalidPrescriberException::class);
        $this->expectExceptionMessage('The prescriber must be named.');

        Prescriber::create(PrescriberId::fromString(self::ID), '   ');
    }

    // ── PatientSnapshot ──

    public function testAPatientKeepsWhatIdentifiesIt(): void
    {
        $patient = PatientSnapshot::create(
            animalId: self::ID,
            name: '  Rex  ',
            species: '  dog  ',
            breed: '  Labrador  ',
            birthDate: new \DateTimeImmutable('2016-05-01'),
            weightKg: '28.5',
            microchipNumber: '  250269801234567  ',
        );

        self::assertSame('Rex', $patient->name());
        self::assertSame('dog', $patient->species());
        self::assertSame('Labrador', $patient->breed());
        self::assertSame('28.5', $patient->weightKg());
        self::assertSame('250269801234567', $patient->microchipNumber());
        self::assertSame(self::ID, $patient->animalId());
        self::assertSame('2016-05-01', $patient->birthDate()?->format('Y-m-d'));
    }

    public function testBlankPatientFieldsBecomeNull(): void
    {
        $patient = PatientSnapshot::create(null, 'Rex', '  ', '  ', null, null, '  ');

        self::assertNull($patient->animalId());
        self::assertNull($patient->species());
        self::assertNull($patient->breed());
        self::assertNull($patient->weightKg());
        self::assertNull($patient->microchipNumber());
        self::assertNull($patient->birthDate());
    }

    public function testAnUnnamedPatientIsRejected(): void
    {
        $this->expectException(InvalidPatientSnapshotException::class);
        $this->expectExceptionMessage('The patient must be named');

        PatientSnapshot::create(null, '   ');
    }

    public function testAMalformedWeightIsRejected(): void
    {
        $this->expectException(InvalidPatientSnapshotException::class);
        $this->expectExceptionMessage('Invalid weight "vingt"');

        PatientSnapshot::create(null, 'Rex', weightKg: 'vingt');
    }

    // ── OwnerSnapshot ──

    public function testAnOwnerKeepsItsAddress(): void
    {
        $owner = OwnerSnapshot::create(
            OwnerId::fromString(self::ID),
            '  Sophie Dupont  ',
            ['12 rue des Lilas', '  ', '75011 Paris'],
        );

        self::assertSame('Sophie Dupont', $owner->displayName());
        self::assertSame(['12 rue des Lilas', '75011 Paris'], $owner->addressLines());
        self::assertSame(self::ID, $owner->clientId()?->toString());
    }

    public function testAnUnknownOwnerStillNamesSomeone(): void
    {
        $owner = OwnerSnapshot::unknown();

        self::assertSame('Détenteur non identifié', $owner->displayName());
        self::assertNull($owner->clientId());
        self::assertSame([], $owner->addressLines());
    }

    public function testAnUnnamedOwnerIsRejected(): void
    {
        $this->expectException(InvalidOwnerSnapshotException::class);
        $this->expectExceptionMessage('The owner must be named');

        OwnerSnapshot::create(null, '  ');
    }

    // ── PrescriptionLine ──

    public function testALineKeepsEverythingItPrints(): void
    {
        $line = $this->line();

        self::assertSame('Meloxidyl', $line->label());
        self::assertSame('MELOXIDYL-1', $line->code());
        self::assertSame('Suspension buvable', $line->pharmaceuticalForm());
        self::assertSame(['Méloxicam'], $line->activeSubstances());
        self::assertSame('0,1 mg/kg', $line->dose());
        self::assertSame('1×/j', $line->frequency());
        self::assertSame(7, $line->durationDays());
        self::assertSame('per os', $line->route());
        self::assertSame('2', $line->quantity());
        self::assertSame('article-1', $line->articleId());
        self::assertSame('Avec de la nourriture', $line->instructions());
        self::assertFalse($line->isControlled());
        self::assertSame(DrugPrescriptionClass::RX, $line->prescriptionClass());
        self::assertSame(self::ID, $line->id()->toString());
    }

    public function testThePosologyReadsAsOneSentence(): void
    {
        self::assertSame('0,1 mg/kg, 1×/j, pendant 7 jours, voie per os', $this->line()->posology());
        self::assertSame(
            '1 comprimé, pendant 1 jour',
            $this->line(dose: '1 comprimé', frequency: null, days: 1, route: null)->posology(),
        );
        self::assertSame('', $this->line(dose: null, frequency: null, days: null, route: null)->posology());
    }

    public function testBlankLineFieldsBecomeNull(): void
    {
        $line = $this->line(code: '  ', form: '  ', dose: '  ', instructions: '  ', substances: ['  ']);

        self::assertNull($line->code());
        self::assertNull($line->pharmaceuticalForm());
        self::assertNull($line->dose());
        self::assertNull($line->instructions());
        self::assertSame([], $line->activeSubstances());
    }

    public function testAnUnnamedMedicineIsRejected(): void
    {
        $this->expectException(InvalidPrescriptionLineException::class);
        $this->expectExceptionMessage('needs a medicine name');

        $this->line(label: '  ');
    }

    public function testAMalformedQuantityIsRejected(): void
    {
        $this->expectException(InvalidPrescriptionLineException::class);
        $this->expectExceptionMessage('expected a positive decimal');

        $this->line(quantity: '-2');
    }

    public function testAZeroQuantityIsRejected(): void
    {
        $this->expectException(InvalidPrescriptionLineException::class);
        $this->expectExceptionMessage('cannot have a zero quantity');

        $this->line(quantity: '0');
    }

    public function testANonPositiveDurationIsRejected(): void
    {
        $this->expectException(InvalidPrescriptionLineException::class);
        $this->expectExceptionMessage('lasts at least one day');

        $this->line(days: 0);
    }

    // ── Validity ──

    public function testAValidityRunsFromItsIssueDate(): void
    {
        $validity = Validity::of(new \DateTimeImmutable('2026-08-22 10:00:00'), 28);

        self::assertSame('2026-09-19', $validity->validUntil()->format('Y-m-d'));
        self::assertSame(28, $validity->spanDays());
        self::assertFalse($validity->isExpiredAt(new \DateTimeImmutable('2026-09-19 09:00:00')));
        self::assertTrue($validity->isExpiredAt(new \DateTimeImmutable('2026-09-20 11:00:00')));
        self::assertSame('2026-08-22', $validity->issuedAt()->format('Y-m-d'));
    }

    public function testExtendingPushesTheEndDateByOneMoreSpan(): void
    {
        $extended = Validity::of(new \DateTimeImmutable('2026-08-22 10:00:00'), 30)->extended();

        self::assertSame('2026-10-21', $extended->validUntil()->format('Y-m-d'));
        self::assertSame(30, $extended->spanDays());
    }

    public function testAValidityOfLessThanADayIsRejected(): void
    {
        $this->expectException(InvalidValidityException::class);
        $this->expectExceptionMessage('valid at least one day');

        Validity::of(new \DateTimeImmutable(), 0);
    }

    public function testAValidityIsReconstitutedFromItsDates(): void
    {
        $validity = Validity::reconstitute(
            new \DateTimeImmutable('2026-08-22'),
            new \DateTimeImmutable('2027-08-22'),
            365,
        );

        self::assertSame('2027-08-22', $validity->validUntil()->format('Y-m-d'));
        self::assertSame(365, $validity->spanDays());

        // A stored span of zero would break every later renewal.
        self::assertSame(
            1,
            Validity::reconstitute(new \DateTimeImmutable('2026-08-22'), new \DateTimeImmutable('2026-08-23'), 0)
                ->spanDays(),
        );
    }

    public function testAValidityEndingBeforeItStartsIsRejected(): void
    {
        $this->expectException(InvalidValidityException::class);
        $this->expectExceptionMessage('cannot expire before it is issued');

        Validity::reconstitute(new \DateTimeImmutable('2026-08-22'), new \DateTimeImmutable('2026-08-01'), 30);
    }

    // ── Renewals ──

    public function testAnAllowanceIsConsumedOneAtATime(): void
    {
        $renewals = Renewals::of(2);

        self::assertSame(2, $renewals->remaining());
        self::assertTrue($renewals->hasRemaining());

        $renewals = $renewals->consumeOne();

        self::assertSame(1, $renewals->used());
        self::assertSame(1, $renewals->remaining());

        self::assertFalse($renewals->consumeOne()->hasRemaining());
    }

    public function testNoneMeansNoRenewal(): void
    {
        $renewals = Renewals::none();

        self::assertSame(0, $renewals->allowed());
        self::assertFalse($renewals->hasRemaining());
    }

    public function testConsumingBeyondTheAllowanceIsRejected(): void
    {
        $this->expectException(InvalidRenewalsException::class);
        $this->expectExceptionMessage('No renewal left');

        Renewals::none()->consumeOne();
    }

    public function testANegativeAllowanceIsRejected(): void
    {
        $this->expectException(InvalidRenewalsException::class);
        $this->expectExceptionMessage('cannot be negative');

        Renewals::of(-1);
    }

    public function testAnUnreasonableAllowanceIsRejected(): void
    {
        $this->expectException(InvalidRenewalsException::class);
        $this->expectExceptionMessage('more than 12 times');

        Renewals::of(13);
    }

    public function testMoreUsedThanAllowedIsRejected(): void
    {
        $this->expectException(InvalidRenewalsException::class);
        $this->expectExceptionMessage('cannot exceed the allowance');

        Renewals::reconstitute(1, 2);
    }

    public function testARenewalKeepsWhenAndUntilWhen(): void
    {
        $renewal = Renewal::create(
            RenewalId::fromString(self::ID),
            new \DateTimeImmutable('2026-08-22'),
            new \DateTimeImmutable('2027-08-22'),
            'user-1',
        );

        self::assertSame(self::ID, $renewal->id()->toString());
        self::assertSame('2026-08-22', $renewal->renewedAt()->format('Y-m-d'));
        self::assertSame('2027-08-22', $renewal->newValidUntil()->format('Y-m-d'));
        self::assertSame('user-1', $renewal->renewedByUserId());
        self::assertNull(
            Renewal::create(RenewalId::fromString(self::ID), new \DateTimeImmutable(), new \DateTimeImmutable())
                ->renewedByUserId(),
        );
    }

    // ── Enums ──

    public function testEveryStatusIsLabelled(): void
    {
        foreach (PrescriptionStatus::cases() as $status) {
            self::assertNotSame('', $status->label());
        }

        self::assertSame('Brouillon', PrescriptionStatus::DRAFT->label());
        self::assertSame('Émise', PrescriptionStatus::ISSUED->label());
        self::assertSame('Annulée', PrescriptionStatus::CANCELLED->label());
        self::assertTrue(PrescriptionStatus::DRAFT->isEditable());
        self::assertFalse(PrescriptionStatus::ISSUED->isEditable());
        self::assertFalse(PrescriptionStatus::CANCELLED->isEditable());
    }

    public function testOnlyControlledClassesFallUnderTheStrictRegime(): void
    {
        foreach (DrugPrescriptionClass::cases() as $class) {
            self::assertNotSame('', $class->label());
        }

        self::assertFalse(DrugPrescriptionClass::NONE->isControlled());
        self::assertFalse(DrugPrescriptionClass::RX->isControlled());
        self::assertTrue(DrugPrescriptionClass::RX_CONTROLLED->isControlled());
        self::assertTrue(DrugPrescriptionClass::RX_NARCOTIC->isControlled());
        self::assertSame('Stupéfiant', DrugPrescriptionClass::RX_NARCOTIC->label());
        self::assertSame('Sans ordonnance', DrugPrescriptionClass::NONE->label());
        self::assertSame('Sur ordonnance', DrugPrescriptionClass::RX->label());
        self::assertSame('Substance contrôlée', DrugPrescriptionClass::RX_CONTROLLED->label());
    }

    /**
     * @param list<string> $substances
     */
    private function line(
        string $label = 'Meloxidyl',
        ?string $code = 'MELOXIDYL-1',
        ?string $form = 'Suspension buvable',
        array $substances = ['Méloxicam'],
        ?string $dose = '0,1 mg/kg',
        ?string $frequency = '1×/j',
        ?int $days = 7,
        ?string $route = 'per os',
        string $quantity = '2',
        ?string $instructions = 'Avec de la nourriture',
    ): PrescriptionLine {
        return PrescriptionLine::create(
            id: PrescriptionLineId::fromString(self::ID),
            articleId: 'article-1',
            code: $code,
            label: $label,
            pharmaceuticalForm: $form,
            activeSubstances: $substances,
            dose: $dose,
            frequency: $frequency,
            durationDays: $days,
            route: $route,
            quantity: $quantity,
            prescriptionClass: DrugPrescriptionClass::RX,
            instructions: $instructions,
        );
    }
}
