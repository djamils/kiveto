<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Prescription\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Prescription\Application\Port\PrescriptionReadRepositoryInterface;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsCriteria;
use App\Context\Prescription\Domain\Exception\PrescriptionNotFoundException;
use App\Context\Prescription\Domain\Prescription;
use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\Service\PrescriptionNumberGeneratorInterface;
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
use App\Context\Prescription\Domain\ValueObject\RenewalId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The write and read sides against a real database: the ordonnance has to come
 * back exactly as it was signed, and the list has to agree with it.
 */
final class DoctrinePrescriptionRepositoryTest extends KernelTestCase
{
    private const string CLINIC_ID       = '01960000-0000-7000-8000-00000000c001';
    private const string OTHER_CLINIC    = '01960000-0000-7000-8000-00000000c002';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-00000000c003';
    private const string PRESCRIBER_ID   = '01960000-0000-7000-8000-00000000c004';
    private const string CLIENT_ID       = '01960000-0000-7000-8000-00000000c005';

    private PrescriptionRepositoryInterface $prescriptions;
    private PrescriptionReadRepositoryInterface $read;
    private UuidGeneratorInterface $uuids;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $prescriptions = $container->get(PrescriptionRepositoryInterface::class);
        \assert($prescriptions instanceof PrescriptionRepositoryInterface);
        $this->prescriptions = $prescriptions;

        $read = $container->get(PrescriptionReadRepositoryInterface::class);
        \assert($read instanceof PrescriptionReadRepositoryInterface);
        $this->read = $read;

