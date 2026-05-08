<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain;

use App\Context\Admission\Domain\Event\AdmissionClosed;
use App\Context\Admission\Domain\Event\AdmissionLocationStatusUpdated;
use App\Context\Admission\Domain\Event\AdmissionOpened;
use App\Context\Admission\Domain\Event\AdmissionReopenedInError;
use App\Context\Admission\Domain\Event\AdmissionTriageDeescalated;
use App\Context\Admission\Domain\Event\AdmissionTriageEscalated;
use App\Context\Admission\Domain\Exception\AdmissionAlreadyClosedException;
use App\Context\Admission\Domain\Exception\AdmissionReopenNotAllowedException;
use App\Context\Admission\Domain\Exception\TriageDeescalationNotAllowedException;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\LocationStatus;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Context\Admission\Domain\ValueObject\Presenter;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class Admission extends AggregateRoot
{
    private function __construct(
        private readonly AdmissionId $id,
        private readonly ClinicId $clinicId,
        private readonly string $patientId,
        private readonly bool $isPatientIdentifiedAtOpening,
        private IntakeChannel $intakeChannel,
        private TriageLevel $triageLevel,
        private ?Presenter $presenter,
        private LocationStatus $locationStatus,
        private readonly ?string $appointmentId,
        private ?string $physicalDescription,
        private AdmissionStatus $status,
        private ?ClosureReason $closureReason,
        private ?string $triageNotes,
        private int $version,
        private readonly \DateTimeImmutable $openedAt,
        private ?\DateTimeImmutable $closedAt,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function open(
        AdmissionId $id,
        ClinicId $clinicId,
        string $patientId,
        bool $isPatientIdentified,
        IntakeChannel $channel,
        TriageLevel $triage,
        ?Presenter $presenter,
        \DateTimeImmutable $now,
        ?string $appointmentId = null,
        ?string $physicalDescription = null,
        ?string $triageNotes = null,
    ): self {
        $locationStatus = new LocationStatus(LocationStatusValue::InWaitingRoom, $now);

        $admission = new self(
            id: $id,
            clinicId: $clinicId,
            patientId: $patientId,
            isPatientIdentifiedAtOpening: $isPatientIdentified,
            intakeChannel: $channel,
            triageLevel: $triage,
            presenter: $presenter,
            locationStatus: $locationStatus,
            appointmentId: $appointmentId,
            physicalDescription: $physicalDescription,
            status: AdmissionStatus::Active,
            closureReason: null,
            triageNotes: $triageNotes,
            version: 1,
            openedAt: $now,
            closedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $admission->recordDomainEvent(new AdmissionOpened(
            admissionId: $id->value(),
            patientId: $patientId,
            clinicId: $clinicId->toString(),
            intakeChannel: $channel->value,
            triageLevel: $triage->value,
            isPatientIdentified: $isPatientIdentified,
            openedAt: $now->format(\DateTimeInterface::ATOM),
        ));

        return $admission;
    }

    public function escalateTriage(TriageLevel $newLevel, \DateTimeImmutable $now): void
    {
        if ($newLevel->ordinal() <= $this->triageLevel->ordinal()) {
            throw new TriageDeescalationNotAllowedException($this->triageLevel->value, $newLevel->value);
        }

        $oldLevel          = $this->triageLevel;
        $this->triageLevel = $newLevel;
        $this->updatedAt   = $now;

        $this->recordDomainEvent(new AdmissionTriageEscalated(
            admissionId: $this->id->value(),
            oldLevel: $oldLevel->value,
            newLevel: $newLevel->value,
            occurredAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function deescalateTriage(TriageLevel $newLevel, \DateTimeImmutable $now): void
    {
        // Only allowed: from VitalEmergency down to Emergency
        $isAllowed = TriageLevel::VitalEmergency === $this->triageLevel
            && TriageLevel::Emergency === $newLevel;

        if (!$isAllowed) {
            throw new TriageDeescalationNotAllowedException($this->triageLevel->value, $newLevel->value);
        }

        $oldLevel          = $this->triageLevel;
        $this->triageLevel = $newLevel;
        $this->updatedAt   = $now;

        $this->recordDomainEvent(new AdmissionTriageDeescalated(
            admissionId: $this->id->value(),
            oldLevel: $oldLevel->value,
            newLevel: $newLevel->value,
            occurredAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function updateLocationStatus(LocationStatus $newStatus, \DateTimeImmutable $now): void
    {
        $this->locationStatus = $newStatus;
        $this->updatedAt      = $now;

        $this->recordDomainEvent(new AdmissionLocationStatusUpdated(
            admissionId: $this->id->value(),
            newLocationValue: $newStatus->value()->value,
            occurredAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function updatePhysicalDescription(?string $description, \DateTimeImmutable $now): void
    {
        $this->physicalDescription = $description;
        $this->updatedAt           = $now;
    }

    public function updateTriageNotes(?string $notes, \DateTimeImmutable $now): void
    {
        $this->triageNotes = $notes;
        $this->updatedAt   = $now;
    }

    public function updatePresenter(Presenter $presenter, \DateTimeImmutable $now): void
    {
        $this->presenter = $presenter;
        $this->updatedAt = $now;
    }

    public function close(ClosureReason $reason, \DateTimeImmutable $now): void
    {
        if (AdmissionStatus::Closed === $this->status) {
            throw new AdmissionAlreadyClosedException($this->id->value());
        }

        $this->status        = AdmissionStatus::Closed;
        $this->closureReason = $reason;
        $this->closedAt      = $now;
        $this->updatedAt     = $now;

        $this->recordDomainEvent(new AdmissionClosed(
            admissionId: $this->id->value(),
            patientId: $this->patientId,
            clinicId: $this->clinicId->toString(),
            reason: $reason->value,
            closedAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function reopenInError(\DateTimeImmutable $now): void
    {
        if (ClosureReason::AdministrativeError !== $this->closureReason) {
            throw new AdmissionReopenNotAllowedException(
                $this->id->value(),
                null !== $this->closureReason ? $this->closureReason->value : 'none',
            );
        }

        $this->status        = AdmissionStatus::Active;
        $this->closureReason = null;
        $this->closedAt      = null;
        $this->updatedAt     = $now;

        $this->recordDomainEvent(new AdmissionReopenedInError(
            admissionId: $this->id->value(),
            patientId: $this->patientId,
            clinicId: $this->clinicId->toString(),
            occurredAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public static function reconstituteFromPersistence(
        AdmissionId $id,
        ClinicId $clinicId,
        string $patientId,
        bool $isPatientIdentifiedAtOpening,
        IntakeChannel $intakeChannel,
        TriageLevel $triageLevel,
        ?Presenter $presenter,
        LocationStatus $locationStatus,
        ?string $appointmentId,
        ?string $physicalDescription,
        AdmissionStatus $status,
        ?ClosureReason $closureReason,
        ?string $triageNotes,
        int $version,
        \DateTimeImmutable $openedAt,
        ?\DateTimeImmutable $closedAt,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            patientId: $patientId,
            isPatientIdentifiedAtOpening: $isPatientIdentifiedAtOpening,
            intakeChannel: $intakeChannel,
            triageLevel: $triageLevel,
            presenter: $presenter,
            locationStatus: $locationStatus,
            appointmentId: $appointmentId,
            physicalDescription: $physicalDescription,
            status: $status,
            closureReason: $closureReason,
            triageNotes: $triageNotes,
            version: $version,
            openedAt: $openedAt,
            closedAt: $closedAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function id(): AdmissionId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function patientId(): string
    {
        return $this->patientId;
    }

    public function isPatientIdentifiedAtOpening(): bool
    {
        return $this->isPatientIdentifiedAtOpening;
    }

    public function intakeChannel(): IntakeChannel
    {
        return $this->intakeChannel;
    }

    public function triageLevel(): TriageLevel
    {
        return $this->triageLevel;
    }

    public function presenter(): ?Presenter
    {
        return $this->presenter;
    }

    public function locationStatus(): LocationStatus
    {
        return $this->locationStatus;
    }

    public function appointmentId(): ?string
    {
        return $this->appointmentId;
    }

    public function physicalDescription(): ?string
    {
        return $this->physicalDescription;
    }

    public function status(): AdmissionStatus
    {
        return $this->status;
    }

    public function closureReason(): ?ClosureReason
    {
        return $this->closureReason;
    }

    public function triageNotes(): ?string
    {
        return $this->triageNotes;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function openedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
