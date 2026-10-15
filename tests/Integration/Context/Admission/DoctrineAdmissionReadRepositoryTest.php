<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Admission;

use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\ArrivalCheckIn;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Admission\Domain\ValueObject\PresenterRole;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineAdmissionReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '01950000-0000-7000-0000-0000000000a1';
    private const string OTHER_CLINIC_ID = '01950000-0000-7000-0000-0000000000a2';

    public function testFindClosedForClinicSinceReturnsTodaysDischargesMostRecentFirst(): void
    {
        $midnight = new \DateTimeImmutable('2026-03-04 00:00:00');

        $morning = $this->closedAdmission('Luna', new \DateTimeImmutable('2026-03-04 09:15:00'));
        $noon    = $this->closedAdmission('Pixel', new \DateTimeImmutable('2026-03-04 12:40:00'));

        $items = $this->readRepository()->findClosedForClinicSince(self::CLINIC_ID, $midnight);

        self::assertCount(2, $items);
        self::assertSame($noon, $items[0]->admissionId);
        self::assertSame($morning, $items[1]->admissionId);
        self::assertSame('Pixel', $items[0]->displayLabel);
        self::assertSame('2026-03-04T12:40:00+00:00', $items[0]->closedAt);
        self::assertSame(ClosureReason::ConsultationCompleted->value, $items[0]->closureReason);
    }

    public function testFindClosedForClinicSinceIgnoresEarlierClosuresOtherClinicsAndActiveAdmissions(): void
    {
        $midnight = new \DateTimeImmutable('2026-03-04 00:00:00');

        $this->closedAdmission('Hier', new \DateTimeImmutable('2026-03-03 18:00:00'));
        $this->closedAdmission('Ailleurs', new \DateTimeImmutable('2026-03-04 10:00:00'), self::OTHER_CLINIC_ID);

        $patientId = $this->patient('Encore là');
        AdmissionEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPatientId($patientId)
            ->create(['status' => AdmissionStatus::Active, 'closedAt' => null, 'closureReason' => null])
        ;

        self::assertSame([], $this->readRepository()->findClosedForClinicSince(self::CLINIC_ID, $midnight));
    }

    public function testFindAllActiveForClinicTellsSinceWhenThePatientIsWhereItIs(): void
    {
        AdmissionEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPatientId($this->patient('Simba'))
            ->create([
                'status'                  => AdmissionStatus::Active,
                'closedAt'                => null,
                'closureReason'           => null,
                'openedAt'                => new \DateTimeImmutable('2026-03-04 10:05:00'),
                'locationStatusEnteredAt' => new \DateTimeImmutable('2026-03-04 10:24:00'),
            ])
        ;

        $items = $this->readRepository()->findAllActiveForClinic(self::CLINIC_ID);

        self::assertCount(1, $items);
        self::assertSame('2026-03-04T10:05:00+00:00', $items[0]->openedAt);
        self::assertSame('2026-03-04T10:24:00+00:00', $items[0]->locationSince);
    }

    public function testAnUnidentifiedEmergencyCarriesWhoBroughtItAndWhatTheDeskSaw(): void
    {
        $patient = PatientEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->create(['displayLabelValue' => 'Urgence 10:28', 'observedSpecies' => 'Chat'])
        ;

        AdmissionEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPatientId($patient->getId()->toString())
            ->create([
                'status'           => AdmissionStatus::Active,
                'closedAt'         => null,
                'closureReason'    => null,
                'presenterName'    => 'M. Garcia',
                'presenterPhone'   => '+33645127790',
                'presenterRole'    => PresenterRole::ThirdParty,
                'scannedMicrochip' => '250268500112847',
            ])
        ;

        $item = $this->readRepository()->findAllActiveForClinic(self::CLINIC_ID)[0];

        self::assertSame('M. Garcia', $item->presenterName);
        self::assertSame('+33645127790', $item->presenterPhone);
        self::assertSame('third_party', $item->presenterRole);
        self::assertSame('250268500112847', $item->scannedMicrochip);
        self::assertSame('Chat', $item->observedSpecies);
        self::assertNull($item->arrivalCheckIn);
        self::assertNull($item->appointmentId);
        self::assertNull($item->appointmentStartsAt);
    }

    public function testAnHonouredBookingCarriesItsTimeAndTheArrivalCheckIn(): void
    {
        $admission = AdmissionEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPatientId($this->patient('Nori'))
            ->create([
                'status'         => AdmissionStatus::Active,
                'closedAt'       => null,
                'closureReason'  => null,
                'arrivalCheckIn' => ArrivalCheckIn::record(true, false, true, false, 4100, 'à jeun')->toArray(),
            ])
        ;

        $appointment = AppointmentEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withLinkedAdmissionId($admission->getId()->toString())
            ->startingAt(new \DateTimeImmutable('2026-03-04 10:30:00'))
            ->create()
        ;

        $item = $this->readRepository()->findAllActiveForClinic(self::CLINIC_ID)[0];

        self::assertSame($appointment->getId()->toString(), $item->appointmentId);
        self::assertSame('2026-03-04T10:30:00+00:00', $item->appointmentStartsAt);
        self::assertNotNull($item->arrivalCheckIn);
        self::assertTrue($item->arrivalCheckIn->fasting);
        self::assertFalse($item->arrivalCheckIn->newSymptom);
        self::assertTrue($item->arrivalCheckIn->documentsBrought);
        self::assertFalse($item->arrivalCheckIn->ongoingTreatment);
        self::assertSame(4100, $item->arrivalCheckIn->weightGrams);
        self::assertSame('à jeun', $item->arrivalCheckIn->note);
        self::assertNull($item->presenterRole);
        self::assertNull($item->observedSpecies);
    }

    public function testTheRepositoryKnowsWhetherAPatientIsStillAdmitted(): void
    {
        $onSite = $this->patient('Rex');
        AdmissionEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withPatientId($onSite)
            ->create(['status' => AdmissionStatus::Active, 'closedAt' => null, 'closureReason' => null])
        ;
        $this->closedAdmission('Parti', new \DateTimeImmutable('2026-03-04 09:00:00'));
        $gone = $this->patient('Ailleurs');

        $repository = static::getContainer()->get(AdmissionRepositoryInterface::class);
        \assert($repository instanceof AdmissionRepositoryInterface);

        self::assertTrue($repository->hasActiveAdmissionForPatient(ClinicId::fromString(self::CLINIC_ID), $onSite));
        self::assertFalse($repository->hasActiveAdmissionForPatient(ClinicId::fromString(self::OTHER_CLINIC_ID), $onSite));
        self::assertFalse($repository->hasActiveAdmissionForPatient(ClinicId::fromString(self::CLINIC_ID), $gone));
    }

    /**
     * Creates a closed admission for the clinic and returns its identifier.
     */
    private function closedAdmission(
        string $label,
        \DateTimeImmutable $closedAt,
        string $clinicId = self::CLINIC_ID,
    ): string {
        $admission = AdmissionEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPatientId($this->patient($label, $clinicId))
            ->create([
                'status'        => AdmissionStatus::Closed,
                'closureReason' => ClosureReason::ConsultationCompleted,
                'closedAt'      => $closedAt,
                'openedAt'      => $closedAt->modify('-1 hour'),
            ])
        ;

        return $admission->getId()->toString();
    }

    private function patient(string $label, string $clinicId = self::CLINIC_ID): string
    {
        $patient = PatientEntityFactory::new()
            ->withClinicId($clinicId)
            ->create(['displayLabelValue' => $label])
        ;

        return $patient->getId()->toString();
    }

    private function readRepository(): AdmissionReadRepositoryInterface
    {
        $repository = static::getContainer()->get(AdmissionReadRepositoryInterface::class);
        \assert($repository instanceof AdmissionReadRepositoryInterface);

        return $repository;
    }
}
