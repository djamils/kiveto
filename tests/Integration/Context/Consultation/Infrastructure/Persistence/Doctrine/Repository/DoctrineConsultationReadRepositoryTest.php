<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\BodySystem;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use App\Context\Consultation\Domain\ValueObject\ExamStatus;
use App\Context\Consultation\Domain\ValueObject\NoteType;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use App\Context\Consultation\Domain\ValueObject\VitalType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineConsultationReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string OTHER_CLINIC_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID      = '66666666-6666-4666-8666-666666666666';
    private const string OTHER_PATIENT   = '77777777-7777-4777-8777-777777777777';
    private const string ARTICLE_ID      = '88888888-8888-4888-8888-888888888888';
    private const string CONSULTATION_1  = '11111111-1111-4111-8111-111111111111';
    private const string CONSULTATION_2  = '11111111-1111-4111-8111-111111111112';
    private const string CONSULTATION_3  = '11111111-1111-4111-8111-111111111113';

    private ConsultationRepositoryInterface $writeRepository;

    private ConsultationReadRepositoryInterface $readRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $writeRepository = self::getContainer()->get(ConsultationRepositoryInterface::class);
        \assert($writeRepository instanceof ConsultationRepositoryInterface);
        $this->writeRepository = $writeRepository;

        $readRepository = self::getContainer()->get(ConsultationReadRepositoryInterface::class);
        \assert($readRepository instanceof ConsultationReadRepositoryInterface);
        $this->readRepository = $readRepository;
    }

    public function testFindByIdReturnsTheWholeExtendedDetails(): void
    {
        $user = UserId::fromString(self::USER_ID);
        $at   = new \DateTimeImmutable('2026-04-10 09:00:00');

        $consultation = $this->startConsultation(self::CONSULTATION_1, self::CLINIC_ID, self::PATIENT_ID, $at);
        $consultation->recordChiefComplaint('Boiterie', $at);
        $consultation->recordVitals(Vitals::create(12.5, 38.2), $at);
        $consultation->addClinicalNote(NoteType::DIAGNOSIS, 'Otite externe', $user, $at);
        $consultation->addPerformedAct('Otoscopie', 1.5, $at, $user, $at);
        $consultation->setMotifs(['Boiterie', 'Vomissements'], $at);
        $consultation->updateSubjectiveText('Boite depuis 3 jours', $at);
        $consultation->recordTypedVital(VitalType::HEART_RATE, '92', $user, $at);
        $consultation->recordTypedVital(VitalType::MUCOUS_MEMBRANES, 'Roses', $user, $at);
        $consultation->recordExamSystem(
            BodySystem::CARDIOVASCULAR,
            ExamStatus::ANOMALY,
            'Souffle systolique',
            ['fc' => '120', 'rhythm' => 'régulier'],
            $user,
            $at,
        );
        $consultation->recordExamSystem(BodySystem::RESPIRATORY, ExamStatus::NORMAL, null, [], $user, $at);
        $consultation->updateObjectiveObservations('Muqueuses roses', $at);
        $consultation->addDiagnosis(
            'OTI-01',
            'Otite externe',
            DiagnosisCertainty::PROBABLE,
            'Oreille droite',
            true,
            DiagnosisSource::MANUAL,
            $user,
            $at,
        );
        $consultation->addDiagnosis(
            null,
            'Dermatite allergique',
            DiagnosisCertainty::POSSIBLE,
            null,
            false,
            DiagnosisSource::AI_SUGGESTION,
            $user,
            $at,
        );
        $consultation->addPlanAction(
            PlanActionKind::PERFORMED_ACT,
            'Otoscopie',
            'ACT-OTO',
            null,
            null,
            null,
            2.0,
            3500,
            'EUR',
            'TVA20',
            $user,
            $at,
        );
        $consultation->addPlanAction(
            PlanActionKind::ADVICE,
            'Nettoyer les oreilles',
            null,
            '2 fois par jour',
            7,
            null,
            1.0,
            null,
            null,
            null,
            $user,
            $at,
        );
        $consultation->addPrescriptionLine(
            self::ARTICLE_ID,
            'MED-OTO',
            'Otomax',
            '1 goutte',
            '2 fois par jour',
            7,
            'Auriculaire',
            3.0,
            1250,
            'EUR',
            'TVA10',
            $user,
            $at,
        );
        $consultation->updateTeamMemo('Rappeler le client demain', $at);

        $this->writeRepository->save($consultation);

        $dto = $this->readRepository->findById(
            ConsultationId::fromString(self::CONSULTATION_1),
            ClinicId::fromString(self::CLINIC_ID),
        );

        self::assertSame(self::CONSULTATION_1, $dto->consultationId);
        self::assertSame(self::CLINIC_ID, $dto->clinicId);
        self::assertSame(self::USER_ID, $dto->practitionerUserId);
        self::assertSame('OPEN', $dto->status);
        self::assertNull($dto->appointmentId);
        self::assertSame(self::PATIENT_ID, $dto->patientId);
        self::assertSame('Boiterie', $dto->chiefComplaint);
        self::assertSame(['weightKg' => '12.500', 'temperatureC' => '38.20'], $dto->vitals);
        self::assertNull($dto->summary);
        self::assertSame('2026-04-10 09:00:00', $dto->startedAtUtc);
        self::assertNull($dto->closedAtUtc);
        self::assertFalse($dto->isClosed());

        self::assertSame(
            [['noteType' => 'DIAGNOSIS', 'content' => 'Otite externe', 'createdAt' => '2026-04-10 09:00:00']],
            $dto->notes,
        );
        self::assertSame(
            [['label' => 'Otoscopie', 'quantity' => '1.50', 'performedAt' => '2026-04-10 09:00:00']],
            $dto->acts,
        );

        // ── New free-text columns ────────────────────────────────────────
        self::assertSame('Boite depuis 3 jours', $dto->subjectiveText);
        self::assertSame('Muqueuses roses', $dto->objectiveObservations);
        self::assertSame('Rappeler le client demain', $dto->teamMemo);

        // ── Child collections ────────────────────────────────────────────
        $motifs = $consultation->getMotifs();
        self::assertSame([
            ['id' => $motifs[0]->getId(), 'label' => 'Boiterie'],
            ['id' => $motifs[1]->getId(), 'label' => 'Vomissements'],
        ], $dto->motifs);

        $typedVitals = $consultation->getTypedVitals();
        self::assertSame([
            [
                'id'         => $typedVitals[0]->getId(),
                'type'       => 'HEART_RATE',
                'value'      => '92',
                'recordedAt' => '2026-04-10 09:00:00',
            ],
            [
                'id'         => $typedVitals[1]->getId(),
                'type'       => 'MUCOUS_MEMBRANES',
                'value'      => 'Roses',
                'recordedAt' => '2026-04-10 09:00:00',
            ],
        ], $dto->typedVitals);

        $examSystems = $consultation->getExamSystems();
        self::assertSame([
            [
                'id'             => $examSystems[0]->getId(),
                'system'         => 'CARDIOVASCULAR',
                'status'         => 'ANOMALY',
                'notes'          => 'Souffle systolique',
                'structuredData' => ['fc' => '120', 'rhythm' => 'régulier'],
            ],
            [
                'id'             => $examSystems[1]->getId(),
                'system'         => 'RESPIRATORY',
                'status'         => 'NORMAL',
                'notes'          => null,
                'structuredData' => [],
            ],
        ], $dto->examSystems);

        $diagnoses = $consultation->getDiagnoses();
        self::assertSame([
            [
                'id'        => $diagnoses[0]->getId(),
                'code'      => 'OTI-01',
                'label'     => 'Otite externe',
                'certainty' => 'PROBABLE',
                'note'      => 'Oreille droite',
                'isPrimary' => true,
                'source'    => 'MANUAL',
            ],
            [
                'id'        => $diagnoses[1]->getId(),
                'code'      => null,
                'label'     => 'Dermatite allergique',
                'certainty' => 'POSSIBLE',
                'note'      => null,
                'isPrimary' => false,
                'source'    => 'AI_SUGGESTION',
            ],
        ], $dto->diagnoses);

        $planActions = $consultation->getPlanActions();
        self::assertSame([
            [
                'id'                  => $planActions[0]->getId(),
                'kind'                => 'PERFORMED_ACT',
                'description'         => 'Otoscopie',
                'catalogCode'         => 'ACT-OTO',
                'posology'            => null,
                'durationDays'        => null,
                'followUpDays'        => null,
                'quantity'            => 2.0,
                'unitPriceMinorUnits' => 3500,
                'currency'            => 'EUR',
            ],
            [
                'id'                  => $planActions[1]->getId(),
                'kind'                => 'ADVICE',
                'description'         => 'Nettoyer les oreilles',
                'catalogCode'         => null,
                'posology'            => '2 fois par jour',
                'durationDays'        => 7,
                'followUpDays'        => null,
                'quantity'            => 1.0,
                'unitPriceMinorUnits' => null,
                'currency'            => null,
            ],
        ], $dto->planActions);

        $prescriptionLines = $consultation->getPrescriptionLines();
        self::assertSame([
            [
                'id'                  => $prescriptionLines[0]->getId(),
                'articleId'           => self::ARTICLE_ID,
                'code'                => 'MED-OTO',
                'label'               => 'Otomax',
                'dose'                => '1 goutte',
                'frequency'           => '2 fois par jour',
                'durationDays'        => 7,
                'route'               => 'Auriculaire',
                'quantity'            => 3.0,
                'unitPriceMinorUnits' => 1250,
                'currency'            => 'EUR',
            ],
        ], $dto->prescriptionLines);

        $billingLines = $consultation->getBillingLines();
        self::assertSame([
            [
                'id'                  => $billingLines[0]->getId(),
                'sourceLineId'        => $planActions[0]->getId(),
                'source'              => 'PLAN_ACT',
                'label'               => 'Otoscopie',
                'code'                => 'ACT-OTO',
                'quantity'            => 2.0,
                'unitPriceMinorUnits' => 3500,
                'currency'            => 'EUR',
                'taxCategoryCode'     => 'TVA20',
                'totalMinorUnits'     => 7000,
            ],
            [
                'id'                  => $billingLines[1]->getId(),
                'sourceLineId'        => $prescriptionLines[0]->getId(),
                'source'              => 'PRESCRIPTION',
                'label'               => 'Otomax',
                'code'                => 'MED-OTO',
                'quantity'            => 3.0,
                'unitPriceMinorUnits' => 1250,
                'currency'            => 'EUR',
                'taxCategoryCode'     => 'TVA10',
                'totalMinorUnits'     => 3750,
            ],
        ], $dto->billingLines);

        // Money maths belongs to the query handler, not to this repository.
        self::assertSame([
            'totalHtMinorUnits'  => 0,
            'totalTvaMinorUnits' => 0,
            'totalTtcMinorUnits' => 0,
            'currency'           => '',
        ], $dto->totals);
    }

    public function testFindByIdThrowsWhenConsultationDoesNotExist(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation "00000000-0000-4000-8000-000000000000" not found.');

        $this->readRepository->findById(
            ConsultationId::fromString('00000000-0000-4000-8000-000000000000'),
            ClinicId::fromString(self::CLINIC_ID),
        );
    }

    public function testFindByIdThrowsWhenConsultationBelongsToAnotherClinic(): void
    {
        $this->startAndSave(self::CONSULTATION_1, self::CLINIC_ID, self::PATIENT_ID, '2026-04-10 09:00:00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(\sprintf('Consultation "%s" not found.', self::CONSULTATION_1));

        $this->readRepository->findById(
            ConsultationId::fromString(self::CONSULTATION_1),
            ClinicId::fromString(self::OTHER_CLINIC_ID),
        );
    }

    public function testFindByIdIgnoresStructuredDataThatIsNotAMapOfStrings(): void
    {
        $this->startAndSave(self::CONSULTATION_1, self::CLINIC_ID, self::PATIENT_ID, '2026-04-10 09:00:00');

        // Written through DBAL: the aggregate can only ever produce a map of
        // strings, so these legacy shapes are unreachable from the write side.
        $this->insertExamSystemRow('CARDIOVASCULAR', '"not-an-array"', 0);
        $this->insertExamSystemRow('RESPIRATORY', 'null', 1);
        $this->insertExamSystemRow('DIGESTIVE', '{"0": 5}', 2);

        $dto = $this->readRepository->findById(
            ConsultationId::fromString(self::CONSULTATION_1),
            ClinicId::fromString(self::CLINIC_ID),
        );

        self::assertSame(
            ['CARDIOVASCULAR', 'RESPIRATORY', 'DIGESTIVE'],
            array_map(static fn (array $row): string => $row['system'], $dto->examSystems),
        );
        self::assertSame(
            [[], [], []],
            array_map(static fn (array $row): array => $row['structuredData'], $dto->examSystems),
        );
    }

    public function testListForPatientsReturnsEmptyArrayWithoutPatientIds(): void
    {
        $this->startAndSave(self::CONSULTATION_1, self::CLINIC_ID, self::PATIENT_ID, '2026-04-10 09:00:00');

        self::assertSame([], $this->readRepository->listForPatients([], ClinicId::fromString(self::CLINIC_ID)));
    }

    public function testListForPatientsReturnsConsultationsOfEveryPatientMostRecentFirst(): void
    {
        $closed = $this->startAndSave(
            self::CONSULTATION_1,
            self::CLINIC_ID,
            self::PATIENT_ID,
            '2026-04-10 08:00:00',
        );
        $closed->recordVitals(Vitals::create(12.5, null), new \DateTimeImmutable('2026-04-10 08:30:00'));
        $closed->close(
            UserId::fromString(self::USER_ID),
            'Traitement local',
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
        $this->writeRepository->save($closed);

        $open = $this->startAndSave(
            self::CONSULTATION_2,
            self::CLINIC_ID,
            self::PATIENT_ID,
            '2026-04-11 08:00:00',
        );
        $open->recordChiefComplaint('Toux', new \DateTimeImmutable('2026-04-11 08:05:00'));
        $this->writeRepository->save($open);

        $this->startAndSave(self::CONSULTATION_3, self::CLINIC_ID, self::OTHER_PATIENT, '2026-04-09 08:00:00');

        $rows = $this->readRepository->listForPatients(
            [self::PATIENT_ID, self::OTHER_PATIENT],
            ClinicId::fromString(self::CLINIC_ID),
        );

        self::assertSame([
            [
                'consultationId' => self::CONSULTATION_2,
                'startedAtUtc'   => '2026-04-11 08:00:00',
                'closedAtUtc'    => null,
                'status'         => 'OPEN',
                'chiefComplaint' => 'Toux',
                'summary'        => null,
                'weightKg'       => null,
            ],
            [
                'consultationId' => self::CONSULTATION_1,
                'startedAtUtc'   => '2026-04-10 08:00:00',
                'closedAtUtc'    => '2026-04-10 09:00:00',
                'status'         => 'CLOSED',
                'chiefComplaint' => null,
                'summary'        => 'Traitement local',
                'weightKg'       => '12.500',
            ],
            [
                'consultationId' => self::CONSULTATION_3,
                'startedAtUtc'   => '2026-04-09 08:00:00',
                'closedAtUtc'    => null,
                'status'         => 'OPEN',
                'chiefComplaint' => null,
                'summary'        => null,
                'weightKg'       => null,
            ],
        ], $rows);
    }

    public function testListForPatientsHonoursTheExcludedConsultation(): void
    {
        $this->startAndSave(self::CONSULTATION_1, self::CLINIC_ID, self::PATIENT_ID, '2026-04-10 08:00:00');
        $this->startAndSave(self::CONSULTATION_2, self::CLINIC_ID, self::PATIENT_ID, '2026-04-11 08:00:00');

        $rows = $this->readRepository->listForPatients(
            [self::PATIENT_ID],
            ClinicId::fromString(self::CLINIC_ID),
            ConsultationId::fromString(self::CONSULTATION_2),
        );

        self::assertSame(
            [self::CONSULTATION_1],
            array_map(static fn (array $row): string => $row['consultationId'], $rows),
        );
    }

    public function testListForPatientsIgnoresConsultationsOfAnotherClinic(): void
    {
        $this->startAndSave(self::CONSULTATION_1, self::CLINIC_ID, self::PATIENT_ID, '2026-04-10 08:00:00');
        $this->startAndSave(self::CONSULTATION_2, self::OTHER_CLINIC_ID, self::PATIENT_ID, '2026-04-11 08:00:00');

        $rows = $this->readRepository->listForPatients(
            [self::PATIENT_ID],
            ClinicId::fromString(self::CLINIC_ID),
        );

        self::assertSame(
            [self::CONSULTATION_1],
            array_map(static fn (array $row): string => $row['consultationId'], $rows),
        );
    }

    private function insertExamSystemRow(string $system, string $structuredData, int $position): void
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        $connection->executeStatement(
            'INSERT INTO consultation__exam_systems
                 (id, consultation_id, `system`, status, notes, structured_data,
                  recorded_at_utc, recorded_by_user_id, position)
             VALUES (:id, :consultationId, :system, :status, NULL, :structuredData,
                     :recordedAt, :userId, :position)',
            [
                'id'             => Uuid::v7()->toBinary(),
                'consultationId' => Uuid::fromString(self::CONSULTATION_1)->toBinary(),
                'system'         => $system,
                'status'         => 'NORMAL',
                'structuredData' => $structuredData,
                'recordedAt'     => '2026-04-10 09:00:00',
                'userId'         => Uuid::fromString(self::USER_ID)->toBinary(),
                'position'       => $position,
            ],
            [
                'id'             => ParameterType::BINARY,
                'consultationId' => ParameterType::BINARY,
                'userId'         => ParameterType::BINARY,
            ],
        );
    }

    private function startConsultation(
        string $consultationId,
        string $clinicId,
        string $patientId,
        \DateTimeImmutable $startedAt,
    ): Consultation {
        return Consultation::startFromAdmission(
            ConsultationId::fromString($consultationId),
            ClinicId::fromString($clinicId),
            AdmissionId::fromString(Uuid::v7()->toRfc4122()),
            PatientId::fromString($patientId),
            UserId::fromString(self::USER_ID),
            $startedAt,
        );
    }

    private function startAndSave(
        string $consultationId,
        string $clinicId,
        string $patientId,
        string $startedAt,
    ): Consultation {
        $consultation = $this->startConsultation(
            $consultationId,
            $clinicId,
            $patientId,
            new \DateTimeImmutable($startedAt),
        );

        $this->writeRepository->save($consultation);

        return $consultation;
    }
}
