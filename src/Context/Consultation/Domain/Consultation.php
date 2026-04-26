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
use App\Context\Consultation\Domain\ValueObject\ClinicalNoteRecord;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Context\Consultation\Domain\ValueObject\NoteType;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PerformedActRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class Consultation extends AggregateRoot
{
    /** @var ClinicalNoteRecord[] */
    private array $notes = [];

    /** @var PerformedActRecord[] */
    private array $acts = [];

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

    /**
     * Rebuild a Consultation aggregate from persisted state.
     *
     * @param array<ClinicalNoteRecord> $notes
     * @param array<PerformedActRecord> $acts
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

        $consultation->notes = $notes;
        $consultation->acts  = $acts;

        return $consultation;
    }

    private function ensureOpen(): void
    {
        if (!$this->status->isOpen()) {
            throw new \DomainException('Cannot modify a closed consultation');
        }
    }
}
