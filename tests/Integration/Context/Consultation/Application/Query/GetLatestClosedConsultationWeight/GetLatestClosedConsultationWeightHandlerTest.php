<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight;

use App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight\ConsultationWeight;
use App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight\GetLatestClosedConsultationWeight;
use App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight\GetLatestClosedConsultationWeightHandler;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class GetLatestClosedConsultationWeightHandlerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '11111111-1111-4111-8111-111111111111';
    private const string OTHER_CLINIC_ID = '22222222-2222-4222-8222-222222222222';
    private const string ANIMAL_ID       = '33333333-3333-4333-8333-333333333333';
    private const string OTHER_ANIMAL_ID = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string CURRENT_PATIENT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
    private const string SIBLING_PATIENT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2';
    private const string OTHER_PATIENT   = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3';

    private ConsultationRepositoryInterface $repository;

    private GetLatestClosedConsultationWeightHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(ConsultationRepositoryInterface::class);
        \assert($repository instanceof ConsultationRepositoryInterface);
        $this->repository = $repository;

        $handler = self::getContainer()->get(GetLatestClosedConsultationWeightHandler::class);
        \assert($handler instanceof GetLatestClosedConsultationWeightHandler);
        $this->handler = $handler;
    }

    public function testReturnsTheWeightOfTheLatestClosedConsultationAcrossReconciledPatients(): void
    {
        $this->createPatient(self::CURRENT_PATIENT, self::ANIMAL_ID);
        $this->createPatient(self::SIBLING_PATIENT, self::ANIMAL_ID);
        $this->createPatient(self::OTHER_PATIENT, self::OTHER_ANIMAL_ID);

        $this->consultation(self::CLINIC_ID, self::CURRENT_PATIENT, '2026-04-10 08:00:00', 12.5, close: true);
        // The sibling (pre-reconciliation) patient row holds the latest closed one.
        $this->consultation(self::CLINIC_ID, self::SIBLING_PATIENT, '2026-04-12 08:00:00', 12.3456, close: true);
        // Newer, but closed without a weight.
        $this->consultation(self::CLINIC_ID, self::CURRENT_PATIENT, '2026-04-13 08:00:00', null, close: true);
        // Newer, but still open: its vitals can change.
        $this->consultation(self::CLINIC_ID, self::CURRENT_PATIENT, '2026-04-14 08:00:00', 14.0, close: false);
        // Newer, but another animal or another clinic.
        $this->consultation(self::CLINIC_ID, self::OTHER_PATIENT, '2026-04-15 08:00:00', 3.0, close: true);
        $this->consultation(self::OTHER_CLINIC_ID, self::CURRENT_PATIENT, '2026-04-16 08:00:00', 20.0, close: true);

        $weight = ($this->handler)(new GetLatestClosedConsultationWeight(self::CLINIC_ID, self::ANIMAL_ID));

        self::assertInstanceOf(ConsultationWeight::class, $weight);
        self::assertSame(12346, $weight->weightGrams);
        self::assertSame('2026-04-12T08:00:00+00:00', $weight->measuredAtUtc->format(\DATE_ATOM));
        self::assertSame('UTC', $weight->measuredAtUtc->getTimezone()->getName());
    }

    public function testAnOpenConsultationAloneGivesNoWeight(): void
    {
        $this->createPatient(self::CURRENT_PATIENT, self::ANIMAL_ID);
        $this->consultation(self::CLINIC_ID, self::CURRENT_PATIENT, '2026-04-14 08:00:00', 14.0, close: false);

        self::assertNull(($this->handler)(new GetLatestClosedConsultationWeight(self::CLINIC_ID, self::ANIMAL_ID)));
    }

    public function testAnAnimalWithoutPatientRecordGivesNoWeight(): void
    {
        self::assertNull(($this->handler)(new GetLatestClosedConsultationWeight(self::CLINIC_ID, self::ANIMAL_ID)));
    }

    private function createPatient(string $patientId, string $animalId): void
    {
        PatientEntityFactory::new()
            ->withId($patientId)
            ->withClinicId(self::CLINIC_ID)
            ->withAnimalLinkId($animalId)
            ->active()
            ->create()
        ;
    }

    private function consultation(string $clinicId, string $patientId, string $startedAt, ?float $weightKg, bool $close): void
    {
        $startedAtUtc = new \DateTimeImmutable($startedAt);
        $consultation = Consultation::startFromAdmission(
            ConsultationId::fromString(Uuid::v7()->toRfc4122()),
            ClinicId::fromString($clinicId),
            AdmissionId::fromString(Uuid::v7()->toRfc4122()),
            PatientId::fromString($patientId),
            UserId::fromString(self::USER_ID),
            $startedAtUtc,
        );

        if (null !== $weightKg) {
            $consultation->recordVitals(Vitals::create($weightKg), $startedAtUtc->modify('+10 minutes'));
        } else {
            $consultation->recordVitals(Vitals::create(null, 38.5), $startedAtUtc->modify('+10 minutes'));
        }

        if ($close) {
            $consultation->close(UserId::fromString(self::USER_ID), null, $startedAtUtc->modify('+1 hour'));
        }

        $this->repository->save($consultation);
    }
}
