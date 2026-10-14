<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain;

use App\Context\Animal\Domain\Event\PreventiveCareRecorded;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\PreventiveCareRecordId;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * One preventive act given to an animal — a vaccine, a dewormer, a flea
 * treatment — with the date the next one falls due. The entry is declarative:
 * it may be copied from a booklet the owner brought, and is never edited.
 */
final class PreventiveCareRecord extends AggregateRoot
{
    public const int MAX_LABEL_LENGTH = 120;
    public const int MAX_BATCH_LENGTH = 60;

    private PreventiveCareRecordId $id;
    private ClinicId $clinicId;
    private AnimalId $animalId;
    private PreventiveCareKind $kind;
    private string $label;
    private \DateTimeImmutable $administeredOn;
    private ?string $batchNumber;
    private ?\DateTimeImmutable $nextDueOn;
    private string $authorName;
    private \DateTimeImmutable $recordedAt;

    private function __construct()
    {
    }

    public static function record(
        PreventiveCareRecordId $id,
        ClinicId $clinicId,
        AnimalId $animalId,
        PreventiveCareKind $kind,
        string $label,
        \DateTimeImmutable $administeredOn,
        ?string $batchNumber,
        ?\DateTimeImmutable $nextDueOn,
        string $authorName,
        \DateTimeImmutable $recordedAt,
    ): self {
        // The day is typed in clinic time while the clock is UTC: a clinic east of Greenwich is
        // already "tomorrow" for a few hours each night. One day of slack covers every time zone.
        if ($administeredOn->setTime(0, 0) > $recordedAt->modify('+1 day')) {
            throw new \InvalidArgumentException('A preventive act cannot be dated in the future.');
        }

        $record = self::reconstitute(
            $id,
            $clinicId,
            $animalId,
            $kind,
            $label,
            $administeredOn,
            $batchNumber,
            $nextDueOn,
            $authorName,
            $recordedAt,
        );

        $record->recordDomainEvent(new PreventiveCareRecorded(
            preventiveCareRecordId: $id->toString(),
            animalId: $animalId->toString(),
            clinicId: $clinicId->toString(),
            kind: $kind->value,
            label: $record->label,
            administeredOn: $record->administeredOn->format('Y-m-d'),
        ));

        return $record;
    }

    public static function reconstitute(
        PreventiveCareRecordId $id,
        ClinicId $clinicId,
        AnimalId $animalId,
        PreventiveCareKind $kind,
        string $label,
        \DateTimeImmutable $administeredOn,
        ?string $batchNumber,
        ?\DateTimeImmutable $nextDueOn,
        string $authorName,
        \DateTimeImmutable $recordedAt,
    ): self {
        $label          = trim($label);
        $authorName     = trim($authorName);
        $batchNumber    = null === $batchNumber ? '' : trim($batchNumber);
        $administeredOn = $administeredOn->setTime(0, 0);
        $nextDueOn      = $nextDueOn?->setTime(0, 0);

        if ('' === $label || mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw new \InvalidArgumentException(
                \sprintf('A preventive act needs a label of at most %d characters.', self::MAX_LABEL_LENGTH),
            );
        }

        if (mb_strlen($batchNumber) > self::MAX_BATCH_LENGTH) {
            throw new \InvalidArgumentException(
                \sprintf('A batch number cannot exceed %d characters.', self::MAX_BATCH_LENGTH),
            );
        }

        if (null !== $nextDueOn && $nextDueOn <= $administeredOn) {
            throw new \InvalidArgumentException('The next due date must come after the administration date.');
        }

        if ('' === $authorName) {
            throw new \InvalidArgumentException('A preventive act needs an author.');
        }

        $record                 = new self();
        $record->id             = $id;
        $record->clinicId       = $clinicId;
        $record->animalId       = $animalId;
        $record->kind           = $kind;
        $record->label          = $label;
        $record->administeredOn = $administeredOn;
        $record->batchNumber    = '' === $batchNumber ? null : $batchNumber;
        $record->nextDueOn      = $nextDueOn;
        $record->authorName     = $authorName;
        $record->recordedAt     = $recordedAt;

        return $record;
    }

    public function id(): PreventiveCareRecordId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function animalId(): AnimalId
    {
        return $this->animalId;
    }

    public function kind(): PreventiveCareKind
    {
        return $this->kind;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function administeredOn(): \DateTimeImmutable
    {
        return $this->administeredOn;
    }

    public function batchNumber(): ?string
    {
        return $this->batchNumber;
    }

    public function nextDueOn(): ?\DateTimeImmutable
    {
        return $this->nextDueOn;
    }

    public function authorName(): string
    {
        return $this->authorName;
    }

    public function recordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
