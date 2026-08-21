<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Application\Query\ListConsultationsForPatient;

use App\Context\Consultation\Application\Query\ListConsultationsForPatient\ConsultationHistoryRow;
use App\Context\Consultation\Application\Query\ListConsultationsForPatient\ListConsultationsForPatient;
use App\Context\Consultation\Application\Query\ListConsultationsForPatient\ListConsultationsForPatientHandler;
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

final class ListConsultationsForPatientHandlerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '11111111-1111-4111-8111-111111111111';
    private const string OTHER_CLINIC_ID = '22222222-2222-4222-8222-222222222222';
    private const string ANIMAL_ID       = '33333333-3333-4333-8333-333333333333';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string CURRENT_PATIENT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
    private const string SIBLING_PATIENT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2';
    private const string CONSULTATION_1  = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc1';
    private const string CONSULTATION_2  = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc2';
    private const string CONSULTATION_3  = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc3';
    private const string CONSULTATION_4  = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc4';

    private ConsultationRepositoryInterface $repository;

    private ListConsultationsForPatientHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(ConsultationRepositoryInterface::class);
        \assert($repository instanceof ConsultationRepositoryInterface);
        $this->repository = $repository;

        $handler = self::getContainer()->get(ListConsultationsForPatientHandler::class);
        \assert($handler instanceof ListConsultationsForPatientHandler);
        $this->handler = $handler;
    }

    public function testReturnsTheHistoryRowsOfTheCurrentPatientOnly(): void
    {
        $this->seedHistory();

        $rows = ($this->handler)(new ListConsultationsForPatient(
            clinicId: self::CLINIC_ID,
            patientId: self::CURRENT_PATIENT,
        ));

        self::assertSame(
            [self::CONSULTATION_2, self::CONSULTATION_1],
            self::consultationIds($rows),
        );

        // Closed consultation carrying a summary and a weight.
        $closed = $rows[1];
        self::assertInstanceOf(ConsultationHistoryRow::class, $closed);
        self::assertSame(self::CONSULTATION_1, $closed->consultationId);
        self::assertSame('2026-04-10 08:00:00', $closed->startedAtUtc);
        self::assertSame('2026-04-10 09:00:00', $closed->closedAtUtc);
        self::assertSame('CLOSED', $closed->status);
        self::assertSame('Boiterie', $closed->chiefComplaint);
        self::assertSame('Traitement local', $closed->summary);
        self::assertSame('12.500', $closed->weightKg);

        $open = $rows[0];
        self::assertSame(self::CONSULTATION_2, $open->consultationId);
        self::assertSame('2026-04-11 08:00:00', $open->startedAtUtc);
        self::assertNull($open->closedAtUtc);
        self::assertSame('OPEN', $open->status);
        self::assertNull($open->chiefComplaint);
        self::assertNull($open->summary);
        self::assertNull($open->weightKg);
    }

    public function testIncludesTheConsultationsOfReconciledSiblingPatientsWhenTheAnimalIsKnown(): void
    {
        $this->seedHistory();

        $rows = ($this->handler)(new ListConsultationsForPatient(
            clinicId: self::CLINIC_ID,
            patientId: self::CURRENT_PATIENT,
            animalId: self::ANIMAL_ID,
        ));

        // The sibling patient row (pre-reconciliation) contributes the oldest
        // consultation; the other clinic's consultation stays out.
        self::assertSame(
            [self::CONSULTATION_2, self::CONSULTATION_1, self::CONSULTATION_3],
            self::consultationIds($rows),
        );
    }

    public function testExcludesTheCurrentConsultation(): void
    {
        $this->seedHistory();

        $rows = ($this->handler)(new ListConsultationsForPatient(
            clinicId: self::CLINIC_ID,
            patientId: self::CURRENT_PATIENT,
            animalId: self::ANIMAL_ID,
            excludeConsultationId: self::CONSULTATION_2,
        ));

        self::assertSame(
            [self::CONSULTATION_1, self::CONSULTATION_3],
            self::consultationIds($rows),
        );
    }

    public function testReturnsAnEmptyListWhenThePatientHasNoHistory(): void
    {
        $this->seedHistory();

        $rows = ($this->handler)(new ListConsultationsForPatient(
            clinicId: self::CLINIC_ID,
            patientId: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ));

        self::assertSame([], $rows);
    }

    /**
     * @param list<ConsultationHistoryRow> $rows
     *
     * @return list<string>
     */
    private static function consultationIds(array $rows): array
    {
        return array_map(
            static fn (ConsultationHistoryRow $row): string => $row->consultationId,
            $rows,
        );
    }

    /**
     * Two patient rows of the clinic share the same animal, plus a consultation
     * of another clinic that must never surface.
     */
    private function seedHistory(): void
    {
        $this->createPatient(self::CURRENT_PATIENT, self::CLINIC_ID);
        $this->createPatient(self::SIBLING_PATIENT, self::CLINIC_ID);

        $closed = $this->startConsultation(
            self::CONSULTATION_1,
            self::CLINIC_ID,
            self::CURRENT_PATIENT,
            '2026-04-10 08:00:00',
        );
        $closed->recordChiefComplaint('Boiterie', new \DateTimeImmutable('2026-04-10 08:10:00'));
        $closed->recordVitals(Vitals::create(12.5, null), new \DateTimeImmutable('2026-04-10 08:30:00'));
        $closed->close(
            UserId::fromString(self::USER_ID),
            'Traitement local',
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
        $this->repository->save($closed);

        $this->startConsultation(
            self::CONSULTATION_2,
            self::CLINIC_ID,
            self::CURRENT_PATIENT,
            '2026-04-11 08:00:00',
        );

        $this->startConsultation(
            self::CONSULTATION_3,
            self::CLINIC_ID,
            self::SIBLING_PATIENT,
            '2026-04-09 08:00:00',
        );

        $this->startConsultation(
            self::CONSULTATION_4,
            self::OTHER_CLINIC_ID,
            self::CURRENT_PATIENT,
            '2026-04-12 08:00:00',
        );
    }

    private function createPatient(string $patientId, string $clinicId): void
    {
        PatientEntityFactory::new()
            ->withId($patientId)
            ->withClinicId($clinicId)
            ->withAnimalLinkId(self::ANIMAL_ID)
            ->active()
            ->create()
        ;
    }

    private function startConsultation(
        string $consultationId,
        string $clinicId,
        string $patientId,
        string $startedAt,
    ): Consultation {
        $consultation = Consultation::startFromAdmission(
            ConsultationId::fromString($consultationId),
            ClinicId::fromString($clinicId),
            AdmissionId::fromString(Uuid::v7()->toRfc4122()),
            PatientId::fromString($patientId),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable($startedAt),
        );

        $this->repository->save($consultation);

        return $consultation;
    }
}
