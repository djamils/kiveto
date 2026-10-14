<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain;

use App\Context\Animal\Domain\Event\AnimalWeightRecorded;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\WeightRecordId;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * One weighing of an animal taken outside a consultation (at the front desk,
 * during a nurse visit). Weighings are facts: a wrong one is followed by a
 * new one, never edited.
 */
final class WeightRecord extends AggregateRoot
{
    public const int MAX_WEIGHT_GRAMS = 2_000_000;

    private WeightRecordId $id;
    private ClinicId $clinicId;
    private AnimalId $animalId;
    private int $weightGrams;
    private ?int $bodyConditionScore;
    private \DateTimeImmutable $measuredOn;
    private string $authorName;
    private \DateTimeImmutable $recordedAt;

    private function __construct()
    {
    }

    public static function record(
        WeightRecordId $id,
        ClinicId $clinicId,
        AnimalId $animalId,
        int $weightGrams,
        ?int $bodyConditionScore,
        \DateTimeImmutable $measuredOn,
        string $authorName,
        \DateTimeImmutable $recordedAt,
    ): self {
        // The day is typed in clinic time while the clock is UTC: a clinic east of Greenwich is
        // already "tomorrow" for a few hours each night. One day of slack covers every time zone.
        if ($measuredOn->setTime(0, 0) > $recordedAt->modify('+1 day')) {
            throw new \InvalidArgumentException('A weighing cannot be dated in the future.');
        }

        $record = self::reconstitute(
            $id,
            $clinicId,
            $animalId,
            $weightGrams,
            $bodyConditionScore,
            $measuredOn,
            $authorName,
            $recordedAt,
        );

        $record->recordDomainEvent(new AnimalWeightRecorded(
            weightRecordId: $id->toString(),
            animalId: $animalId->toString(),
            clinicId: $clinicId->toString(),
            weightGrams: $weightGrams,
            measuredOn: $record->measuredOn->format('Y-m-d'),
        ));

        return $record;
    }

    public static function reconstitute(
        WeightRecordId $id,
        ClinicId $clinicId,
        AnimalId $animalId,
        int $weightGrams,
        ?int $bodyConditionScore,
        \DateTimeImmutable $measuredOn,
        string $authorName,
        \DateTimeImmutable $recordedAt,
    ): self {
        $authorName = trim($authorName);

        if ($weightGrams <= 0 || $weightGrams > self::MAX_WEIGHT_GRAMS) {
            throw new \InvalidArgumentException('A weight must be between 1 g and 2 000 kg.');
        }

        if (null !== $bodyConditionScore && ($bodyConditionScore < 1 || $bodyConditionScore > 9)) {
            throw new \InvalidArgumentException('A body condition score is a number from 1 to 9.');
        }

        if ('' === $authorName) {
            throw new \InvalidArgumentException('A weighing needs an author.');
        }

        $record                     = new self();
        $record->id                 = $id;
        $record->clinicId           = $clinicId;
        $record->animalId           = $animalId;
        $record->weightGrams        = $weightGrams;
        $record->bodyConditionScore = $bodyConditionScore;
        $record->measuredOn         = $measuredOn->setTime(0, 0);
        $record->authorName         = $authorName;
        $record->recordedAt         = $recordedAt;

        return $record;
    }

    public function id(): WeightRecordId
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

    public function weightGrams(): int
    {
        return $this->weightGrams;
    }

    public function bodyConditionScore(): ?int
    {
        return $this->bodyConditionScore;
    }

    public function measuredOn(): \DateTimeImmutable
    {
        return $this->measuredOn;
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
