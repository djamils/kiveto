<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain;

use App\ClinicalCare\Domain\Event\ConsultationChiefComplaintRecorded;
use App\ClinicalCare\Domain\Event\ConsultationClinicalNoteAdded;
use App\ClinicalCare\Domain\Event\ConsultationClosed;
use App\ClinicalCare\Domain\Event\ConsultationPatientIdentityAttached;
use App\ClinicalCare\Domain\Event\ConsultationPerformedActAdded;
use App\ClinicalCare\Domain\Event\ConsultationStartedFromAppointment;
use App\ClinicalCare\Domain\Event\ConsultationStartedFromWaitingRoomEntry;
use App\ClinicalCare\Domain\Event\ConsultationVitalsRecorded;
use App\ClinicalCare\Domain\ValueObject\AnimalId;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\ClinicalCare\Domain\ValueObject\ClinicId;
use App\ClinicalCare\Domain\ValueObject\ClinicalNoteRecord;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\ConsultationStatus;
use App\ClinicalCare\Domain\ValueObject\NoteType;
use App\ClinicalCare\Domain\ValueObject\OwnerId;
use App\ClinicalCare\Domain\ValueObject\PerformedActRecord;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\ClinicalCare\Domain\ValueObject\Vitals;
use App\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
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
        private readonly ?WaitingRoomEntryId $waitingRoomEntryId,
        private UserId $practitionerUserId,
        private ?OwnerId $ownerId,
        private ?AnimalId $animalId,
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
        UserId $practitionerUserId,
        ?OwnerId $ownerId,
        ?AnimalId $animalId,
        \DateTimeImmutable $startedAtUtc,
    ): self {
        $consultation = new self(
            id: $id,
            clinicId: $clinicId,
            appointmentId: $appointmentId,
            waitingRoomEntryId: null,
            practitionerUserId: $practitionerUserId,
            ownerId: $ownerId,
            animalId: $animalId,
            status: ConsultationStatus::OPEN,
            chiefComplaint: null,
            vitals: null,
            summary: null,
            startedAtUtc: $startedAtUtc,
            closedAtUtc: null,
            createdAtUtc: $startedAtUtc,
            updatedAtUtc: $startedAtUtc,
        );

        $consultation->raise(new ConsultationStartedFromAppointment(
            $id,
            $clinicId,
            $appointmentId,
            $practitionerUserId,
            $startedAtUtc,
        ));

        return $consultation;
    }

    public static function startFromWaitingRoomEntry(
        ConsultationId $id,
        ClinicId $clinicId,
        WaitingRoomEntryId $waitingRoomEntryId,
        UserId $practitionerUserId,
        ?OwnerId $ownerId,
        ?AnimalId $animalId,
        \DateTimeImmutable $startedAtUtc,
    ): self {
        $consultation = new self(
            id: $id,
            clinicId: $clinicId,
            appointmentId: null,
            waitingRoomEntryId: $waitingRoomEntryId,
            practitionerUserId: $practitionerUserId,
            ownerId: $ownerId,
            animalId: $animalId,
            status: ConsultationStatus::OPEN,
            chiefComplaint: null,
            vitals: null,
            summary: null,
            startedAtUtc: $startedAtUtc,
            closedAtUtc: null,
            createdAtUtc: $startedAtUtc,
            updatedAtUtc: $startedAtUtc,
        );

        $consultation->raise(new ConsultationStartedFromWaitingRoomEntry(
            $id,
            $clinicId,
            $waitingRoomEntryId,
            $practitionerUserId,
            $startedAtUtc,
        ));

        return $consultation;
    }

    public function attachPatientIdentity(
        ?OwnerId $ownerId,
        ?AnimalId $animalId,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        if (null === $ownerId && null === $animalId) {
            throw new \DomainException('At least one of owner or animal must be provided');
        }

        $this->ownerId = $ownerId;
        $this->animalId = $animalId;
        $this->updatedAtUtc = $occurredAt;

        $this->raise(new ConsultationPatientIdentityAttached(
            $this->id,
            $ownerId,
            $animalId,
            $occurredAt,
        ));
    }

    public function recordChiefComplaint(
        string $chiefComplaint,
        \DateTimeImmutable $occurredAt,
    ): void {
        $this->ensureOpen();

        if (trim($chiefComplaint) === '') {
            throw new \InvalidArgumentException('Chief complaint cannot be empty');
        }

        $this->chiefComplaint = $chiefComplaint;
        $this->updatedAtUtc = $occurredAt;

        $this->raise(new ConsultationChiefComplaintRecorded(
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

        $this->vitals = $vitals;
        $this->updatedAtUtc = $occurredAt;

        $this->raise(new ConsultationVitalsRecorded(
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

        $note = ClinicalNoteRecord::create($noteType, $content, $createdAt, $createdByUserId);
        $this->notes[] = $note;
        $this->updatedAtUtc = $createdAt;

        $this->raise(new ConsultationClinicalNoteAdded(
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

        $act = PerformedActRecord::create($label, $quantity, $performedAt, $createdAt, $createdByUserId);
        $this->acts[] = $act;
        $this->updatedAtUtc = $createdAt;

        $this->raise(new ConsultationPerformedActAdded(
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

        $this->status = ConsultationStatus::CLOSED;
        $this->summary = $summary;
        $this->closedAtUtc = $closedAt;
        $this->updatedAtUtc = $closedAt;

        $this->raise(new ConsultationClosed(
            $this->id,
            $closedByUserId,
            $summary,
            $closedAt,
        ));
    }

    private function ensureOpen(): void
    {
        if (!$this->status->isOpen()) {
            throw new \DomainException('Cannot modify a closed consultation');
        }
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

    public function getWaitingRoomEntryId(): ?WaitingRoomEntryId
    {
        return $this->waitingRoomEntryId;
    }

    public function getPractitionerUserId(): UserId
    {
        return $this->practitionerUserId;
    }

    public function getOwnerId(): ?OwnerId
    {
        return $this->ownerId;
    }

    public function getAnimalId(): ?AnimalId
    {
        return $this->animalId;
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

    // For reconstitution from persistence
    public static function reconstitute(
        ConsultationId $id,
        ClinicId $clinicId,
        ?AppointmentId $appointmentId,
        ?WaitingRoomEntryId $waitingRoomEntryId,
        UserId $practitionerUserId,
        ?OwnerId $ownerId,
        ?AnimalId $animalId,
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
            $waitingRoomEntryId,
            $practitionerUserId,
            $ownerId,
            $animalId,
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
        $consultation->acts = $acts;

        return $consultation;
    }
}
