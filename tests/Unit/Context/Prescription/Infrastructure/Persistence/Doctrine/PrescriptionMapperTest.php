<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Infrastructure\Persistence\Doctrine;

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
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity\PrescriptionEntity;
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity\PrescriptionLineEntity;
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\PrescriptionMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PrescriptionMapperTest extends TestCase
{
    private const string PRESCRIPTION_ID = '01960000-0000-7000-8000-000000000901';
    private const string CLINIC_ID       = '01960000-0000-7000-8000-000000000902';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-000000000903';
    private const string PRESCRIBER_ID   = '01960000-0000-7000-8000-000000000904';
    private const string ANIMAL_ID       = '01960000-0000-7000-8000-000000000905';
    private const string CLIENT_ID       = '01960000-0000-7000-8000-000000000906';
    private const string LINE_ID         = '01960000-0000-7000-8000-000000000907';
    private const string RENEWAL_ID      = '01960000-0000-7000-8000-000000000908';
    private const string USER_ID         = '01960000-0000-7000-8000-000000000909';

    private PrescriptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PrescriptionMapper();
    }

    public function testAPrescriptionSurvivesTheRoundTrip(): void
    {
        $prescription = $this->prescription();

        $entity   = $this->mapper->toEntity($prescription);
        $lines    = [$this->mapper->lineToEntity($prescription->lines()[0], $prescription->id(), 0)];
        $renewals = [$this->mapper->renewalToEntity($prescription->renewalHistory()[0], $prescription->id(), 0)];

        $restored = $this->mapper->toDomain($entity, $lines, $renewals);

        self::assertSame(self::PRESCRIPTION_ID, $restored->id()->toString());
        self::assertSame(self::CLINIC_ID, $restored->clinicId()->toString());
        self::assertSame(self::CONSULTATION_ID, $restored->consultationId()->toString());
        self::assertSame('ORD-2026-000007', $restored->number()?->toString());
        self::assertSame(PrescriptionStatus::ISSUED, $restored->status());
        self::assertSame('Rousseau', $restored->prescriber()->displayName());
        self::assertSame('FR-12345', $restored->prescriber()->registrationNumber());
        self::assertSame('Rex', $restored->patient()->name());
        self::assertSame('28.5', $restored->patient()->weightKg());
        self::assertSame(self::ANIMAL_ID, $restored->patient()->animalId());
        self::assertSame('Sophie Dupont', $restored->owner()->displayName());
        self::assertSame(['12 rue des Lilas'], $restored->owner()->addressLines());
        self::assertCount(1, $restored->lines());
        self::assertSame(['Méloxicam'], $restored->lines()[0]->activeSubstances());
        self::assertCount(1, $restored->renewalHistory());
        self::assertSame(self::USER_ID, $restored->renewalHistory()[0]->renewedByUserId());
        self::assertSame(2, $restored->renewals()->allowed());
        self::assertSame(1, $restored->renewals()->used());
        self::assertSame('Au cours du repas', $restored->notes());
        self::assertSame('2027-08-22', $restored->validity()?->validUntil()->format('Y-m-d'));
        self::assertSame(365, $restored->validity()->spanDays());
    }

    public function testDecimalColumnsComeBackInTheirOriginalShape(): void
    {
        $entity = $this->mapper->toEntity($this->prescription());
        $entity->setPatientWeightKg('28.500');

        $line = new PrescriptionLineEntity();
        $line->setId(Uuid::fromString(self::LINE_ID));
        $line->setPrescriptionId(Uuid::fromString(self::PRESCRIPTION_ID));
        $line->setLabel('Meloxidyl');
        $line->setCode(null);
        $line->setPharmaceuticalForm(null);
        $line->setActiveSubstances([]);
        $line->setDose(null);
        $line->setFrequency(null);
        $line->setDurationDays(null);
        $line->setRoute(null);
        $line->setQuantity('2.00');
        $line->setPrescriptionClass(DrugPrescriptionClass::RX);
        $line->setInstructions(null);
        $line->setPosition(0);

        $restored = $this->mapper->toDomain($entity, [$line], []);

        self::assertSame('2', $restored->lines()[0]->quantity());
        self::assertSame('28.5', $restored->patient()->weightKg());
    }

    public function testAWholeDecimalIsNotTrimmedAway(): void
    {
        $entity = $this->mapper->toEntity($this->prescription());
        $entity->setPatientWeightKg('30');

        $restored = $this->mapper->toDomain($entity, [], []);

        self::assertSame('30', $restored->patient()->weightKg());
    }

    public function testADraftWithoutNumberValidityOrOwnerMapsToNulls(): void
    {
        $prescription = Prescription::draft(
            id: PrescriptionId::fromString(self::PRESCRIPTION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            consultationId: ConsultationId::fromString(self::CONSULTATION_ID),
            prescriber: Prescriber::create(PrescriberId::fromString(self::PRESCRIBER_ID), 'Martin'),
            patient: PatientSnapshot::create(null, 'Rex'),
            owner: OwnerSnapshot::unknown(),
            now: $this->now(),
        );

        $entity = $this->mapper->toEntity($prescription);

        self::assertNull($entity->getNumber());
        self::assertNull($entity->getIssuedAt());
        self::assertNull($entity->getValidUntil());
        self::assertNull($entity->getValiditySpanDays());
        self::assertNull($entity->getOwnerClientId());
        self::assertNull($entity->getPatientAnimalId());
        self::assertNull($entity->getPrescriberRegistrationNumber());
        self::assertNull($entity->getNotes());
        self::assertSame([], $entity->getOwnerAddressLines());

        $restored = $this->mapper->toDomain($entity, [], []);

        self::assertNull($restored->number());
        self::assertNull($restored->validity());
        self::assertNull($restored->owner()->clientId());
        self::assertNull($restored->patient()->animalId());
        self::assertFalse($restored->prescriber()->canSign());
    }

    public function testAnExistingRowIsUpdatedRatherThanReplaced(): void
    {
        $prescription = $this->prescription();
        $entity       = new PrescriptionEntity();
        $entity->setId(Uuid::fromString(self::PRESCRIPTION_ID));
        $entity->setClinicId(Uuid::fromString(self::CLINIC_ID));
        $entity->setConsultationId(Uuid::fromString(self::CONSULTATION_ID));
        $entity->setCreatedAt($this->now());

        $this->mapper->applyMutableState($prescription, $entity);

        self::assertSame('ORD-2026-000007', $entity->getNumber());
        self::assertSame(PrescriptionStatus::ISSUED, $entity->getStatus());
        self::assertSame('Rousseau', $entity->getPrescriberDisplayName());
        self::assertSame('DR', $entity->getPrescriberProfessionalTitle());
        self::assertSame('Rex', $entity->getPatientName());
        self::assertSame('dog', $entity->getPatientSpecies());
        self::assertSame('Labrador', $entity->getPatientBreed());
        self::assertSame('250269801234567', $entity->getPatientMicrochipNumber());
        self::assertSame(2, $entity->getRenewalsAllowed());
        self::assertSame(1, $entity->getRenewalsUsed());
        self::assertSame(365, $entity->getValiditySpanDays());
        self::assertNull($entity->getCancelledAt());
        self::assertNull($entity->getCancellationReason());
        self::assertSame(
            $this->now()->format('Y-m-d'),
            $entity->getUpdatedAt()->format('Y-m-d'),
        );
        self::assertSame('2016-05-01', $entity->getPatientBirthDate()?->format('Y-m-d'));
    }

    public function testALineEntityCarriesEverythingItPrints(): void
    {
        $entity = $this->mapper->lineToEntity(
            $this->prescription()->lines()[0],
            PrescriptionId::fromString(self::PRESCRIPTION_ID),
            2,
        );

        self::assertSame(self::LINE_ID, $entity->getId()->toRfc4122());
        self::assertSame(self::PRESCRIPTION_ID, $entity->getPrescriptionId()->toRfc4122());
        self::assertSame('MELOXIDYL-1', $entity->getCode());
        self::assertSame('Meloxidyl', $entity->getLabel());
        self::assertSame('Suspension buvable', $entity->getPharmaceuticalForm());
        self::assertSame(['Méloxicam'], $entity->getActiveSubstances());
        self::assertSame('0,1 mg/kg', $entity->getDose());
        self::assertSame('1×/j', $entity->getFrequency());
        self::assertSame(7, $entity->getDurationDays());
        self::assertSame('per os', $entity->getRoute());
        self::assertSame('1', $entity->getQuantity());
        self::assertSame(DrugPrescriptionClass::RX, $entity->getPrescriptionClass());
        self::assertSame('Avec de la nourriture', $entity->getInstructions());
        self::assertSame(2, $entity->getPosition());
        self::assertSame('01960000-0000-7000-8000-00000000090a', $entity->getArticleId()?->toRfc4122());
    }

    public function testARenewalWithoutAnAuthorMapsToNull(): void
    {
        $renewal = Renewal::create(
            RenewalId::fromString(self::RENEWAL_ID),
            $this->now(),
            $this->now()->modify('+1 year'),
        );

        $entity = $this->mapper->renewalToEntity($renewal, PrescriptionId::fromString(self::PRESCRIPTION_ID), 3);

        self::assertNull($entity->getRenewedByUserId());
        self::assertSame(3, $entity->getPosition());
        self::assertSame(self::RENEWAL_ID, $entity->getId()->toRfc4122());
        self::assertSame(self::PRESCRIPTION_ID, $entity->getPrescriptionId()->toRfc4122());
        self::assertSame(
            $this->now()->format('Y-m-d'),
            $entity->getRenewedAt()->format('Y-m-d'),
        );
        self::assertSame('2027-08-22', $entity->getNewValidUntil()->format('Y-m-d'));
    }

    private function prescription(): Prescription
    {
        return Prescription::reconstitute(
            id: PrescriptionId::fromString(self::PRESCRIPTION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            consultationId: ConsultationId::fromString(self::CONSULTATION_ID),
            number: PrescriptionNumber::of(2026, 7),
            status: PrescriptionStatus::ISSUED,
            prescriber: Prescriber::create(
                PrescriberId::fromString(self::PRESCRIBER_ID),
                'Rousseau',
                'FR-12345',
                'DR',
            ),
            patient: PatientSnapshot::create(
                animalId: self::ANIMAL_ID,
                name: 'Rex',
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
            lines: [
                PrescriptionLine::create(
                    id: PrescriptionLineId::fromString(self::LINE_ID),
                    articleId: '01960000-0000-7000-8000-00000000090a',
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
                    instructions: 'Avec de la nourriture',
                ),
            ],
            validity: Validity::of($this->now(), 365),
            renewals: Renewals::reconstitute(2, 1),
            renewalHistory: [
                Renewal::create(
                    RenewalId::fromString(self::RENEWAL_ID),
                    $this->now(),
                    $this->now()->modify('+2 years'),
                    self::USER_ID,
                ),
            ],
            notes: 'Au cours du repas',
            createdAt: $this->now(),
            updatedAt: $this->now(),
            cancelledAt: null,
            cancellationReason: null,
        );
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
