<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Admission;

use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
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
