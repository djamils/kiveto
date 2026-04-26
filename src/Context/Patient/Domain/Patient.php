<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain;

use App\Context\Patient\Domain\Event\PatientArchived;
use App\Context\Patient\Domain\Event\PatientCreated;
use App\Context\Patient\Domain\Event\PatientDisplayLabelChanged;
use App\Context\Patient\Domain\Event\PatientDisplayLabelCustomized;
use App\Context\Patient\Domain\Event\PatientLinkedToAnimal;
use App\Context\Patient\Domain\Event\PatientReconciledInto;
use App\Context\Patient\Domain\Exception\PatientAlreadyLinkedException;
use App\Context\Patient\Domain\Exception\PatientNotActiveException;
use App\Context\Patient\Domain\ValueObject\AnimalLink;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\DisplayLabel;
use App\Context\Patient\Domain\ValueObject\DisplayLabelKind;
use App\Context\Patient\Domain\ValueObject\PatientId;
use App\Context\Patient\Domain\ValueObject\PatientStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class Patient extends AggregateRoot
{
    private function __construct(
        private readonly PatientId $id,
        private readonly ClinicId $clinicId,
        private PatientStatus $status,
        private DisplayLabel $displayLabel,
        private ?AnimalLink $animalLink,
        private ?string $observedSpecies,
        private ?string $observedColor,
        private int $version,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function createUnidentified(
        PatientId $id,
        ClinicId $clinicId,
        string $provisionalLabel,
        ?string $observedSpecies,
        ?string $observedColor,
        \DateTimeImmutable $now,
    ): self {
        $patient = new self(
            id: $id,
            clinicId: $clinicId,
            status: PatientStatus::Active,
            displayLabel: DisplayLabel::provisional($provisionalLabel),
            animalLink: null,
            observedSpecies: $observedSpecies,
            observedColor: $observedColor,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $patient->recordDomainEvent(new PatientCreated(
            patientId: $id->toString(),
            clinicId: $clinicId->toString(),
            displayLabelKind: DisplayLabelKind::Provisional->value,
            displayLabelValue: $provisionalLabel,
            animalId: null,
        ));

        return $patient;
    }

    public static function createFromAnimal(
        PatientId $id,
        ClinicId $clinicId,
        AnimalLink $animalLink,
        string $animalName,
        \DateTimeImmutable $now,
    ): self {
        $patient = new self(
            id: $id,
            clinicId: $clinicId,
            status: PatientStatus::Active,
            displayLabel: DisplayLabel::fromAnimal($animalName),
            animalLink: $animalLink,
            observedSpecies: null,
            observedColor: null,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $patient->recordDomainEvent(new PatientCreated(
            patientId: $id->toString(),
            clinicId: $clinicId->toString(),
            displayLabelKind: DisplayLabelKind::FromAnimal->value,
            displayLabelValue: $animalName,
            animalId: $animalLink->animalId(),
        ));

        return $patient;
    }

    public function linkToAnimal(AnimalLink $animalLink, \DateTimeImmutable $now): void
    {
        $this->ensureActive();

        if (null !== $this->animalLink) {
            throw new PatientAlreadyLinkedException($this->id->toString());
        }

        $this->animalLink = $animalLink;
        $this->updatedAt  = $now;

        $this->recordDomainEvent(new PatientLinkedToAnimal(
            patientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            animalId: $animalLink->animalId(),
        ));
    }

    public function updateDisplayLabel(DisplayLabel $newLabel, \DateTimeImmutable $now): void
    {
        // If current kind is Custom, this is a no-op
        if (DisplayLabelKind::Custom === $this->displayLabel->kind()) {
            return;
        }

        if ($this->displayLabel->equals($newLabel)) {
            return;
        }

        $this->displayLabel = $newLabel;
        $this->updatedAt    = $now;

        $this->recordDomainEvent(new PatientDisplayLabelChanged(
            patientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            newDisplayLabelKind: $newLabel->kind()->value,
            newDisplayLabelValue: $newLabel->value(),
        ));
    }

    public function customizeDisplayLabel(string $value, \DateTimeImmutable $now): void
    {
        $this->ensureActive();

        $this->displayLabel = DisplayLabel::custom($value);
        $this->updatedAt    = $now;

        $this->recordDomainEvent(new PatientDisplayLabelCustomized(
            patientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            customValue: $value,
        ));
    }

    public function reconcileInto(string $targetPatientId, \DateTimeImmutable $now): void
    {
        $this->ensureActive();

        $this->status    = PatientStatus::Archived;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new PatientReconciledInto(
            sourcePatientId: $this->id->toString(),
            targetPatientId: $targetPatientId,
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function archive(\DateTimeImmutable $now): void
    {
        $this->ensureActive();

        $this->status    = PatientStatus::Archived;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new PatientArchived(
            patientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public static function reconstituteFromPersistence(
        PatientId $id,
        ClinicId $clinicId,
        PatientStatus $status,
        DisplayLabel $displayLabel,
        ?AnimalLink $animalLink,
        ?string $observedSpecies,
        ?string $observedColor,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            status: $status,
            displayLabel: $displayLabel,
            animalLink: $animalLink,
            observedSpecies: $observedSpecies,
            observedColor: $observedColor,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function id(): PatientId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function status(): PatientStatus
    {
        return $this->status;
    }

    public function displayLabel(): DisplayLabel
    {
        return $this->displayLabel;
    }

    public function animalLink(): ?AnimalLink
    {
        return $this->animalLink;
    }

    public function observedSpecies(): ?string
    {
        return $this->observedSpecies;
    }

    public function observedColor(): ?string
    {
        return $this->observedColor;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function ensureActive(): void
    {
        if (PatientStatus::Active !== $this->status) {
            throw new PatientNotActiveException($this->id->toString(), $this->status->value);
        }
    }
}