        $uuids = $container->get(UuidGeneratorInterface::class);
        \assert($uuids instanceof UuidGeneratorInterface);
        $this->uuids = $uuids;
    }

    public function testAPrescriptionComesBackAsItWasSigned(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line('Meloxidyl'), $this->now());
        $prescription->addLine($this->line('Synulox'), $this->now());
        $prescription->allowRenewals(2, $this->now());
        $prescription->setNotes('Au cours du repas', $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 77), $this->now());
        $prescription->renew(RenewalId::fromString($this->uuids->generate()), self::PRESCRIBER_ID, $this->now());

        $this->prescriptions->save($prescription);

        $reloaded = $this->prescriptions->get($this->clinic(), $prescription->id());

        self::assertSame('ORD-2026-000077', $reloaded->number()?->toString());
        self::assertSame(PrescriptionStatus::ISSUED, $reloaded->status());
        self::assertCount(2, $reloaded->lines());
        self::assertCount(1, $reloaded->renewalHistory());
        self::assertSame(1, $reloaded->renewals()->used());
        self::assertSame('Au cours du repas', $reloaded->notes());
        self::assertSame('Rousseau', $reloaded->prescriber()->displayName());
        self::assertSame('Rex', $reloaded->patient()->name());
        self::assertSame(['12 rue des Lilas'], $reloaded->owner()->addressLines());
        self::assertSame(['Méloxicam'], $reloaded->lines()[0]->activeSubstances());
        // Issued for a year, renewed once.
        self::assertSame('2028-08-21', $reloaded->validity()?->validUntil()->format('Y-m-d'));
    }

    public function testAControlledPrescriptionKeepsItsShortValidity(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line('Ketamine', DrugPrescriptionClass::RX_NARCOTIC), $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 78), $this->now());

        $this->prescriptions->save($prescription);

        $reloaded = $this->prescriptions->get($this->clinic(), $prescription->id());

        self::assertTrue($reloaded->carriesControlledSubstance());
        self::assertSame(28, $reloaded->validity()?->spanDays());
    }

    public function testSavingTwiceUpdatesRatherThanDuplicates(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line('Meloxidyl'), $this->now());
        $this->prescriptions->save($prescription);

        $prescription->issue(PrescriptionNumber::of(2026, 79), $this->now());
        $this->prescriptions->save($prescription);

        $reloaded = $this->prescriptions->get($this->clinic(), $prescription->id());

        self::assertSame(PrescriptionStatus::ISSUED, $reloaded->status());
        self::assertCount(1, $reloaded->lines());
    }

    public function testARemovedMedicineIsDeletedFromTheRow(): void
    {
        $prescription = $this->draft();
        $kept         = $this->line('Meloxidyl');
        $dropped      = $this->line('Synulox');
        $prescription->addLine($kept, $this->now());
        $prescription->addLine($dropped, $this->now());
        $this->prescriptions->save($prescription);

        $prescription->removeLine($dropped->id(), $this->now());
        $this->prescriptions->save($prescription);

        $reloaded = $this->prescriptions->get($this->clinic(), $prescription->id());

        self::assertCount(1, $reloaded->lines());
        self::assertSame('Meloxidyl', $reloaded->lines()[0]->label());
    }

    public function testASecondSaveOnlyWritesTheNewRenewal(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line('Meloxidyl'), $this->now());
        $prescription->allowRenewals(2, $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 80), $this->now());
        $prescription->renew(RenewalId::fromString($this->uuids->generate()), null, $this->now());
        $this->prescriptions->save($prescription);

        $prescription->renew(RenewalId::fromString($this->uuids->generate()), null, $this->now());
        $this->prescriptions->save($prescription);

        $reloaded = $this->prescriptions->get($this->clinic(), $prescription->id());

        self::assertCount(2, $reloaded->renewalHistory());
        self::assertSame(2, $reloaded->renewals()->used());
    }

    public function testAPrescriptionOfAnotherClinicIsInvisible(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line('Meloxidyl'), $this->now());
        $this->prescriptions->save($prescription);

        self::assertNull(
            $this->prescriptions->findById(ClinicId::fromString(self::OTHER_CLINIC), $prescription->id()),
        );
    }

    public function testAnUnknownPrescriptionIsRejected(): void
    {
        $this->expectException(PrescriptionNotFoundException::class);
        $this->expectExceptionMessage('Prescription not found');

        $this->prescriptions->get($this->clinic(), PrescriptionId::fromString($this->uuids->generate()));
    }

    public function testTheConsultationFindsItsPrescription(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line('Meloxidyl'), $this->now());
        $this->prescriptions->save($prescription);

        $found = $this->prescriptions->findForConsultation(
            $this->clinic(),
            ConsultationId::fromString(self::CONSULTATION_ID),
        );

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($prescription->id()));
    }

    public function testACancelledPrescriptionNoLongerHoldsTheConsultation(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line('Meloxidyl'), $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 81), $this->now());
        $prescription->cancel('Erreur', $this->now());
        $this->prescriptions->save($prescription);

        self::assertNull($this->prescriptions->findForConsultation(
            $this->clinic(),
            ConsultationId::fromString(self::CONSULTATION_ID),
        ));
    }

    public function testAConsultationWithoutAPrescriptionFindsNothing(): void
    {
        self::assertNull($this->prescriptions->findForConsultation(
            $this->clinic(),
            ConsultationId::fromString($this->uuids->generate()),
        ));
    }

    public function testTheNumberSequenceHandsOutConsecutiveNumbers(): void
    {
        $generator = self::getContainer()->get(PrescriptionNumberGeneratorInterface::class);
        \assert($generator instanceof PrescriptionNumberGeneratorInterface);

        $first    = $generator->next($this->clinic(), 2026);
        $second   = $generator->next($this->clinic(), 2026);
        $other    = $generator->next(ClinicId::fromString(self::OTHER_CLINIC), 2026);
        $nextYear = $generator->next($this->clinic(), 2027);

        self::assertSame($first->sequence() + 1, $second->sequence());
        // Each clinic and each year counts on its own.
        self::assertSame(1, $other->sequence());
        self::assertSame(1, $nextYear->sequence());
        self::assertSame(2027, $nextYear->year());
    }

    public function testTheDetailsMirrorTheAggregate(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line('Meloxidyl'), $this->now());
        $prescription->allowRenewals(1, $this->now());
        $prescription->issue(PrescriptionNumber::of(2026, 90), $this->now());
        $prescription->renew(RenewalId::fromString($this->uuids->generate()), null, $this->now());
        $this->prescriptions->save($prescription);

        $details = $this->read->details($this->clinic(), $prescription->id());

        self::assertSame('ORD-2026-000090', $details->number);
        self::assertSame('Émise', $details->statusLabel);
        self::assertSame('Dr Rousseau', $details->prescriberName);
        self::assertSame('FR-12345', $details->prescriberRegistrationNumber);
        self::assertCount(1, $details->lines);
        self::assertCount(1, $details->renewals);
        self::assertSame(0, $details->renewalsRemaining);
        self::assertFalse($details->carriesControlledSubstance);
        self::assertTrue($details->isDispensable);
        self::assertFalse($details->isExpired);
        self::assertSame(365, $details->validityDays);
        self::assertSame('Sur ordonnance', $details->lines[0]->prescriptionClassLabel);
        self::assertSame('0,1 mg/kg, 1×/j, pendant 7 jours, voie per os', $details->lines[0]->posology);
        self::assertSame(self::CONSULTATION_ID, $details->consultationId);
    }

    public function testTheListFiltersOnStatusTermPeriodAndControlledSubstances(): void
    {
        $ordinary = $this->draft();
        $ordinary->addLine($this->line('Meloxidyl'), $this->now());
        $ordinary->issue(PrescriptionNumber::of(2026, 100), $this->now());
        $this->prescriptions->save($ordinary);

        $controlled = $this->draft(consultationId: $this->uuids->generate(), patientName: 'Luna');
        $controlled->addLine($this->line('Ketamine', DrugPrescriptionClass::RX_NARCOTIC), $this->now());
        $controlled->issue(PrescriptionNumber::of(2026, 101), $this->now());
        $this->prescriptions->save($controlled);

        $cancelled = $this->draft(consultationId: $this->uuids->generate(), patientName: 'Minou');
        $cancelled->addLine($this->line('Synulox'), $this->now());
        $cancelled->issue(PrescriptionNumber::of(2026, 102), $this->now());
        $cancelled->cancel('Erreur', $this->now());
        $this->prescriptions->save($cancelled);

        $all = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria());
        self::assertSame(3, $all['total']);

        $issued = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(statuses: ['ISSUED']));
        self::assertSame(2, $issued['total']);

        $byPatient = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(term: 'Luna'));
        self::assertSame(1, $byPatient['total']);
        self::assertSame('ORD-2026-000101', $byPatient['items'][0]->number);
        self::assertTrue($byPatient['items'][0]->carriesControlledSubstance);
        self::assertSame(1, $byPatient['items'][0]->lineCount);
        self::assertSame('Dr Rousseau', $byPatient['items'][0]->prescriberName);

        $byOwner = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(term: 'Dupont'));
        self::assertSame(3, $byOwner['total']);

        $byNumber = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(term: '000102'));
        self::assertSame(1, $byNumber['total']);

        $onlyControlled = $this->read->search(
            $this->clinic(),
            new SearchPrescriptionsCriteria(controlledOnly: true),
        );
        self::assertSame(1, $onlyControlled['total']);

        $future = $this->read->search(
            $this->clinic(),
            new SearchPrescriptionsCriteria(issuedFrom: new \DateTimeImmutable('+1 year')),
        );
        self::assertSame(0, $future['total']);

        $upTo = $this->read->search(
            $this->clinic(),
            new SearchPrescriptionsCriteria(issuedTo: new \DateTimeImmutable('+1 day')),
        );
        self::assertSame(3, $upTo['total']);
    }

    public function testAnImpossibleDateRangeSkipsTheQuery(): void
    {
        self::assertSame(
            ['items' => [], 'total' => 0],
            $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(
                issuedFrom: new \DateTimeImmutable('2026-12-31'),
                issuedTo: new \DateTimeImmutable('2026-01-01'),
            )),
        );
    }

    public function testTheListSortsAndPaginates(): void
    {
        foreach (['Alpha', 'Bravo', 'Charlie'] as $index => $name) {
            $prescription = $this->draft(consultationId: $this->uuids->generate(), patientName: $name);
            $prescription->addLine($this->line('Meloxidyl'), $this->now());
            $prescription->issue(PrescriptionNumber::of(2026, 110 + $index), $this->now());
            $this->prescriptions->save($prescription);
        }

        $byPatient = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(
            sortBy: SearchPrescriptionsCriteria::SORT_PATIENT,
            sortDescending: false,
        ));
        self::assertSame('Alpha', $byPatient['items'][0]->patientName);

        $byNumber = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(
            sortBy: SearchPrescriptionsCriteria::SORT_NUMBER,
            sortDescending: true,
        ));
        self::assertSame('ORD-2026-000112', $byNumber['items'][0]->number);

        $byStatus = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(
            sortBy: SearchPrescriptionsCriteria::SORT_STATUS,
        ));
        self::assertSame(3, $byStatus['total']);

        $page2 = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(perPage: 2, page: 2));
        self::assertCount(1, $page2['items']);

        // An unknown sort falls back on the date rather than failing.
        $fallback = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria(sortBy: 'colour'));
        self::assertSame(3, $fallback['total']);
    }

    public function testADraftIsListedWithoutANumberOrValidity(): void
    {
        $prescription = $this->draft();
        $prescription->addLine($this->line('Meloxidyl'), $this->now());
        $this->prescriptions->save($prescription);

        $result = $this->read->search($this->clinic(), new SearchPrescriptionsCriteria());

        self::assertSame(1, $result['total']);
        self::assertNull($result['items'][0]->number);
        self::assertNull($result['items'][0]->issuedAt);
        self::assertNull($result['items'][0]->validUntil);
        self::assertFalse($result['items'][0]->isExpiredAt(new \DateTimeImmutable('+10 years')));
    }

    private function draft(
        ?string $consultationId = null,
        string $patientName = 'Rex',
    ): Prescription {
        return Prescription::draft(
            id: PrescriptionId::fromString($this->uuids->generate()),
            clinicId: $this->clinic(),
            consultationId: ConsultationId::fromString($consultationId ?? self::CONSULTATION_ID),
            prescriber: Prescriber::create(
                PrescriberId::fromString(self::PRESCRIBER_ID),
                'Rousseau',
                'FR-12345',
                'DR',
            ),
            patient: PatientSnapshot::create(
                animalId: null,
                name: $patientName,
                species: 'dog',
                breed: 'Labrador',
                birthDate: new \DateTimeImmutable('2016-05-01'),
                weightKg: '28.5',
                microchipNumber: '250269801234567',
            ),
            owner: OwnerSnapshot::create(
                OwnerId::fromString(self::CLIENT_ID),
                'Sophie Dupont',
                ['12 rue des Lilas'],
            ),
            now: $this->now(),
        );
    }

    private function line(
        string $label,
        DrugPrescriptionClass $class = DrugPrescriptionClass::RX,
    ): PrescriptionLine {
        return PrescriptionLine::create(
            id: PrescriptionLineId::fromString($this->uuids->generate()),
            articleId: null,
            code: 'CODE-1',
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

    private function clinic(): ClinicId
    {
        return ClinicId::fromString(self::CLINIC_ID);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
