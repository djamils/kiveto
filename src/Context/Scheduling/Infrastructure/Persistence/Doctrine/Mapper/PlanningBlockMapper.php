<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\PlanningBlockEntity;
use Symfony\Component\Uid\Uuid;

final class PlanningBlockMapper
{
    public function toDomain(PlanningBlockEntity $entity): PlanningBlock
    {
        return PlanningBlock::reconstitute(
            id: PlanningBlockId::fromString($entity->getId()->toRfc4122()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            practitionerId: UserId::fromString($entity->getPractitionerUserId()->toRfc4122()),
            type: $entity->getType(),
            timeRange: new TimeRange($entity->getDate(), $entity->getStartTime(), $entity->getEndTime()),
            capacityPerHour: $entity->getCapacityPerHour(),
            rule: RecurrenceRule::fromJson(json_encode($entity->getRecurrenceRule(), \JSON_THROW_ON_ERROR)),
            note: $entity->getNote(),
        );
    }

    public function toEntity(PlanningBlock $block): PlanningBlockEntity
    {
        $now    = new \DateTimeImmutable();
        $entity = new PlanningBlockEntity();
        $entity->setId(Uuid::fromString($block->id()->toString()));
        $entity->setClinicId(Uuid::fromString($block->clinicId()->toString()));
        $entity->setPractitionerUserId(Uuid::fromString($block->practitionerId()->toString()));
        $entity->setType($block->type());
        $entity->setDate($block->timeRange()->date());
        $entity->setStartTime($block->timeRange()->startTime());
        $entity->setEndTime($block->timeRange()->endTime());
        $entity->setCapacityPerHour($block->capacityPerHour());
        $entity->setRecurrenceRule($this->ruleToArray($block->recurrenceRule()));
        $entity->setNote($block->note());
        $entity->setCreatedAt($now);
        $entity->setUpdatedAt($now);

        return $entity;
    }

    public function updateEntity(PlanningBlock $block, PlanningBlockEntity $entity): void
    {
        $entity->setType($block->type());
        $entity->setDate($block->timeRange()->date());
        $entity->setStartTime($block->timeRange()->startTime());
        $entity->setEndTime($block->timeRange()->endTime());
        $entity->setCapacityPerHour($block->capacityPerHour());
        $entity->setRecurrenceRule($this->ruleToArray($block->recurrenceRule()));
        $entity->setNote($block->note());
        $entity->setUpdatedAt(new \DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleToArray(RecurrenceRule $rule): array
    {
        return ['freq' => $rule->freq(), 'until' => $rule->until()];
    }
}
