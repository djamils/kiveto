<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain;

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
use App\Context\Consultation\Domain\ValueObject\ClinicalNoteRecord;
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
use App\Context\Consultation\Domain\ValueObject\PerformedActRecord;
use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\PlanActionRecord;
use App\Context\Consultation\Domain\ValueObject\PrescriptionLineRecord;
use App\Context\Consultation\Domain\ValueObject\TypedVitalRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use App\Context\Consultation\Domain\ValueObject\VitalType;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class Consultation extends AggregateRoot
{
    private const int MAX_MOTIFS = 20;

    /** @var ClinicalNoteRecord[] */
    private array $notes = [];

    /** @var PerformedActRecord[] */
    private array $acts = [];

    /** @var list<MotifTag> */
    private array $motifs = [];

    /** @var list<TypedVitalRecord> */
    private array $typedVitals = [];

    /** @var list<ExamSystemRecord> */
    private array $examSystems = [];

    /** @var list<DiagnosisRecord> */
    private array $diagnoses = [];

    /** @var list<PlanActionRecord> */
    private array $planActions = [];

    /** @var list<PrescriptionLineRecord> */
    private array $prescriptionLines = [];

    /** @var list<BillingLineRecord> */
    private array $billingLines = [];

    private ?string $subjectiveText = null;

    private ?string $objectiveObservations = null;

    private ?string $teamMemo = null;

    private function __construct(
        private readonly ConsultationId $id,
        private readonly ClinicId $clinicId,
        private readonly ?AppointmentId $appointmentId,
        private readonly AdmissionId $admissionId,
        private readonly PatientId $patientId,
        private UserId $practitionerUserId,
        private ConsultationStatus $status,
        private ?string $chiefComplaint,
        private ?Vitals $vitals,
        private ?string $summary,
        private readonly \DateTimeImmutable $startedAtUtc,
        private ?\DateTimeImmutable $closedAtUtc,
        private readonly \DateTimeImmutable $createdAtUtc,
        private \DateTimeImmutable $updatedAtUtc,
    ) {
    }

    public static function startFromAppointment(
        ConsultationId $id,
        ClinicId $clinicId,
        AppointmentId $appointmentId,
        AdmissionId $admissionId,
        PatientId $patientId,
        UserId $practitionerUserId,
        \DateTimeImmutable $startedAtUtc,
    ): self {
        $consultation = new self(
            id: $id,
            clinicId: $clinicId,
            appointmentId: $appointmentId,
            admissionId: $admissionId,
            patientId: $patientId,
            practitionerUserId: $practitionerUserId,
            status: ConsultationStatus::OPEN,
            chiefComplaint: null,
            vitals: null,
            summary: null,
            startedAtUtc: $startedAtUtc,
            closedAtUtc: null,
            createdAtUtc: $startedAtUtc,
            updatedAtUtc: $startedAtUtc,
        );

        $consultation->recordDomainEvent(new ConsultationStartedFromAppointment(
            $id,
            $clinicId,
            $appointmentId,
            $admissionId,
            $patientId,
            $practitionerUserId,
            $startedAtUtc,
        ));

        return $consultation;
    }

    public static function startFromAdmission(
        ConsultationId $id,
        ClinicId $clinicId,
        AdmissionId $admissionId,
        PatientId $patientId,
        UserId $practitionerUserId,
        \DateTimeImmutable $startedAtUtc,
    ): self {
        $consultation = new self(
            id: $id,
            clinicId: $clinicId,
            appointmentId: null,
            admissionId: $admissionId,
            patientId: $patientId,
            practitionerUserId: $practitionerUserId,
            status: ConsultationStatus::OPEN,
            chiefComplaint: null,
            vitals: null,
            summary: null,
            startedAtUtc: $startedAtUtc,
            closedAtUtc: null,
            createdAtUtc: $startedAtUtc,
            updatedAtUtc: $startedAtUtc,
        );

        $consultation->recordDomainEvent(new ConsultationStartedFromAdmission(
            $id,
            $clinicId,
            $admissionId,
            $patientId,
            $practitionerUserId,
            $startedAtUtc,
        ));

        return $consultation;
    }

    public function recordChiefComplaint(
        string $chiefComplaint,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        if ('' === trim($chiefComplaint)) {
            throw new \InvalidArgumentException('Chief complaint cannot be empty');
        }

        $this->chiefComplaint = $chiefComplaint;
        $this->updatedAtUtc   = $occurredAt;

        $this->recordDomainEvent(new ConsultationChiefComplaintRecorded(
            $this->id,
            $chiefComplaint,
            $occurredAt,
        ));
    }

    public function recordVitals(
        Vitals $vitals,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        $this->vitals       = $vitals;
        $this->updatedAtUtc = $occurredAt;

        $this->recordDomainEvent(new ConsultationVitalsRecorded(
            $this->id,
            $vitals,
            $occurredAt,
        ));
    }

    public function addClinicalNote(
        NoteType $noteType,
        string $content,
        UserId $createdByUserId,
        \DateTimeImmutable $createdAt,
    ): void {
        $this->ensureOpen();

        $note               = ClinicalNoteRecord::create($noteType, $content, $createdAt, $createdByUserId);
        $this->notes[]      = $note;
        $this->updatedAtUtc = $createdAt;

        $this->recordDomainEvent(new ConsultationClinicalNoteAdded(
            $this->id,
            $note,
            $createdAt,
        ));
    }

    public function addPerformedAct(
        string $label,
        float $quantity,
        \DateTimeImmutable $performedAt,
        UserId $createdByUserId,
        \DateTimeImmutable $createdAt,
    ): void {
        $this->ensureOpen();

        $act                = PerformedActRecord::create($label, $quantity, $performedAt, $createdAt, $createdByUserId);
        $this->acts[]       = $act;
        $this->updatedAtUtc = $createdAt;

        $this->recordDomainEvent(new ConsultationPerformedActAdded(
            $this->id,
            $act,
            $createdAt,
        ));
    }

    // ── Cockpit: motifs ──────────────────────────────────────────────────

    /**
     * Replaces the whole motif strip. Labels are de-duplicated, order kept.
     *
     * @param list<string> $labels
     */
    public function setMotifs(array $labels, \DateTimeImmutable $occurredAt): void
    {
        $this->ensureOpen();

        if (\count($labels) > self::MAX_MOTIFS) {
            throw new \InvalidArgumentException(
                \sprintf('A consultation cannot carry more than %d motifs', self::MAX_MOTIFS),
            );
        }

        $motifs = [];
        $seen   = [];

        foreach ($labels as $label) {
            $motif = MotifTag::create($label);
            $key   = mb_strtolower($motif->getLabel());

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $motifs[]   = $motif;
        }

        $this->motifs       = $motifs;
        $this->updatedAtUtc = $occurredAt;
    }

    // ── Cockpit: typed vitals ────────────────────────────────────────────

    /**
     * Records an additional vital, replacing any previous value of the same type.
     */
    public function recordTypedVital(
        VitalType $type,
        string $value,
        UserId $recordedByUserId,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        $record = TypedVitalRecord::create($type, $value, $occurredAt, $recordedByUserId);

        $kept = array_values(array_filter(
            $this->typedVitals,
            static fn (TypedVitalRecord $vital): bool => $vital->getType() !== $type,
        ));

        $kept[]             = $record;
        $this->typedVitals  = $kept;
        $this->updatedAtUtc = $occurredAt;
    }

    public function removeTypedVital(VitalType $type, \DateTimeImmutable $occurredAt): void
    {
        $this->ensureOpen();

        $kept = array_values(array_filter(
            $this->typedVitals,
            static fn (TypedVitalRecord $vital): bool => $vital->getType() !== $type,
        ));

        if (\count($kept) === \count($this->typedVitals)) {
            throw new \DomainException('Typed vital not found');
        }

        $this->typedVitals  = $kept;
        $this->updatedAtUtc = $occurredAt;
    }

    // ── Cockpit: SOAP S ──────────────────────────────────────────────────

    public function updateSubjectiveText(?string $text, \DateTimeImmutable $occurredAt): void
    {
        $this->ensureOpen();

        $this->subjectiveText = self::normalizeFreeText($text, 'Subjective text');
        $this->updatedAtUtc   = $occurredAt;
    }

    // ── Cockpit: SOAP O ──────────────────────────────────────────────────

    /**
     * Records the exam result of one body system, replacing any previous entry.
     *
     * @param array<string, string> $structuredData
     */
    public function recordExamSystem(
        BodySystem $system,
        ExamStatus $status,
        ?string $notes,
        array $structuredData,
        UserId $recordedByUserId,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        $record = ExamSystemRecord::create($system, $status, $notes, $structuredData, $occurredAt, $recordedByUserId);

        $kept = array_values(array_filter(
            $this->examSystems,
            static fn (ExamSystemRecord $exam): bool => $exam->getSystem() !== $system,
        ));

        $kept[]             = $record;
        $this->examSystems  = $kept;
        $this->updatedAtUtc = $occurredAt;
    }

    /**
     * "Tout RAS" shortcut: marks every listed system as normal except those
     * already flagged as an anomaly, whose findings must not be wiped.
     *
     * @param list<BodySystem> $systems
     */
    public function markAllSystemsNormal(
        array $systems,
        UserId $recordedByUserId,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        foreach ($systems as $system) {
            $existing = $this->findExamSystem($system);

            if (null !== $existing && ExamStatus::ANOMALY === $existing->getStatus()) {
                continue;
            }

            $record = null !== $existing
                ? $existing->withStatus(ExamStatus::NORMAL, $occurredAt)
                : ExamSystemRecord::create($system, ExamStatus::NORMAL, null, [], $occurredAt, $recordedByUserId);

            $kept = array_values(array_filter(
                $this->examSystems,
                static fn (ExamSystemRecord $exam): bool => $exam->getSystem() !== $system,
            ));

            $kept[]            = $record;
            $this->examSystems = $kept;
        }

        $this->updatedAtUtc = $occurredAt;
    }

    public function updateObjectiveObservations(?string $text, \DateTimeImmutable $occurredAt): void
    {
        $this->ensureOpen();

        $this->objectiveObservations = self::normalizeFreeText($text, 'Objective observations');
        $this->updatedAtUtc          = $occurredAt;
    }

    // ── Cockpit: SOAP A ──────────────────────────────────────────────────

    public function addDiagnosis(
        ?string $code,
        string $label,
        DiagnosisCertainty $certainty,
        ?string $note,
        bool $isPrimary,
        DiagnosisSource $source,
        UserId $createdByUserId,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        $diagnosis = DiagnosisRecord::create(
            $code,
            $label,
            $certainty,
            $note,
            $isPrimary,
            $source,
            $occurredAt,
            $createdByUserId,
        );

        if ($isPrimary) {
            $this->clearPrimaryDiagnosis();
        }

        $this->diagnoses[]  = $diagnosis;
        $this->updatedAtUtc = $occurredAt;
    }

    public function updateDiagnosis(
        string $diagnosisId,
        ?string $code,
        string $label,
        DiagnosisCertainty $certainty,
        ?string $note,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        $index = $this->indexOfDiagnosis($diagnosisId);

        $diagnoses         = $this->diagnoses;
        $diagnoses[$index] = $diagnoses[$index]->withDetails($code, $label, $certainty, $note);

        $this->diagnoses    = array_values($diagnoses);
        $this->updatedAtUtc = $occurredAt;
    }

    public function removeDiagnosis(string $diagnosisId, \DateTimeImmutable $occurredAt): void
    {
        $this->ensureOpen();

        $index = $this->indexOfDiagnosis($diagnosisId);

        $diagnoses = $this->diagnoses;
        unset($diagnoses[$index]);

        $this->diagnoses    = array_values($diagnoses);
        $this->updatedAtUtc = $occurredAt;
    }

    /**
     * Promotes one diagnosis as the primary one, or clears the flag entirely
     * when $diagnosisId is null. At most one diagnosis is ever primary.
     */
    public function setPrimaryDiagnosis(?string $diagnosisId, \DateTimeImmutable $occurredAt): void
    {
        $this->ensureOpen();

        if (null === $diagnosisId) {
            $this->clearPrimaryDiagnosis();
            $this->updatedAtUtc = $occurredAt;

            return;
        }

        $index = $this->indexOfDiagnosis($diagnosisId);

        $this->clearPrimaryDiagnosis();

        $diagnoses         = $this->diagnoses;
        $diagnoses[$index] = $diagnoses[$index]->withPrimary(true);

        $this->diagnoses    = array_values($diagnoses);
        $this->updatedAtUtc = $occurredAt;
    }

    // ── Cockpit: SOAP P ──────────────────────────────────────────────────

    public function addPlanAction(
        PlanActionKind $kind,
        string $description,
        ?string $catalogCode,
        ?string $posology,
        ?int $durationDays,
        ?int $followUpDays,
        float $quantity,
        ?int $unitPriceMinorUnits,
        ?string $currency,
        ?string $taxCategoryCode,
        UserId $createdByUserId,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        $this->planActions[] = PlanActionRecord::create(
            $kind,
            $description,
            $catalogCode,
            $posology,
            $durationDays,
            $followUpDays,
            $quantity,
            $unitPriceMinorUnits,
            $currency,
            $taxCategoryCode,
            $occurredAt,
            $createdByUserId,
        );

        $this->updatedAtUtc = $occurredAt;
        $this->syncBillingLines();
    }

    public function updatePlanAction(
        string $planActionId,
        string $description,
        ?string $posology,
        ?int $durationDays,
        ?int $followUpDays,
        float $quantity,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        $index = $this->indexOfPlanAction($planActionId);

        $planActions         = $this->planActions;
        $planActions[$index] = $planActions[$index]->withDetails(
            $description,
            $posology,
            $durationDays,
            $followUpDays,
            $quantity,
        );

        $this->planActions  = array_values($planActions);
        $this->updatedAtUtc = $occurredAt;
        $this->syncBillingLines();
    }

    public function removePlanAction(string $planActionId, \DateTimeImmutable $occurredAt): void
    {
        $this->ensureOpen();

        $index = $this->indexOfPlanAction($planActionId);

        $planActions = $this->planActions;
        unset($planActions[$index]);

        $this->planActions  = array_values($planActions);
        $this->updatedAtUtc = $occurredAt;
        $this->syncBillingLines();
    }

    // ── Cockpit: prescription ────────────────────────────────────────────

    public function addPrescriptionLine(
        ?string $articleId,
        ?string $code,
        string $label,
        ?string $dose,
        ?string $frequency,
        ?int $durationDays,
        ?string $route,
        float $quantity,
        int $unitPriceMinorUnits,
        string $currency,
        ?string $taxCategoryCode,
        UserId $createdByUserId,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        $this->prescriptionLines[] = PrescriptionLineRecord::create(
            $articleId,
            $code,
            $label,
            $dose,
            $frequency,
            $durationDays,
            $route,
            $quantity,
            $unitPriceMinorUnits,
            $currency,
            $taxCategoryCode,
            $occurredAt,
            $createdByUserId,
        );

        $this->updatedAtUtc = $occurredAt;
        $this->syncBillingLines();
    }

    public function removePrescriptionLine(string $prescriptionLineId, \DateTimeImmutable $occurredAt): void
    {
        $this->ensureOpen();

        $index = null;

        foreach ($this->prescriptionLines as $position => $line) {
            if ($line->getId() === $prescriptionLineId) {
                $index = $position;

                break;
            }
        }

        if (null === $index) {
            throw new \DomainException('Prescription line not found');
        }

        $prescriptionLines = $this->prescriptionLines;
        unset($prescriptionLines[$index]);

        $this->prescriptionLines = array_values($prescriptionLines);
        $this->updatedAtUtc      = $occurredAt;
        $this->syncBillingLines();
    }

    // ── Cockpit: team memo ───────────────────────────────────────────────

    public function updateTeamMemo(?string $memo, \DateTimeImmutable $occurredAt): void
    {
        $this->ensureOpen();

        $this->teamMemo     = self::normalizeFreeText($memo, 'Team memo');
        $this->updatedAtUtc = $occurredAt;
    }

    public function close(
        UserId $closedByUserId,
        ?string $summary,
        \DateTimeImmutable $closedAt,
    ): void {
        $this->ensureOpen();

        $this->status       = ConsultationStatus::CLOSED;
        $this->summary      = $summary;
        $this->closedAtUtc  = $closedAt;
        $this->updatedAtUtc = $closedAt;

        $this->recordDomainEvent(new ConsultationClosed(
            $this->id,
            $closedByUserId,
            $summary,
            $closedAt,
        ));
    }

    // Getters for reconstitution & read
    public function getId(): ConsultationId
    {
        return $this->id;
    }

    public function getClinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function getAppointmentId(): ?AppointmentId
    {
        return $this->appointmentId;
    }

    public function getAdmissionId(): AdmissionId
    {
        return $this->admissionId;
    }

    public function getPatientId(): PatientId
    {
        return $this->patientId;
    }

    public function getPractitionerUserId(): UserId
    {
        return $this->practitionerUserId;
    }

    public function getStatus(): ConsultationStatus
    {
        return $this->status;
    }

    public function getChiefComplaint(): ?string
    {
        return $this->chiefComplaint;
    }

    public function getVitals(): ?Vitals
    {
        return $this->vitals;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getStartedAtUtc(): \DateTimeImmutable
    {
        return $this->startedAtUtc;
    }

    public function getClosedAtUtc(): ?\DateTimeImmutable
    {
        return $this->closedAtUtc;
    }

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function getUpdatedAtUtc(): \DateTimeImmutable
    {
        return $this->updatedAtUtc;
    }

    /** @return ClinicalNoteRecord[] */
    public function getNotes(): array
    {
        return $this->notes;
    }

    /** @return PerformedActRecord[] */
    public function getActs(): array
    {
        return $this->acts;
    }

    /** @return list<MotifTag> */
    public function getMotifs(): array
    {
        return $this->motifs;
    }

    /** @return list<TypedVitalRecord> */
    public function getTypedVitals(): array
    {
        return $this->typedVitals;
    }

    /** @return list<ExamSystemRecord> */
    public function getExamSystems(): array
    {
        return $this->examSystems;
    }

    /** @return list<DiagnosisRecord> */
    public function getDiagnoses(): array
    {
        return $this->diagnoses;
    }

    /** @return list<PlanActionRecord> */
    public function getPlanActions(): array
    {
        return $this->planActions;
    }

    /** @return list<PrescriptionLineRecord> */
    public function getPrescriptionLines(): array
    {
        return $this->prescriptionLines;
    }

    /** @return list<BillingLineRecord> */
    public function getBillingLines(): array
    {
        return $this->billingLines;
    }

    public function getSubjectiveText(): ?string
    {
        return $this->subjectiveText;
    }

    public function getObjectiveObservations(): ?string
    {
        return $this->objectiveObservations;
    }

    public function getTeamMemo(): ?string
    {
        return $this->teamMemo;
    }

    /**
     * Rebuild a Consultation aggregate from persisted state.
     *
     * @param array<ClinicalNoteRecord>    $notes
     * @param array<PerformedActRecord>    $acts
     * @param list<MotifTag>               $motifs
     * @param list<TypedVitalRecord>       $typedVitals
     * @param list<ExamSystemRecord>       $examSystems
     * @param list<DiagnosisRecord>        $diagnoses
     * @param list<PlanActionRecord>       $planActions
     * @param list<PrescriptionLineRecord> $prescriptionLines
     * @param list<BillingLineRecord>      $billingLines
     */
    public static function reconstitute(
        ConsultationId $id,
        ClinicId $clinicId,
        ?AppointmentId $appointmentId,
        AdmissionId $admissionId,
        PatientId $patientId,
        UserId $practitionerUserId,
        ConsultationStatus $status,
        ?string $chiefComplaint,
        ?Vitals $vitals,
        ?string $summary,
        \DateTimeImmutable $startedAtUtc,
        ?\DateTimeImmutable $closedAtUtc,
        \DateTimeImmutable $createdAtUtc,
        \DateTimeImmutable $updatedAtUtc,
        array $notes,
        array $acts,
        ?string $subjectiveText = null,
        ?string $objectiveObservations = null,
        ?string $teamMemo = null,
        array $motifs = [],
        array $typedVitals = [],
        array $examSystems = [],
        array $diagnoses = [],
        array $planActions = [],
        array $prescriptionLines = [],
        array $billingLines = [],
    ): self {
        $consultation = new self(
            $id,
            $clinicId,
            $appointmentId,
            $admissionId,
            $patientId,
            $practitionerUserId,
            $status,
            $chiefComplaint,
            $vitals,
            $summary,
            $startedAtUtc,
            $closedAtUtc,
            $createdAtUtc,
            $updatedAtUtc,
        );

        $consultation->notes                 = $notes;
        $consultation->acts                  = $acts;
        $consultation->subjectiveText        = $subjectiveText;
        $consultation->objectiveObservations = $objectiveObservations;
        $consultation->teamMemo              = $teamMemo;
        $consultation->motifs                = $motifs;
        $consultation->typedVitals           = $typedVitals;
        $consultation->examSystems           = $examSystems;
        $consultation->diagnoses             = $diagnoses;
        $consultation->planActions           = $planActions;
        $consultation->prescriptionLines     = $prescriptionLines;
        $consultation->billingLines          = $billingLines;

        return $consultation;
    }

    /**
     * Re-derives the billing draft from the billable plan actions and the
     * prescription lines.
     *
     * Existing lines are matched by `sourceLineId`: a match keeps its own id and
     * its snapshotted unit price (label and quantity follow the source), a new
     * source creates a new line, and an orphaned line is dropped.
     */
    private function syncBillingLines(): void
    {
        $existingBySource = [];

        foreach ($this->billingLines as $line) {
            $existingBySource[$line->getSourceLineId()] = $line;
        }

        $synced = [];

        foreach ($this->planActions as $action) {
            if (!$action->getKind()->isBillable()) {
                continue;
            }

            $unitPrice = $action->getUnitPriceMinorUnits();
            $currency  = $action->getCurrency();

            if (null === $unitPrice || null === $currency) {
                continue;
            }

            $existing = $existingBySource[$action->getId()] ?? null;

            $synced[] = null !== $existing
                ? $existing->withSourceDetails(
                    $action->getDescription(),
                    $action->getCatalogCode(),
                    $action->getQuantity(),
                )
                : BillingLineRecord::create(
                    $action->getId(),
                    BillingLineSource::PLAN_ACT,
                    $action->getDescription(),
                    $action->getCatalogCode(),
                    $action->getQuantity(),
                    $unitPrice,
                    $currency,
                    $action->getTaxCategoryCode(),
                );
        }

        foreach ($this->prescriptionLines as $line) {
            $existing = $existingBySource[$line->getId()] ?? null;

            $synced[] = null !== $existing
                ? $existing->withSourceDetails($line->getLabel(), $line->getCode(), $line->getQuantity())
                : BillingLineRecord::create(
                    $line->getId(),
                    BillingLineSource::PRESCRIPTION,
                    $line->getLabel(),
                    $line->getCode(),
                    $line->getQuantity(),
                    $line->getUnitPriceMinorUnits(),
                    $line->getCurrency(),
                    $line->getTaxCategoryCode(),
                );
        }

        $this->billingLines = $synced;
    }

    private function findExamSystem(BodySystem $system): ?ExamSystemRecord
    {
        foreach ($this->examSystems as $exam) {
            if ($exam->getSystem() === $system) {
                return $exam;
            }
        }

        return null;
    }

    private function indexOfDiagnosis(string $diagnosisId): int
    {
        foreach ($this->diagnoses as $index => $diagnosis) {
            if ($diagnosis->getId() === $diagnosisId) {
                return $index;
            }
        }

        throw new \DomainException('Diagnosis not found');
    }

    private function indexOfPlanAction(string $planActionId): int
    {
        foreach ($this->planActions as $index => $action) {
            if ($action->getId() === $planActionId) {
                return $index;
            }
        }

        throw new \DomainException('Plan action not found');
    }

    private function clearPrimaryDiagnosis(): void
    {
        $diagnoses = [];

        foreach ($this->diagnoses as $diagnosis) {
            $diagnoses[] = $diagnosis->isPrimary() ? $diagnosis->withPrimary(false) : $diagnosis;
        }

        $this->diagnoses = $diagnoses;
    }

    private static function normalizeFreeText(?string $text, string $subject): ?string
    {
        if (null === $text) {
            return null;
        }

        $text = trim($text);

        if ('' === $text) {
            return null;
        }

        if (mb_strlen($text) > 20000) {
            throw new \InvalidArgumentException(\sprintf('%s cannot exceed 20000 characters', $subject));
        }

        return $text;
    }

    private function ensureOpen(): void
    {
        if (!$this->status->isOpen()) {
            throw new \DomainException('Cannot modify a closed consultation');
        }
    }
}
