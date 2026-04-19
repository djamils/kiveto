<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain;

use App\Context\Scheduling\Domain\Event\PlanningBlockCreated;
use App\Context\Scheduling\Domain\Event\PlanningBlockDeleted;
use App\Context\Scheduling\Domain\Event\PlanningBlockUpdated;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class PlanningBlock extends AggregateRoot
{
    private PlanningBlockId $id;
    private ClinicId $clinicId;
    private UserId $staffUserId;
    private PlanningBlockType $type;
    private TimeRange $timeRange;
    private int $capacityPerHour;
    private RecurrenceRule $recurrenceRule;
    private ?string $note;

    private function __construct()
    {
    }

    public static function create(
        PlanningBlockId $id,
        ClinicId $clinicId,
        UserId $staffUserId,
        PlanningBlockType $type,
        TimeRange $timeRange,
        int $capacityPerHour,
        RecurrenceRule $rule,
        ?string $note,
    ): self {
        $block                  = new self();
        $block->id              = $id;
        $block->clinicId        = $clinicId;
        $block->staffUserId     = $staffUserId;
        $block->type            = $type;
        $block->timeRange       = $timeRange;
        $block->capacityPerHour = $capacityPerHour;
        $block->recurrenceRule  = $rule;
        $block->note            = $note;

        $block->recordDomainEvent(new PlanningBlockCreated(
            planningBlockId: $id->toString(),
            clinicId: $clinicId->toString(),
            staffUserId: $staffUserId->toString(),
            type: $type->value,
            date: $timeRange->date(),
            startTime: $timeRange->startTime(),
            endTime: $timeRange->endTime(),
            capacityPerHour: $capacityPerHour,
            recurrenceFreq: $rule->freq(),
            recurrenceUntil: $rule->until(),
            note: $note,
        ));

        return $block;
    }

    public static function reconstitute(
        PlanningBlockId $id,
        ClinicId $clinicId,
        UserId $staffUserId,
        PlanningBlockType $type,
        TimeRange $timeRange,
        int $capacityPerHour,
        RecurrenceRule $rule,
        ?string $note,
    ): self {
        $block                  = new self();
        $block->id              = $id;
        $block->clinicId        = $clinicId;
        $block->staffUserId     = $staffUserId;
        $block->type            = $type;
        $block->timeRange       = $timeRange;
        $block->capacityPerHour = $capacityPerHour;
        $block->recurrenceRule  = $rule;
        $block->note            = $note;

        return $block;
    }

    public function update(
        PlanningBlockType $newType,
        TimeRange $newRange,
        int $newCapacity,
        RecurrenceRule $newRule,
        ?string $newNote,
    ): void {
        $this->type            = $newType;
        $this->timeRange       = $newRange;
        $this->capacityPerHour = $newCapacity;
        $this->recurrenceRule  = $newRule;
        $this->note            = $newNote;

        $this->recordDomainEvent(new PlanningBlockUpdated(
            planningBlockId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            staffUserId: $this->staffUserId->toString(),
            type: $newType->value,
            date: $newRange->date(),
            startTime: $newRange->startTime(),
            endTime: $newRange->endTime(),
            capacityPerHour: $newCapacity,
            recurrenceFreq: $newRule->freq(),
            recurrenceUntil: $newRule->until(),
            note: $newNote,
        ));
    }

    public function delete(): void
    {
        $this->recordDomainEvent(new PlanningBlockDeleted(
            planningBlockId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function id(): PlanningBlockId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function staffUserId(): UserId
    {
        return $this->staffUserId;
    }

    public function type(): PlanningBlockType
    {
        return $this->type;
    }

    public function timeRange(): TimeRange
    {
        return $this->timeRange;
    }

    public function capacityPerHour(): int
    {
        return $this->capacityPerHour;
    }

    public function recurrenceRule(): RecurrenceRule
    {
        return $this->recurrenceRule;
    }

    public function note(): ?string
    {
        return $this->note;
    }
}
