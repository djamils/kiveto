<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain;

use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Event\ConsultationChiefComplaintRecorded;
use App\Context\Consultation\Domain\Event\ConsultationClinicalNoteAdded;
use App\Context\Consultation\Domain\Event\ConsultationClosed;
use App\Context\Consultation\Domain\Event\ConsultationPerformedActAdded;
use App\Context\Consultation\Domain\Event\ConsultationStartedFromAdmission;
use App\Context\Consultation\Domain\Event\ConsultationStartedFromAppointment;
use App\Context\Consultation\Domain\Event\ConsultationVitalsRecorded;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\BillingLineRecord;
use App\Context\Consultation\Domain\ValueObject\BillingLineSource;
use App\Context\Consultation\Domain\ValueObject\BodySystem;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\DiagnosisRecord;
use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use App\Context\Consultation\Domain\ValueObject\ExamStatus;
use App\Context\Consultation\Domain\ValueObject\ExamSystemRecord;
use App\Context\Consultation\Domain\ValueObject\MotifTag;
use App\Context\Consultation\Domain\ValueObject\NoteType;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\PlanActionRecord;
use App\Context\Consultation\Domain\ValueObject\PrescriptionLineRecord;
use App\Context\Consultation\Domain\ValueObject\TypedVitalRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use App\Context\Consultation\Domain\ValueObject\VitalType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsultationTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ADMISSION_ID    = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID      = '66666666-6666-4666-8666-666666666666';

    public function testStartFromAppointment(): void
    {
        $startedAt = new \DateTimeImmutable('2026-04-10 09:00:00');

        $consultation = Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            $startedAt,
        );

        self::assertSame(self::CONSULTATION_ID, $consultation->getId()->toString());
        self::assertSame(self::CLINIC_ID, $consultation->getClinicId()->toString());
        self::assertSame(self::APPOINTMENT_ID, $consultation->getAppointmentId()?->toString());
        self::assertSame(self::ADMISSION_ID, $consultation->getAdmissionId()->toString());
        self::assertSame(self::PATIENT_ID, $consultation->getPatientId()->toString());
        self::assertSame(self::USER_ID, $consultation->getPractitionerUserId()->toString());
        self::assertSame(ConsultationStatus::OPEN, $consultation->getStatus());
        self::assertNull($consultation->getChiefComplaint());
        self::assertNull($consultation->getVitals());
        self::assertNull($consultation->getSummary());
        self::assertNull($consultation->getClosedAtUtc());
        self::assertSame($startedAt, $consultation->getStartedAtUtc());
        self::assertSame($startedAt, $consultation->getCreatedAtUtc());
        self::assertSame($startedAt, $consultation->getUpdatedAtUtc());
        self::assertSame([], $consultation->getNotes());
        self::assertSame([], $consultation->getActs());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationStartedFromAppointment::class, $events[0]);
    }

    public function testStartFromAdmission(): void
    {
        $consultation = Consultation::startFromAdmission(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );

        self::assertSame(self::ADMISSION_ID, $consultation->getAdmissionId()->toString());
        self::assertSame(self::PATIENT_ID, $consultation->getPatientId()->toString());
        self::assertNull($consultation->getAppointmentId());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationStartedFromAdmission::class, $events[0]);
    }

    public function testRecordChiefComplaint(): void
    {
        $consultation = $this->makeOpenConsultation();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $consultation->recordChiefComplaint(
            'Limping right paw for 3 days',
            new \DateTimeImmutable('2026-04-10 09:05:00'),
        );

        self::assertSame('Limping right paw for 3 days', $consultation->getChiefComplaint());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationChiefComplaintRecorded::class, $events[0]);
    }

    public function testRecordChiefComplaintRejectsEmptyContent(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chief complaint cannot be empty');

        $consultation->recordChiefComplaint('   ', new \DateTimeImmutable('2026-04-10 09:05:00'));
    }

    public function testRecordVitals(): void
    {
        $consultation = $this->makeOpenConsultation();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $vitals = Vitals::create(weightKg: 12.5, temperatureC: 38.2);
        $consultation->recordVitals($vitals, new \DateTimeImmutable('2026-04-10 09:10:00'));

        self::assertSame($vitals, $consultation->getVitals());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationVitalsRecorded::class, $events[0]);
    }

    public function testAddClinicalNote(): void
    {
        $consultation = $this->makeOpenConsultation();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $consultation->addClinicalNote(
            NoteType::DIAGNOSIS,
            'Otitis externa',
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:15:00'),
        );

        self::assertCount(1, $consultation->getNotes());
        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationClinicalNoteAdded::class, $events[0]);
    }

    public function testAddPerformedAct(): void
    {
        $consultation = $this->makeOpenConsultation();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $consultation->addPerformedAct(
            'Otoscopy',
            1.0,
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:20:00'),
        );

        self::assertCount(1, $consultation->getActs());
        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationPerformedActAdded::class, $events[0]);
    }

    public function testCloseConsultation(): void
    {
        $consultation = $this->makeOpenConsultation();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $consultation->close(
            UserId::fromString(self::USER_ID),
            'All good',
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        self::assertSame(ConsultationStatus::CLOSED, $consultation->getStatus());
        self::assertSame('All good', $consultation->getSummary());
        self::assertNotNull($consultation->getClosedAtUtc());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationClosed::class, $events[0]);
    }

    public function testCannotCloseTwice(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:05:00'),
        );
    }

    public function testCannotRecordVitalsOnClosedConsultation(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $consultation->recordVitals(
            Vitals::create(10.0, null),
            new \DateTimeImmutable('2026-04-10 10:05:00'),
        );
    }

    public function testCannotRecordChiefComplaintOnClosedConsultation(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $consultation->recordChiefComplaint('Late', new \DateTimeImmutable('2026-04-10 10:05:00'));
    }

    public function testCannotAddNoteOnClosedConsultation(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $consultation->addClinicalNote(
            NoteType::GENERAL,
            'Late note',
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 10:05:00'),
        );
    }

    public function testCannotAddPerformedActOnClosedConsultation(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $consultation->addPerformedAct(
            'Late act',
            1.0,
            new \DateTimeImmutable('2026-04-10 10:05:00'),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 10:05:00'),
        );
    }

    public function testReconstituteRehydratesWithoutEvents(): void
    {
        $consultation = Consultation::reconstitute(
            id: ConsultationId::fromString(self::CONSULTATION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            appointmentId: null,
            admissionId: AdmissionId::fromString(self::ADMISSION_ID),
            patientId: PatientId::fromString(self::PATIENT_ID),
            practitionerUserId: UserId::fromString(self::USER_ID),
            status: ConsultationStatus::CLOSED,
            chiefComplaint: 'Pre-existing',
            vitals: Vitals::create(10.0, 38.0),
            summary: 'Done',
            startedAtUtc: new \DateTimeImmutable('2026-04-10 09:00:00'),
            closedAtUtc: new \DateTimeImmutable('2026-04-10 10:00:00'),
            createdAtUtc: new \DateTimeImmutable('2026-04-10 09:00:00'),
            updatedAtUtc: new \DateTimeImmutable('2026-04-10 10:00:00'),
            notes: [],
            acts: [],
        );

        self::assertSame(ConsultationStatus::CLOSED, $consultation->getStatus());
        self::assertCount(0, $consultation->recordedDomainEvents());
    }

    // ── Cockpit: motifs ──────────────────────────────────────────────────

    public function testSetMotifsDeduplicatesLabelsDifferingOnlyByCase(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->setMotifs(
            ['Vomissements', 'vomissements', ' VOMISSEMENTS ', 'Diarrhée'],
            self::at('09:30:00'),
        );

        $motifs = $consultation->getMotifs();
        self::assertCount(2, $motifs);
        self::assertSame('Vomissements', $motifs[0]->getLabel());
        self::assertSame('Diarrhée', $motifs[1]->getLabel());
        self::assertSame('2026-04-10 09:30:00', $consultation->getUpdatedAtUtc()->format('Y-m-d H:i:s'));
    }

    public function testSetMotifsRejectsMoreThanTwentyLabels(): void
    {
        $consultation = $this->makeOpenConsultation();

        $labels = [];
        for ($index = 0; $index < 21; ++$index) {
            $labels[] = 'Motif ' . $index;
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A consultation cannot carry more than 20 motifs');

        $consultation->setMotifs($labels, self::at('09:30:00'));
    }

    public function testSetMotifsReplacesThePreviousSet(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->setMotifs(['Vomissements', 'Diarrhée'], self::at('09:30:00'));

        $consultation->setMotifs(['Toux'], self::at('09:35:00'));

        $motifs = $consultation->getMotifs();
        self::assertCount(1, $motifs);
        self::assertSame('Toux', $motifs[0]->getLabel());
    }

    public function testSetMotifsWithAnEmptyListClearsTheStrip(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->setMotifs(['Toux'], self::at('09:30:00'));

        $consultation->setMotifs([], self::at('09:35:00'));

        self::assertSame([], $consultation->getMotifs());
    }

    // ── Cockpit: typed vitals ────────────────────────────────────────────

    public function testRecordTypedVitalUpsertsByType(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->recordTypedVital(VitalType::HEART_RATE, '90', self::user(), self::at('09:30:00'));
        $consultation->recordTypedVital(VitalType::RESPIRATORY_RATE, '20', self::user(), self::at('09:31:00'));
        $consultation->recordTypedVital(VitalType::HEART_RATE, '110', self::user(), self::at('09:32:00'));

        $vitals = $consultation->getTypedVitals();
        self::assertCount(2, $vitals);
        self::assertSame(VitalType::RESPIRATORY_RATE, $vitals[0]->getType());
        self::assertSame('20', $vitals[0]->getValue());
        self::assertSame(VitalType::HEART_RATE, $vitals[1]->getType());
        self::assertSame('110', $vitals[1]->getValue());
    }

    public function testRemoveTypedVital(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->recordTypedVital(VitalType::HEART_RATE, '90', self::user(), self::at('09:30:00'));
        $consultation->recordTypedVital(VitalType::PAIN_SCORE, '2', self::user(), self::at('09:31:00'));

        $consultation->removeTypedVital(VitalType::HEART_RATE, self::at('09:32:00'));

        $vitals = $consultation->getTypedVitals();
        self::assertCount(1, $vitals);
        self::assertSame(VitalType::PAIN_SCORE, $vitals[0]->getType());
        self::assertSame('2026-04-10 09:32:00', $consultation->getUpdatedAtUtc()->format('Y-m-d H:i:s'));
    }

    public function testRemoveTypedVitalRejectsAnUnrecordedType(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Typed vital not found');

        $consultation->removeTypedVital(VitalType::GLYCEMIA, self::at('09:30:00'));
    }

    // ── Cockpit: free-text fields ────────────────────────────────────────

    public function testUpdateSubjectiveTextTrimsTheContent(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->updateSubjectiveText("  Boite depuis 3 jours \n", self::at('09:30:00'));

        self::assertSame('Boite depuis 3 jours', $consultation->getSubjectiveText());
    }

    public function testUpdateSubjectiveTextTurnsBlankContentIntoNull(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->updateSubjectiveText('Something', self::at('09:30:00'));

        $consultation->updateSubjectiveText('   ', self::at('09:31:00'));

        self::assertNull($consultation->getSubjectiveText());
    }

    public function testUpdateSubjectiveTextAcceptsNull(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->updateSubjectiveText('Something', self::at('09:30:00'));

        $consultation->updateSubjectiveText(null, self::at('09:31:00'));

        self::assertNull($consultation->getSubjectiveText());
    }

    public function testUpdateSubjectiveTextRejectsOversizedContent(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subjective text cannot exceed 20000 characters');

        $consultation->updateSubjectiveText(str_repeat('a', 20001), self::at('09:30:00'));
    }

    public function testUpdateObjectiveObservationsTrimsTheContent(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->updateObjectiveObservations('  Muqueuses roses  ', self::at('09:30:00'));

        self::assertSame('Muqueuses roses', $consultation->getObjectiveObservations());
    }

    public function testUpdateObjectiveObservationsTurnsBlankContentIntoNull(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->updateObjectiveObservations('Muqueuses roses', self::at('09:30:00'));

        $consultation->updateObjectiveObservations('', self::at('09:31:00'));

        self::assertNull($consultation->getObjectiveObservations());
    }

    public function testUpdateObjectiveObservationsRejectsOversizedContent(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Objective observations cannot exceed 20000 characters');

        $consultation->updateObjectiveObservations(str_repeat('b', 20001), self::at('09:30:00'));
    }

    public function testUpdateTeamMemoTrimsTheContent(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->updateTeamMemo("\t Rappeler le propriétaire ", self::at('09:30:00'));

        self::assertSame('Rappeler le propriétaire', $consultation->getTeamMemo());
    }

    public function testUpdateTeamMemoTurnsBlankContentIntoNull(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->updateTeamMemo('Rappeler le propriétaire', self::at('09:30:00'));

        $consultation->updateTeamMemo('  ', self::at('09:31:00'));

        self::assertNull($consultation->getTeamMemo());
    }

    public function testUpdateTeamMemoRejectsOversizedContent(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Team memo cannot exceed 20000 characters');

        $consultation->updateTeamMemo(str_repeat('c', 20001), self::at('09:30:00'));
    }

    // ── Cockpit: exam systems ────────────────────────────────────────────

    public function testRecordExamSystemUpsertsBySystem(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->recordExamSystem(
            BodySystem::CARDIOVASCULAR,
            ExamStatus::UNTESTED,
            'À revoir',
            [],
            self::user(),
            self::at('09:30:00'),
        );
        $consultation->recordExamSystem(
            BodySystem::RESPIRATORY,
            ExamStatus::NORMAL,
            null,
            [],
            self::user(),
            self::at('09:31:00'),
        );
        $consultation->recordExamSystem(
            BodySystem::CARDIOVASCULAR,
            ExamStatus::ANOMALY,
            'Souffle grade 3',
            ['fc' => '160', 'rhythm' => '  '],
            self::user(),
            self::at('09:32:00'),
        );

        self::assertCount(2, $consultation->getExamSystems());

        $cardio = self::findExamSystem($consultation, BodySystem::CARDIOVASCULAR);
        self::assertSame(ExamStatus::ANOMALY, $cardio->getStatus());
        self::assertSame('Souffle grade 3', $cardio->getNotes());
        self::assertSame(['fc' => '160'], $cardio->getStructuredData());
    }

    public function testMarkAllSystemsNormalCreatesMissingSystems(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->markAllSystemsNormal(
            [BodySystem::CARDIOVASCULAR, BodySystem::RESPIRATORY],
            self::user(),
            self::at('09:30:00'),
        );

        self::assertCount(2, $consultation->getExamSystems());
        self::assertSame(
            ExamStatus::NORMAL,
            self::findExamSystem($consultation, BodySystem::CARDIOVASCULAR)->getStatus(),
        );
        self::assertSame(
            ExamStatus::NORMAL,
            self::findExamSystem($consultation, BodySystem::RESPIRATORY)->getStatus(),
        );
        self::assertSame('2026-04-10 09:30:00', $consultation->getUpdatedAtUtc()->format('Y-m-d H:i:s'));
    }

    public function testMarkAllSystemsNormalFlipsExistingNonAnomalySystems(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->recordExamSystem(
            BodySystem::RESPIRATORY,
            ExamStatus::UNTESTED,
            'Non ausculté',
            ['note' => 'x'],
            self::user(),
            self::at('09:30:00'),
        );
        $existingId = self::findExamSystem($consultation, BodySystem::RESPIRATORY)->getId();

        $consultation->markAllSystemsNormal([BodySystem::RESPIRATORY], self::user(), self::at('09:31:00'));

        $respiratory = self::findExamSystem($consultation, BodySystem::RESPIRATORY);
        self::assertCount(1, $consultation->getExamSystems());
        self::assertSame($existingId, $respiratory->getId());
        self::assertSame(ExamStatus::NORMAL, $respiratory->getStatus());
        self::assertSame('Non ausculté', $respiratory->getNotes());
        self::assertSame(['note' => 'x'], $respiratory->getStructuredData());
    }

    public function testMarkAllSystemsNormalLeavesAnomalySystemsUntouched(): void
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->recordExamSystem(
            BodySystem::CARDIOVASCULAR,
            ExamStatus::ANOMALY,
            'Souffle grade 3',
            ['fc' => '160'],
            self::user(),
            self::at('09:30:00'),
        );
        $anomalyId = self::findExamSystem($consultation, BodySystem::CARDIOVASCULAR)->getId();

        $consultation->markAllSystemsNormal(
            [BodySystem::CARDIOVASCULAR, BodySystem::RESPIRATORY],
            self::user(),
            self::at('09:31:00'),
        );

        $cardio = self::findExamSystem($consultation, BodySystem::CARDIOVASCULAR);
        self::assertSame($anomalyId, $cardio->getId());
        self::assertSame(ExamStatus::ANOMALY, $cardio->getStatus());
        self::assertSame('Souffle grade 3', $cardio->getNotes());
        self::assertSame(['fc' => '160'], $cardio->getStructuredData());
        self::assertSame(
            '2026-04-10 09:30:00',
            $cardio->getRecordedAtUtc()->format('Y-m-d H:i:s'),
        );
        self::assertSame(
            ExamStatus::NORMAL,
            self::findExamSystem($consultation, BodySystem::RESPIRATORY)->getStatus(),
        );
    }

    // ── Cockpit: diagnoses ───────────────────────────────────────────────

    public function testAddDiagnosis(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->addDiagnosis(
            'H60.3',
            'Otite externe',
            DiagnosisCertainty::PROBABLE,
            'Oreille droite',
            false,
            DiagnosisSource::MANUAL,
            self::user(),
            self::at('09:30:00'),
        );

        $diagnoses = $consultation->getDiagnoses();
        self::assertCount(1, $diagnoses);
        self::assertSame('H60.3', $diagnoses[0]->getCode());
        self::assertSame('Otite externe', $diagnoses[0]->getLabel());
        self::assertSame(DiagnosisCertainty::PROBABLE, $diagnoses[0]->getCertainty());
        self::assertSame('Oreille droite', $diagnoses[0]->getNote());
        self::assertFalse($diagnoses[0]->isPrimary());
        self::assertSame(DiagnosisSource::MANUAL, $diagnoses[0]->getSource());
    }

    public function testAddingASecondPrimaryDiagnosisDemotesTheFirstOne(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addDiagnosis($consultation, 'Otite externe', true);

        $this->addDiagnosis($consultation, 'Gastro-entérite', true);

        $diagnoses = $consultation->getDiagnoses();
        self::assertCount(2, $diagnoses);
        self::assertFalse($diagnoses[0]->isPrimary());
        self::assertTrue($diagnoses[1]->isPrimary());
    }

    public function testUpdateDiagnosisKeepsIdentityAndPrimaryFlag(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addDiagnosis($consultation, 'Otite externe', true);
        $diagnosisId = $consultation->getDiagnoses()[0]->getId();

        $consultation->updateDiagnosis(
            $diagnosisId,
            'H60.9',
            'Otite externe bilatérale',
            DiagnosisCertainty::CERTAIN,
            'Confirmée à l\'otoscopie',
            self::at('09:35:00'),
        );

        $diagnosis = $consultation->getDiagnoses()[0];
        self::assertSame($diagnosisId, $diagnosis->getId());
        self::assertSame('H60.9', $diagnosis->getCode());
        self::assertSame('Otite externe bilatérale', $diagnosis->getLabel());
        self::assertSame(DiagnosisCertainty::CERTAIN, $diagnosis->getCertainty());
        self::assertSame('Confirmée à l\'otoscopie', $diagnosis->getNote());
        self::assertTrue($diagnosis->isPrimary());
    }

    public function testUpdateDiagnosisRejectsAnUnknownId(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Diagnosis not found');

        $consultation->updateDiagnosis(
            'unknown-diagnosis',
            null,
            'Otite',
            DiagnosisCertainty::CERTAIN,
            null,
            self::at('09:35:00'),
        );
    }

    public function testRemoveDiagnosis(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addDiagnosis($consultation, 'Otite externe', false);
        $this->addDiagnosis($consultation, 'Gastro-entérite', false);
        $removedId = $consultation->getDiagnoses()[0]->getId();

        $consultation->removeDiagnosis($removedId, self::at('09:40:00'));

        $diagnoses = $consultation->getDiagnoses();
        self::assertCount(1, $diagnoses);
        self::assertSame('Gastro-entérite', $diagnoses[0]->getLabel());
    }

    public function testRemoveDiagnosisRejectsAnUnknownId(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Diagnosis not found');

        $consultation->removeDiagnosis('unknown-diagnosis', self::at('09:40:00'));
    }

    public function testSetPrimaryDiagnosisPromotesOnlyOneDiagnosis(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addDiagnosis($consultation, 'Otite externe', true);
        $this->addDiagnosis($consultation, 'Gastro-entérite', false);
        $secondId = $consultation->getDiagnoses()[1]->getId();

        $consultation->setPrimaryDiagnosis($secondId, self::at('09:45:00'));

        $diagnoses = $consultation->getDiagnoses();
        self::assertFalse($diagnoses[0]->isPrimary());
        self::assertTrue($diagnoses[1]->isPrimary());
    }

    public function testSetPrimaryDiagnosisWithNullClearsTheFlag(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addDiagnosis($consultation, 'Otite externe', true);

        $consultation->setPrimaryDiagnosis(null, self::at('09:45:00'));

        self::assertFalse($consultation->getDiagnoses()[0]->isPrimary());
        self::assertSame('2026-04-10 09:45:00', $consultation->getUpdatedAtUtc()->format('Y-m-d H:i:s'));
    }

    public function testSetPrimaryDiagnosisRejectsAnUnknownId(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Diagnosis not found');

        $consultation->setPrimaryDiagnosis('unknown-diagnosis', self::at('09:45:00'));
    }

    // ── Cockpit: plan actions ────────────────────────────────────────────

    public function testAddPlanAction(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->addPlanAction(
            PlanActionKind::FOLLOW_UP_APPOINTMENT,
            'Contrôle',
            null,
            null,
            null,
            10,
            1.0,
            null,
            null,
            null,
            self::user(),
            self::at('09:50:00'),
        );

        $actions = $consultation->getPlanActions();
        self::assertCount(1, $actions);
        self::assertSame(PlanActionKind::FOLLOW_UP_APPOINTMENT, $actions[0]->getKind());
        self::assertSame('Contrôle', $actions[0]->getDescription());
        self::assertSame(10, $actions[0]->getFollowUpDays());
    }

    public function testUpdatePlanActionKeepsIdentityAndPriceSnapshot(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addBillableAct($consultation, 'Otoscopie', 1.0, 4500);
        $actionId = $consultation->getPlanActions()[0]->getId();

        $consultation->updatePlanAction(
            $actionId,
            'Otoscopie bilatérale',
            '2 fois par jour',
            5,
            7,
            3.0,
            self::at('09:55:00'),
        );

        $action = $consultation->getPlanActions()[0];
        self::assertSame($actionId, $action->getId());
        self::assertSame('Otoscopie bilatérale', $action->getDescription());
        self::assertSame('2 fois par jour', $action->getPosology());
        self::assertSame(5, $action->getDurationDays());
        self::assertSame(7, $action->getFollowUpDays());
        self::assertSame(3.0, $action->getQuantity());
        self::assertSame(4500, $action->getUnitPriceMinorUnits());
    }

    public function testUpdatePlanActionRejectsAnUnknownId(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Plan action not found');

        $consultation->updatePlanAction('unknown-action', 'Otoscopie', null, null, null, 1.0, self::at('09:55:00'));
    }

    public function testRemovePlanAction(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addBillableAct($consultation, 'Otoscopie', 1.0, 4500);
        $this->addBillableAct($consultation, 'Détartrage', 1.0, 12000);
        $removedId = $consultation->getPlanActions()[0]->getId();

        $consultation->removePlanAction($removedId, self::at('10:00:00'));

        $actions = $consultation->getPlanActions();
        self::assertCount(1, $actions);
        self::assertSame('Détartrage', $actions[0]->getDescription());
    }

    public function testRemovePlanActionRejectsAnUnknownId(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Plan action not found');

        $consultation->removePlanAction('unknown-action', self::at('10:00:00'));
    }

    // ── Cockpit: prescription ────────────────────────────────────────────

    public function testAddPrescriptionLine(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->addPrescription($consultation, 'Amoxicilline 250mg', 14.0, 320);

        $lines = $consultation->getPrescriptionLines();
        self::assertCount(1, $lines);
        self::assertSame('Amoxicilline 250mg', $lines[0]->getLabel());
        self::assertSame(14.0, $lines[0]->getQuantity());
        self::assertSame(320, $lines[0]->getUnitPriceMinorUnits());
        self::assertSame('EUR', $lines[0]->getCurrency());
    }

    public function testRemovePrescriptionLine(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addPrescription($consultation, 'Amoxicilline 250mg', 14.0, 320);
        $this->addPrescription($consultation, 'Méloxicam 1,5mg/ml', 1.0, 1850);
        $removedId = $consultation->getPrescriptionLines()[0]->getId();

        $consultation->removePrescriptionLine($removedId, self::at('10:05:00'));

        $lines = $consultation->getPrescriptionLines();
        self::assertCount(1, $lines);
        self::assertSame('Méloxicam 1,5mg/ml', $lines[0]->getLabel());
    }

    public function testRemovePrescriptionLineRejectsAnUnknownId(): void
    {
        $consultation = $this->makeOpenConsultation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Prescription line not found');

        $consultation->removePrescriptionLine('unknown-line', self::at('10:05:00'));
    }

    // ── Cockpit: billing derivation ──────────────────────────────────────

    public function testBillablePlanActionWithAPriceDerivesABillingLine(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addBillableAct($consultation, 'Otoscopie', 2.0, 4500);
        $actionId = $consultation->getPlanActions()[0]->getId();

        $lines = $consultation->getBillingLines();
        self::assertCount(1, $lines);
        self::assertSame($actionId, $lines[0]->getSourceLineId());
        self::assertSame(BillingLineSource::PLAN_ACT, $lines[0]->getSource());
        self::assertSame('Otoscopie', $lines[0]->getLabel());
        self::assertSame('ACT-OTO', $lines[0]->getCode());
        self::assertSame(2.0, $lines[0]->getQuantity());
        self::assertSame(4500, $lines[0]->getUnitPriceMinorUnits());
        self::assertSame('EUR', $lines[0]->getCurrency());
        self::assertSame('STD', $lines[0]->getTaxCategoryCode());
        self::assertSame(9000, $lines[0]->getTotalMinorUnits());
    }

    public function testBillablePlanActionWithoutAPriceDerivesNoBillingLine(): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->addPlanAction(
            PlanActionKind::PERFORMED_ACT,
            'Otoscopie offerte',
            'ACT-OTO',
            null,
            null,
            null,
            1.0,
            null,
            null,
            null,
            self::user(),
            self::at('09:50:00'),
        );

        self::assertCount(1, $consultation->getPlanActions());
        self::assertSame([], $consultation->getBillingLines());
    }

    #[DataProvider('provideNonBillablePlanActionDerivesNoBillingLineCases')]
    public function testNonBillablePlanActionDerivesNoBillingLine(PlanActionKind $kind): void
    {
        $consultation = $this->makeOpenConsultation();

        $consultation->addPlanAction(
            $kind,
            'Action non facturable',
            'CODE-1',
            null,
            null,
            null,
            1.0,
            4500,
            'EUR',
            'STD',
            self::user(),
            self::at('09:50:00'),
        );

        self::assertSame([], $consultation->getBillingLines());
    }

    /** @return iterable<string, array{PlanActionKind}> */
    public static function provideNonBillablePlanActionDerivesNoBillingLineCases(): iterable
    {
        yield 'advice' => [PlanActionKind::ADVICE];
        yield 'follow-up appointment' => [PlanActionKind::FOLLOW_UP_APPOINTMENT];
        yield 'medication prescription' => [PlanActionKind::MEDICATION_PRESCRIPTION];
        yield 'other' => [PlanActionKind::OTHER];
    }

    public function testPrescriptionLineDerivesABillingLine(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addPrescription($consultation, 'Amoxicilline 250mg', 14.0, 320);
        $lineId = $consultation->getPrescriptionLines()[0]->getId();

        $billingLines = $consultation->getBillingLines();
        self::assertCount(1, $billingLines);
        self::assertSame($lineId, $billingLines[0]->getSourceLineId());
        self::assertSame(BillingLineSource::PRESCRIPTION, $billingLines[0]->getSource());
        self::assertSame('Amoxicilline 250mg', $billingLines[0]->getLabel());
        self::assertSame('MED-AMOX', $billingLines[0]->getCode());
        self::assertSame(14.0, $billingLines[0]->getQuantity());
        self::assertSame(320, $billingLines[0]->getUnitPriceMinorUnits());
    }

    public function testUpdatingAPlanActionPropagatesToItsDerivedBillingLine(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addBillableAct($consultation, 'Otoscopie', 1.0, 4500);
        $actionId      = $consultation->getPlanActions()[0]->getId();
        $billingLineId = $consultation->getBillingLines()[0]->getId();

        $consultation->updatePlanAction(
            $actionId,
            'Otoscopie bilatérale',
            null,
            null,
            null,
            3.0,
            self::at('09:55:00'),
        );

        $lines = $consultation->getBillingLines();
        self::assertCount(1, $lines);
        self::assertSame($billingLineId, $lines[0]->getId());
        self::assertSame($actionId, $lines[0]->getSourceLineId());
        self::assertSame('Otoscopie bilatérale', $lines[0]->getLabel());
        self::assertSame(3.0, $lines[0]->getQuantity());
        self::assertSame(4500, $lines[0]->getUnitPriceMinorUnits());
    }

    public function testRemovingAPlanActionRemovesItsDerivedBillingLine(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addBillableAct($consultation, 'Otoscopie', 1.0, 4500);
        $this->addBillableAct($consultation, 'Détartrage', 1.0, 12000);
        $removedId = $consultation->getPlanActions()[0]->getId();
        self::assertCount(2, $consultation->getBillingLines());

        $consultation->removePlanAction($removedId, self::at('10:00:00'));

        $lines = $consultation->getBillingLines();
        self::assertCount(1, $lines);
        self::assertSame('Détartrage', $lines[0]->getLabel());
    }

    public function testRemovingAPrescriptionLineRemovesItsDerivedBillingLine(): void
    {
        $consultation = $this->makeOpenConsultation();
        $this->addBillableAct($consultation, 'Otoscopie', 1.0, 4500);
        $this->addPrescription($consultation, 'Amoxicilline 250mg', 14.0, 320);
        $removedId = $consultation->getPrescriptionLines()[0]->getId();
        self::assertCount(2, $consultation->getBillingLines());

        $consultation->removePrescriptionLine($removedId, self::at('10:05:00'));

        $lines = $consultation->getBillingLines();
        self::assertCount(1, $lines);
        self::assertSame(BillingLineSource::PLAN_ACT, $lines[0]->getSource());
        self::assertSame('Otoscopie', $lines[0]->getLabel());
    }

    // ── Cockpit: closed-state rejection ──────────────────────────────────

    /**
     * @param \Closure(Consultation): void $mutation
     */
    #[DataProvider('provideCockpitMutationIsRejectedOnAClosedConsultationCases')]
    public function testCockpitMutationIsRejectedOnAClosedConsultation(\Closure $mutation): void
    {
        $consultation = $this->makeClosedConsultation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        $mutation($consultation);
    }

    /** @return iterable<string, array{\Closure(Consultation): void}> */
    public static function provideCockpitMutationIsRejectedOnAClosedConsultationCases(): iterable
    {
        $at   = self::at('10:30:00');
        $user = self::user();

        yield 'setMotifs' => [
            static function (Consultation $c) use ($at): void {
                $c->setMotifs(['Toux'], $at);
            },
        ];

        yield 'recordTypedVital' => [
            static function (Consultation $c) use ($user, $at): void {
                $c->recordTypedVital(VitalType::HEART_RATE, '90', $user, $at);
            },
        ];

        yield 'removeTypedVital' => [
            static function (Consultation $c) use ($at): void {
                $c->removeTypedVital(VitalType::HEART_RATE, $at);
            },
        ];

        yield 'updateSubjectiveText' => [
            static function (Consultation $c) use ($at): void {
                $c->updateSubjectiveText('Late', $at);
            },
        ];

        yield 'updateObjectiveObservations' => [
            static function (Consultation $c) use ($at): void {
                $c->updateObjectiveObservations('Late', $at);
            },
        ];

        yield 'updateTeamMemo' => [
            static function (Consultation $c) use ($at): void {
                $c->updateTeamMemo('Late', $at);
            },
        ];

        yield 'recordExamSystem' => [
            static function (Consultation $c) use ($user, $at): void {
                $c->recordExamSystem(BodySystem::CARDIOVASCULAR, ExamStatus::NORMAL, null, [], $user, $at);
            },
        ];

        yield 'markAllSystemsNormal' => [
            static function (Consultation $c) use ($user, $at): void {
                $c->markAllSystemsNormal([BodySystem::SKIN], $user, $at);
            },
        ];

        yield 'addDiagnosis' => [
            static function (Consultation $c) use ($user, $at): void {
                $c->addDiagnosis(
                    null,
                    'Otite externe',
                    DiagnosisCertainty::CERTAIN,
                    null,
                    false,
                    DiagnosisSource::MANUAL,
                    $user,
                    $at,
                );
            },
        ];

        yield 'updateDiagnosis' => [
            static function (Consultation $c) use ($at): void {
                $c->updateDiagnosis('any-id', null, 'Otite externe', DiagnosisCertainty::CERTAIN, null, $at);
            },
        ];

        yield 'removeDiagnosis' => [
            static function (Consultation $c) use ($at): void {
                $c->removeDiagnosis('any-id', $at);
            },
        ];

        yield 'setPrimaryDiagnosis' => [
            static function (Consultation $c) use ($at): void {
                $c->setPrimaryDiagnosis('any-id', $at);
            },
        ];

        yield 'setPrimaryDiagnosis with null' => [
            static function (Consultation $c) use ($at): void {
                $c->setPrimaryDiagnosis(null, $at);
            },
        ];

        yield 'addPlanAction' => [
            static function (Consultation $c) use ($user, $at): void {
                $c->addPlanAction(
                    PlanActionKind::PERFORMED_ACT,
                    'Otoscopie',
                    'ACT-OTO',
                    null,
                    null,
                    null,
                    1.0,
                    4500,
                    'EUR',
                    'STD',
                    $user,
                    $at,
                );
            },
        ];

        yield 'updatePlanAction' => [
            static function (Consultation $c) use ($at): void {
                $c->updatePlanAction('any-id', 'Otoscopie', null, null, null, 1.0, $at);
            },
        ];

        yield 'removePlanAction' => [
            static function (Consultation $c) use ($at): void {
                $c->removePlanAction('any-id', $at);
            },
        ];

        yield 'addPrescriptionLine' => [
            static function (Consultation $c) use ($user, $at): void {
                $c->addPrescriptionLine(
                    null,
                    'MED-AMOX',
                    'Amoxicilline 250mg',
                    null,
                    null,
                    null,
                    null,
                    14.0,
                    320,
                    'EUR',
                    'STD',
                    $user,
                    $at,
                );
            },
        ];

        yield 'removePrescriptionLine' => [
            static function (Consultation $c) use ($at): void {
                $c->removePrescriptionLine('any-id', $at);
            },
        ];
    }

    public function testReconstituteRestoresTheCockpitCollections(): void
    {
        $at = self::at('09:00:00');

        $consultation = Consultation::reconstitute(
            id: ConsultationId::fromString(self::CONSULTATION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            appointmentId: null,
            admissionId: AdmissionId::fromString(self::ADMISSION_ID),
            patientId: PatientId::fromString(self::PATIENT_ID),
            practitionerUserId: UserId::fromString(self::USER_ID),
            status: ConsultationStatus::OPEN,
            chiefComplaint: null,
            vitals: null,
            summary: null,
            startedAtUtc: $at,
            closedAtUtc: null,
            createdAtUtc: $at,
            updatedAtUtc: $at,
            notes: [],
            acts: [],
            subjectiveText: 'Boite depuis 3 jours',
            objectiveObservations: 'Muqueuses roses',
            teamMemo: 'Rappeler le propriétaire',
            motifs: [MotifTag::reconstitute('motif-1', 'Boiterie')],
            typedVitals: [
                TypedVitalRecord::reconstitute('vital-1', VitalType::HEART_RATE, '95', $at, self::USER_ID),
            ],
            examSystems: [
                ExamSystemRecord::reconstitute(
                    'exam-1',
                    BodySystem::LOCOMOTOR,
                    ExamStatus::ANOMALY,
                    'Boiterie postérieur droit',
                    ['limb' => 'RH'],
                    $at,
                    self::USER_ID,
                ),
            ],
            diagnoses: [
                DiagnosisRecord::reconstitute(
                    'diag-1',
                    'M25.5',
                    'Arthrose',
                    DiagnosisCertainty::PROBABLE,
                    null,
                    true,
                    DiagnosisSource::AI_SUGGESTION,
                    $at,
                    self::USER_ID,
                ),
            ],
            planActions: [
                PlanActionRecord::reconstitute(
                    'plan-1',
                    PlanActionKind::PERFORMED_ACT,
                    'Radiographie',
                    'ACT-RX',
                    null,
                    null,
                    null,
                    1.0,
                    7500,
                    'EUR',
                    'STD',
                    $at,
                    self::USER_ID,
                ),
            ],
            prescriptionLines: [
                PrescriptionLineRecord::reconstitute(
                    'presc-1',
                    null,
                    'MED-MELOX',
                    'Méloxicam 1,5mg/ml',
                    '0,1 ml/kg',
                    '1x/j',
                    7,
                    'PO',
                    1.0,
                    1850,
                    'EUR',
                    'STD',
                    $at,
                    self::USER_ID,
                ),
            ],
            billingLines: [
                BillingLineRecord::reconstitute(
                    'bill-1',
                    'plan-1',
                    BillingLineSource::PLAN_ACT,
                    'Radiographie',
                    'ACT-RX',
                    1.0,
                    7500,
                    'EUR',
                    'STD',
                ),
            ],
        );

        self::assertSame('Boite depuis 3 jours', $consultation->getSubjectiveText());
        self::assertSame('Muqueuses roses', $consultation->getObjectiveObservations());
        self::assertSame('Rappeler le propriétaire', $consultation->getTeamMemo());
        self::assertSame('Boiterie', $consultation->getMotifs()[0]->getLabel());
        self::assertSame(VitalType::HEART_RATE, $consultation->getTypedVitals()[0]->getType());
        self::assertSame(BodySystem::LOCOMOTOR, $consultation->getExamSystems()[0]->getSystem());
        self::assertSame(['limb' => 'RH'], $consultation->getExamSystems()[0]->getStructuredData());
        self::assertSame('Arthrose', $consultation->getDiagnoses()[0]->getLabel());
        self::assertTrue($consultation->getDiagnoses()[0]->isPrimary());
        self::assertSame('Radiographie', $consultation->getPlanActions()[0]->getDescription());
        self::assertSame('Méloxicam 1,5mg/ml', $consultation->getPrescriptionLines()[0]->getLabel());
        self::assertSame('bill-1', $consultation->getBillingLines()[0]->getId());
        self::assertSame('plan-1', $consultation->getBillingLines()[0]->getSourceLineId());
        self::assertCount(0, $consultation->recordedDomainEvents());
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-04-10 ' . $time);
    }

    private static function user(): UserId
    {
        return UserId::fromString(self::USER_ID);
    }

    private static function findExamSystem(Consultation $consultation, BodySystem $system): ExamSystemRecord
    {
        foreach ($consultation->getExamSystems() as $exam) {
            if ($exam->getSystem() === $system) {
                return $exam;
            }
        }

        self::fail(\sprintf('Exam system "%s" not recorded', $system->value));
    }

    private function addDiagnosis(Consultation $consultation, string $label, bool $isPrimary): void
    {
        $consultation->addDiagnosis(
            null,
            $label,
            DiagnosisCertainty::PROBABLE,
            null,
            $isPrimary,
            DiagnosisSource::MANUAL,
            self::user(),
            self::at('09:30:00'),
        );
    }

    private function addBillableAct(
        Consultation $consultation,
        string $description,
        float $quantity,
        int $unitPriceMinorUnits,
    ): void {
        $consultation->addPlanAction(
            PlanActionKind::PERFORMED_ACT,
            $description,
            'ACT-OTO',
            null,
            null,
            null,
            $quantity,
            $unitPriceMinorUnits,
            'EUR',
            'STD',
            self::user(),
            self::at('09:50:00'),
        );
    }

    private function addPrescription(
        Consultation $consultation,
        string $label,
        float $quantity,
        int $unitPriceMinorUnits,
    ): void {
        $consultation->addPrescriptionLine(
            null,
            'MED-AMOX',
            $label,
            null,
            null,
            null,
            null,
            $quantity,
            $unitPriceMinorUnits,
            'EUR',
            'STD',
            self::user(),
            self::at('10:00:00'),
        );
    }

    private function makeClosedConsultation(): Consultation
    {
        $consultation = $this->makeOpenConsultation();
        $consultation->close(self::user(), null, self::at('10:00:00'));

        return $consultation;
    }

    private function makeOpenConsultation(): Consultation
    {
        return Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }
}
