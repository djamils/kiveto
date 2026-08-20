<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\BillingLineSource;
use App\Context\Consultation\Domain\ValueObject\BodySystem;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use App\Context\Consultation\Domain\ValueObject\ExamStatus;
use App\Context\Consultation\Domain\ValueObject\NoteType;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use App\Context\Consultation\Domain\ValueObject\VitalType;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ConsultationChildEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\DiagnosisEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\MotifEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PlanActionEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PrescriptionLineEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineConsultationRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ADMISSION_ID    = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID      = '66666666-6666-4666-8666-666666666666';
    private const string ARTICLE_ID      = '77777777-7777-4777-8777-777777777777';

    private ConsultationRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repo = self::getContainer()->get(ConsultationRepositoryInterface::class);
        \assert($repo instanceof ConsultationRepositoryInterface);
        $this->repository = $repo;
    }

    public function testFindByIdReturnsNullWhenConsultationDoesNotExist(): void
    {
        $result = $this->repository->findById(
            ConsultationId::fromString('00000000-0000-4000-8000-000000000000'),
        );

        self::assertNull($result);
    }

    public function testSaveAndFindRoundTripFromAppointment(): void
    {
        $consultation = Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );

        $this->repository->save($consultation);

        $loaded = $this->repository->findById($consultation->getId());
        self::assertNotNull($loaded);
        self::assertSame(self::CONSULTATION_ID, $loaded->getId()->toString());
        self::assertSame(self::APPOINTMENT_ID, $loaded->getAppointmentId()?->toString());
        self::assertSame(self::ADMISSION_ID, $loaded->getAdmissionId()->toString());
        self::assertSame(self::PATIENT_ID, $loaded->getPatientId()->toString());
        self::assertSame(ConsultationStatus::OPEN, $loaded->getStatus());
    }

    public function testSaveAndFindRoundTripFromAdmission(): void
    {
        $consultation = Consultation::startFromAdmission(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );

        $this->repository->save($consultation);

        $loaded = $this->repository->findById($consultation->getId());
        self::assertNotNull($loaded);
        self::assertNull($loaded->getAppointmentId());
        self::assertSame(self::ADMISSION_ID, $loaded->getAdmissionId()->toString());
        self::assertSame(self::PATIENT_ID, $loaded->getPatientId()->toString());
    }

    public function testRoundTripWithFullLifecycle(): void
    {
        $consultation = Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
        $this->repository->save($consultation);

        // Reload, drive the lifecycle, save again — exercises updateEntity branches.
        $reloaded = $this->repository->findById($consultation->getId());
        self::assertNotNull($reloaded);

        $reloaded->recordChiefComplaint('Limping', new \DateTimeImmutable('2026-04-10 09:05:00'));
        $reloaded->recordVitals(
            Vitals::create(12.5, 38.2),
            new \DateTimeImmutable('2026-04-10 09:10:00'),
        );
        $reloaded->addClinicalNote(
            NoteType::DIAGNOSIS,
            'Otitis externa',
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:15:00'),
        );
        $reloaded->addPerformedAct(
            'Otoscopy',
            1.0,
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:20:00'),
        );
        $reloaded->close(
            UserId::fromString(self::USER_ID),
            'Stable',
            new \DateTimeImmutable('2026-04-10 09:30:00'),
        );

        $this->repository->save($reloaded);

        // Reload again to verify everything round-tripped.
        $final = $this->repository->findById($consultation->getId());
        self::assertNotNull($final);
        self::assertSame(ConsultationStatus::CLOSED, $final->getStatus());
        self::assertSame('Limping', $final->getChiefComplaint());
        self::assertSame('Stable', $final->getSummary());
        self::assertNotNull($final->getVitals());
        self::assertSame(12.5, $final->getVitals()->getWeightKg());
        self::assertSame(38.2, $final->getVitals()->getTemperatureC());
        self::assertCount(1, $final->getNotes());
        self::assertCount(1, $final->getActs());

        // Sanity-check the entity-level getConsultationId() getters by querying
        // the children directly via the EntityManager.
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);
        $consultationIdBinary = Uuid::fromString(self::CONSULTATION_ID)->toBinary();

        $noteEntity = $em->getRepository(
            \App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ClinicalNoteEntity::class,
        )->findOneBy(['consultationId' => $consultationIdBinary]);
        self::assertNotNull($noteEntity);
        self::assertSame($consultationIdBinary, $noteEntity->getConsultationId());

        $actEntity = $em->getRepository(
            \App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PerformedActEntity::class,
        )->findOneBy(['consultationId' => $consultationIdBinary]);
        self::assertNotNull($actEntity);
        self::assertSame($consultationIdBinary, $actEntity->getConsultationId());
    }

    public function testRoundTripWithEveryCockpitCollection(): void
    {
        $user = UserId::fromString(self::USER_ID);
        $at   = new \DateTimeImmutable('2026-04-10 09:00:00');

        $consultation = $this->startConsultation($at);

        $consultation->setMotifs(['Boiterie', 'Vomissements'], $at);
        $consultation->updateSubjectiveText('  Boite depuis 3 jours  ', $at);
        $consultation->recordTypedVital(VitalType::HEART_RATE, '92', $user, $at);
        $consultation->recordTypedVital(VitalType::MUCOUS_MEMBRANES, 'Roses', $user, $at);
        $consultation->recordExamSystem(
            BodySystem::CARDIOVASCULAR,
            ExamStatus::ANOMALY,
            'Souffle systolique',
            ['fc' => '120', 'rhythm' => 'régulier', 'murmur' => 'grade 2'],
            $user,
            $at,
        );
        $consultation->recordExamSystem(BodySystem::RESPIRATORY, ExamStatus::NORMAL, null, [], $user, $at);
        $consultation->updateObjectiveObservations('Muqueuses roses, TRC < 2 s', $at);
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
        // Priced act — feeds the billing draft.
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
        // Billable kind but no price snapshot — must not reach the billing draft.
        $consultation->addPlanAction(
            PlanActionKind::PERFORMED_ACT,
            'Pesée',
            null,
            null,
            null,
            null,
            1.0,
            null,
            null,
            null,
            $user,
            $at,
        );
        $consultation->addPlanAction(
            PlanActionKind::FOLLOW_UP_APPOINTMENT,
            'Revoir dans 10 jours',
            null,
            null,
            null,
            10,
            1.0,
            null,
            null,
            null,
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

        $expectedMotifs      = $consultation->getMotifs();
        $expectedVitals      = $consultation->getTypedVitals();
        $expectedExams       = $consultation->getExamSystems();
        $expectedDiagnoses   = $consultation->getDiagnoses();
        $expectedActions     = $consultation->getPlanActions();
        $expectedPrescribed  = $consultation->getPrescriptionLines();
        $expectedBillingLine = $consultation->getBillingLines();

        $this->repository->save($consultation);

        $loaded = $this->repository->findById($consultation->getId());
        self::assertNotNull($loaded);

        // ── Free-text columns ────────────────────────────────────────────
        self::assertSame('Boite depuis 3 jours', $loaded->getSubjectiveText());
        self::assertSame('Muqueuses roses, TRC < 2 s', $loaded->getObjectiveObservations());
        self::assertSame('Rappeler le client demain', $loaded->getTeamMemo());

        // ── Motifs ───────────────────────────────────────────────────────
        $motifs = $loaded->getMotifs();
        self::assertCount(2, $motifs);
        self::assertSame($expectedMotifs[0]->getId(), $motifs[0]->getId());
        self::assertSame('Boiterie', $motifs[0]->getLabel());
        self::assertSame($expectedMotifs[1]->getId(), $motifs[1]->getId());
        self::assertSame('Vomissements', $motifs[1]->getLabel());

        // ── Typed vitals ─────────────────────────────────────────────────
        $typedVitals = $loaded->getTypedVitals();
        self::assertCount(2, $typedVitals);
        self::assertSame($expectedVitals[0]->getId(), $typedVitals[0]->getId());
        self::assertSame(VitalType::HEART_RATE, $typedVitals[0]->getType());
        self::assertSame('92', $typedVitals[0]->getValue());
        self::assertSame('2026-04-10 09:00:00', $typedVitals[0]->getRecordedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $typedVitals[0]->getRecordedByUserId());
        self::assertSame($expectedVitals[1]->getId(), $typedVitals[1]->getId());
        self::assertSame(VitalType::MUCOUS_MEMBRANES, $typedVitals[1]->getType());
        self::assertSame('Roses', $typedVitals[1]->getValue());
        self::assertSame('2026-04-10 09:00:00', $typedVitals[1]->getRecordedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $typedVitals[1]->getRecordedByUserId());

        // ── Exam systems ─────────────────────────────────────────────────
        $exams = $loaded->getExamSystems();
        self::assertCount(2, $exams);
        self::assertSame($expectedExams[0]->getId(), $exams[0]->getId());
        self::assertSame(BodySystem::CARDIOVASCULAR, $exams[0]->getSystem());
        self::assertSame(ExamStatus::ANOMALY, $exams[0]->getStatus());
        self::assertSame('Souffle systolique', $exams[0]->getNotes());
        self::assertSame(
            ['fc' => '120', 'rhythm' => 'régulier', 'murmur' => 'grade 2'],
            $exams[0]->getStructuredData(),
        );
        self::assertSame('2026-04-10 09:00:00', $exams[0]->getRecordedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $exams[0]->getRecordedByUserId());
        self::assertSame($expectedExams[1]->getId(), $exams[1]->getId());
        self::assertSame(BodySystem::RESPIRATORY, $exams[1]->getSystem());
        self::assertSame(ExamStatus::NORMAL, $exams[1]->getStatus());
        self::assertNull($exams[1]->getNotes());
        self::assertSame([], $exams[1]->getStructuredData());
        self::assertSame('2026-04-10 09:00:00', $exams[1]->getRecordedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $exams[1]->getRecordedByUserId());

        // ── Diagnoses ────────────────────────────────────────────────────
        $diagnoses = $loaded->getDiagnoses();
        self::assertCount(2, $diagnoses);
        self::assertSame($expectedDiagnoses[0]->getId(), $diagnoses[0]->getId());
        self::assertSame('OTI-01', $diagnoses[0]->getCode());
        self::assertSame('Otite externe', $diagnoses[0]->getLabel());
        self::assertSame(DiagnosisCertainty::PROBABLE, $diagnoses[0]->getCertainty());
        self::assertSame('Oreille droite', $diagnoses[0]->getNote());
        self::assertTrue($diagnoses[0]->isPrimary());
        self::assertSame(DiagnosisSource::MANUAL, $diagnoses[0]->getSource());
        self::assertSame('2026-04-10 09:00:00', $diagnoses[0]->getCreatedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $diagnoses[0]->getCreatedByUserId());
        self::assertSame($expectedDiagnoses[1]->getId(), $diagnoses[1]->getId());
        self::assertNull($diagnoses[1]->getCode());
        self::assertSame('Dermatite allergique', $diagnoses[1]->getLabel());
        self::assertSame(DiagnosisCertainty::POSSIBLE, $diagnoses[1]->getCertainty());
        self::assertNull($diagnoses[1]->getNote());
        self::assertFalse($diagnoses[1]->isPrimary());
        self::assertSame(DiagnosisSource::AI_SUGGESTION, $diagnoses[1]->getSource());
        self::assertSame('2026-04-10 09:00:00', $diagnoses[1]->getCreatedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $diagnoses[1]->getCreatedByUserId());

        // ── Plan actions ─────────────────────────────────────────────────
        $planActions = $loaded->getPlanActions();
        self::assertCount(4, $planActions);

        self::assertSame($expectedActions[0]->getId(), $planActions[0]->getId());
        self::assertSame(PlanActionKind::PERFORMED_ACT, $planActions[0]->getKind());
        self::assertSame('Otoscopie', $planActions[0]->getDescription());
        self::assertSame('ACT-OTO', $planActions[0]->getCatalogCode());
        self::assertNull($planActions[0]->getPosology());
        self::assertNull($planActions[0]->getDurationDays());
        self::assertNull($planActions[0]->getFollowUpDays());
        self::assertSame(2.0, $planActions[0]->getQuantity());
        self::assertSame(3500, $planActions[0]->getUnitPriceMinorUnits());
        self::assertSame('EUR', $planActions[0]->getCurrency());
        self::assertSame('TVA20', $planActions[0]->getTaxCategoryCode());
        self::assertSame('2026-04-10 09:00:00', $planActions[0]->getCreatedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $planActions[0]->getCreatedByUserId());

        self::assertSame($expectedActions[1]->getId(), $planActions[1]->getId());
        self::assertSame(PlanActionKind::PERFORMED_ACT, $planActions[1]->getKind());
        self::assertSame('Pesée', $planActions[1]->getDescription());
        self::assertNull($planActions[1]->getCatalogCode());
        self::assertNull($planActions[1]->getPosology());
        self::assertNull($planActions[1]->getDurationDays());
        self::assertNull($planActions[1]->getFollowUpDays());
        self::assertSame(1.0, $planActions[1]->getQuantity());
        self::assertNull($planActions[1]->getUnitPriceMinorUnits());
        self::assertNull($planActions[1]->getCurrency());
        self::assertNull($planActions[1]->getTaxCategoryCode());
        self::assertSame('2026-04-10 09:00:00', $planActions[1]->getCreatedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $planActions[1]->getCreatedByUserId());

        self::assertSame($expectedActions[2]->getId(), $planActions[2]->getId());
        self::assertSame(PlanActionKind::FOLLOW_UP_APPOINTMENT, $planActions[2]->getKind());
        self::assertSame('Revoir dans 10 jours', $planActions[2]->getDescription());
        self::assertNull($planActions[2]->getCatalogCode());
        self::assertNull($planActions[2]->getPosology());
        self::assertNull($planActions[2]->getDurationDays());
        self::assertSame(10, $planActions[2]->getFollowUpDays());
        self::assertSame(1.0, $planActions[2]->getQuantity());
        self::assertNull($planActions[2]->getUnitPriceMinorUnits());
        self::assertNull($planActions[2]->getCurrency());
        self::assertNull($planActions[2]->getTaxCategoryCode());
        self::assertSame('2026-04-10 09:00:00', $planActions[2]->getCreatedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $planActions[2]->getCreatedByUserId());

        self::assertSame($expectedActions[3]->getId(), $planActions[3]->getId());
        self::assertSame(PlanActionKind::ADVICE, $planActions[3]->getKind());
        self::assertSame('Nettoyer les oreilles', $planActions[3]->getDescription());
        self::assertNull($planActions[3]->getCatalogCode());
        self::assertSame('2 fois par jour', $planActions[3]->getPosology());
        self::assertSame(7, $planActions[3]->getDurationDays());
        self::assertNull($planActions[3]->getFollowUpDays());
        self::assertSame(1.0, $planActions[3]->getQuantity());
        self::assertNull($planActions[3]->getUnitPriceMinorUnits());
        self::assertNull($planActions[3]->getCurrency());
        self::assertNull($planActions[3]->getTaxCategoryCode());
        self::assertSame('2026-04-10 09:00:00', $planActions[3]->getCreatedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $planActions[3]->getCreatedByUserId());

        // ── Prescription lines ───────────────────────────────────────────
        $prescriptionLines = $loaded->getPrescriptionLines();
        self::assertCount(1, $prescriptionLines);
        self::assertSame($expectedPrescribed[0]->getId(), $prescriptionLines[0]->getId());
        self::assertSame(self::ARTICLE_ID, $prescriptionLines[0]->getArticleId());
        self::assertSame('MED-OTO', $prescriptionLines[0]->getCode());
        self::assertSame('Otomax', $prescriptionLines[0]->getLabel());
        self::assertSame('1 goutte', $prescriptionLines[0]->getDose());
        self::assertSame('2 fois par jour', $prescriptionLines[0]->getFrequency());
        self::assertSame(7, $prescriptionLines[0]->getDurationDays());
        self::assertSame('Auriculaire', $prescriptionLines[0]->getRoute());
        self::assertSame(3.0, $prescriptionLines[0]->getQuantity());
        self::assertSame(1250, $prescriptionLines[0]->getUnitPriceMinorUnits());
        self::assertSame('EUR', $prescriptionLines[0]->getCurrency());
        self::assertSame('TVA10', $prescriptionLines[0]->getTaxCategoryCode());
        self::assertSame('2026-04-10 09:00:00', $prescriptionLines[0]->getCreatedAtUtc()->format('Y-m-d H:i:s'));
        self::assertSame(self::USER_ID, $prescriptionLines[0]->getCreatedByUserId());
        self::assertSame(
            '1 goutte · 2 fois par jour · 7 j · Auriculaire',
            $prescriptionLines[0]->getPosologySummary(),
        );

        // ── Derived billing lines ────────────────────────────────────────
        $billingLines = $loaded->getBillingLines();
        self::assertCount(2, $billingLines);
        self::assertSame($expectedBillingLine[0]->getId(), $billingLines[0]->getId());
        self::assertSame($expectedActions[0]->getId(), $billingLines[0]->getSourceLineId());
        self::assertSame(BillingLineSource::PLAN_ACT, $billingLines[0]->getSource());
        self::assertSame('Otoscopie', $billingLines[0]->getLabel());
        self::assertSame('ACT-OTO', $billingLines[0]->getCode());
        self::assertSame(2.0, $billingLines[0]->getQuantity());
        self::assertSame(3500, $billingLines[0]->getUnitPriceMinorUnits());
        self::assertSame('EUR', $billingLines[0]->getCurrency());
        self::assertSame('TVA20', $billingLines[0]->getTaxCategoryCode());
        self::assertSame(7000, $billingLines[0]->getTotalMinorUnits());

        self::assertSame($expectedBillingLine[1]->getId(), $billingLines[1]->getId());
        self::assertSame($expectedPrescribed[0]->getId(), $billingLines[1]->getSourceLineId());
        self::assertSame(BillingLineSource::PRESCRIPTION, $billingLines[1]->getSource());
        self::assertSame('Otomax', $billingLines[1]->getLabel());
        self::assertSame('MED-OTO', $billingLines[1]->getCode());
        self::assertSame(3.0, $billingLines[1]->getQuantity());
        self::assertSame(1250, $billingLines[1]->getUnitPriceMinorUnits());
        self::assertSame('EUR', $billingLines[1]->getCurrency());
        self::assertSame('TVA10', $billingLines[1]->getTaxCategoryCode());
        self::assertSame(3750, $billingLines[1]->getTotalMinorUnits());
    }

    /**
     * Re-saving an aggregate that was loaded from the database must update the
     * existing child rows in place instead of deleting and re-inserting them,
     * which would collide with Doctrine's identity map on the same primary key.
     */
    public function testSecondSaveSyncsChildRowsByIdWithoutLosingSurvivors(): void
    {
        $user = UserId::fromString(self::USER_ID);
        $at   = new \DateTimeImmutable('2026-04-10 09:00:00');

        $consultation = $this->startConsultation($at);
        $consultation->addDiagnosis(
            'D-A',
            'Otite externe',
            DiagnosisCertainty::PROBABLE,
            null,
            true,
            DiagnosisSource::MANUAL,
            $user,
            $at,
        );
        $consultation->addDiagnosis(
            'D-B',
            'Gastrite',
            DiagnosisCertainty::POSSIBLE,
            null,
            false,
            DiagnosisSource::MANUAL,
            $user,
            $at,
        );
        $consultation->addDiagnosis(
            'D-C',
            'Arthrose',
            DiagnosisCertainty::CERTAIN,
            null,
            false,
            DiagnosisSource::MANUAL,
            $user,
            $at,
        );
        $consultation->addPrescriptionLine(
            null,
            'MED-1',
            'Otomax',
            null,
            null,
            null,
            null,
            2.0,
            1000,
            'EUR',
            null,
            $user,
            $at,
        );
        $consultation->addPrescriptionLine(
            null,
            'MED-2',
            'Metacam',
            null,
            null,
            null,
            null,
            1.0,
            2000,
            'EUR',
            null,
            $user,
            $at,
        );

        $this->repository->save($consultation);

        $reloaded = $this->repository->findById($consultation->getId());
        self::assertNotNull($reloaded);

        $diagnosisIds        = array_map(static fn ($d): string => $d->getId(), $reloaded->getDiagnoses());
        $prescriptionLineIds = array_map(static fn ($l): string => $l->getId(), $reloaded->getPrescriptionLines());
        $billingLineIds      = array_map(static fn ($l): string => $l->getId(), $reloaded->getBillingLines());
        self::assertCount(3, $diagnosisIds);
        self::assertCount(2, $prescriptionLineIds);
        self::assertCount(2, $billingLineIds);

        $later = new \DateTimeImmutable('2026-04-10 10:00:00');

        // Update the first diagnosis, drop the second, append a fourth one and
        // remove the first prescription line.
        $reloaded->updateDiagnosis(
            $diagnosisIds[0],
            'D-A2',
            'Otite moyenne',
            DiagnosisCertainty::CERTAIN,
            'Aggravation',
            $later,
        );
        $reloaded->removeDiagnosis($diagnosisIds[1], $later);
        $reloaded->addDiagnosis(
            'D-D',
            'Conjonctivite',
            DiagnosisCertainty::POSSIBLE,
            null,
            false,
            DiagnosisSource::AI_SUGGESTION,
            $user,
            $later,
        );
        $reloaded->removePrescriptionLine($prescriptionLineIds[0], $later);

        $this->repository->save($reloaded);

        $final = $this->repository->findById($consultation->getId());
        self::assertNotNull($final);

        // Survivors kept their ids, the removed row is gone, the new row is last.
        $finalDiagnoses = $final->getDiagnoses();
        self::assertCount(3, $finalDiagnoses);
        self::assertSame($diagnosisIds[0], $finalDiagnoses[0]->getId());
        self::assertSame('D-A2', $finalDiagnoses[0]->getCode());
        self::assertSame('Otite moyenne', $finalDiagnoses[0]->getLabel());
        self::assertSame(DiagnosisCertainty::CERTAIN, $finalDiagnoses[0]->getCertainty());
        self::assertSame('Aggravation', $finalDiagnoses[0]->getNote());
        self::assertTrue($finalDiagnoses[0]->isPrimary());
        self::assertSame($diagnosisIds[2], $finalDiagnoses[1]->getId());
        self::assertSame('Arthrose', $finalDiagnoses[1]->getLabel());
        self::assertNotContains($diagnosisIds[1], array_map(
            static fn ($d): string => $d->getId(),
            $finalDiagnoses,
        ));
        self::assertSame('Conjonctivite', $finalDiagnoses[2]->getLabel());
        self::assertNotContains($finalDiagnoses[2]->getId(), $diagnosisIds);

        // The orphaned diagnosis row really left the database.
        self::assertNull($this->findChildById(DiagnosisEntity::class, $diagnosisIds[1]));
        self::assertNotNull($this->findChildById(DiagnosisEntity::class, $diagnosisIds[0]));

        // Prescription lines: only the second survives, with its original id.
        $finalPrescriptionLines = $final->getPrescriptionLines();
        self::assertCount(1, $finalPrescriptionLines);
        self::assertSame($prescriptionLineIds[1], $finalPrescriptionLines[0]->getId());
        self::assertSame('Metacam', $finalPrescriptionLines[0]->getLabel());
        self::assertNull($this->findChildById(PrescriptionLineEntity::class, $prescriptionLineIds[0]));

        // The derived billing line of the survivor kept its own id too.
        $finalBillingLines = $final->getBillingLines();
        self::assertCount(1, $finalBillingLines);
        self::assertSame($billingLineIds[1], $finalBillingLines[0]->getId());
        self::assertSame($prescriptionLineIds[1], $finalBillingLines[0]->getSourceLineId());
        self::assertSame(2000, $finalBillingLines[0]->getTotalMinorUnits());
    }

    public function testChildCollectionsRoundTripInInsertionOrder(): void
    {
        $user = UserId::fromString(self::USER_ID);
        $at   = new \DateTimeImmutable('2026-04-10 09:00:00');

        $consultation = $this->startConsultation($at);
        // Deliberately non-alphabetical so that only the stored position can
        // produce this ordering back.
        $consultation->setMotifs(['Zèbre', 'Alpha', 'Mésange'], $at);

        foreach (['Zulu', 'Alpha', 'Mike'] as $description) {
            $consultation->addPlanAction(
                PlanActionKind::ADVICE,
                $description,
                null,
                null,
                null,
                null,
                1.0,
                null,
                null,
                null,
                $user,
                $at,
            );
        }

        $this->repository->save($consultation);

        $loaded = $this->repository->findById($consultation->getId());
        self::assertNotNull($loaded);

        self::assertSame(
            ['Zèbre', 'Alpha', 'Mésange'],
            array_map(static fn ($motif): string => $motif->getLabel(), $loaded->getMotifs()),
        );
        self::assertSame([0, 1, 2], $this->childPositions(MotifEntity::class));

        self::assertSame(
            ['Zulu', 'Alpha', 'Mike'],
            array_map(static fn ($action): string => $action->getDescription(), $loaded->getPlanActions()),
        );
        self::assertSame([0, 1, 2], $this->childPositions(PlanActionEntity::class));

        // Removing the head renumbers the remaining rows without reshuffling them.
        $later       = new \DateTimeImmutable('2026-04-10 10:00:00');
        $firstAction = $loaded->getPlanActions()[0];
        $loaded->removePlanAction($firstAction->getId(), $later);
        $this->repository->save($loaded);

        $final = $this->repository->findById($consultation->getId());
        self::assertNotNull($final);
        self::assertSame(
            ['Alpha', 'Mike'],
            array_map(static fn ($action): string => $action->getDescription(), $final->getPlanActions()),
        );
        self::assertSame([0, 1], $this->childPositions(PlanActionEntity::class));
    }

    private function startConsultation(\DateTimeImmutable $at): Consultation
    {
        return Consultation::startFromAdmission(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            $at,
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    /**
     * @template TEntity of ConsultationChildEntity
     *
     * @param class-string<TEntity> $entityClass
     *
     * @return TEntity|null
     */
    private function findChildById(string $entityClass, string $id): ?ConsultationChildEntity
    {
        return $this->entityManager()
            ->getRepository($entityClass)
            ->findOneBy(['id' => Uuid::fromString($id)->toBinary()])
        ;
    }

    /**
     * @param class-string<MotifEntity|PlanActionEntity> $entityClass
     *
     * @return list<int>
     */
    private function childPositions(string $entityClass): array
    {
        $entities = $this->entityManager()->getRepository($entityClass)->findBy(
            ['consultationId' => Uuid::fromString(self::CONSULTATION_ID)->toBinary()],
            ['position' => 'ASC'],
        );

        return array_map(static fn ($entity): int => $entity->getPosition(), $entities);
    }
}
