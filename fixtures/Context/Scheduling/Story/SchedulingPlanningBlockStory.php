<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Story;

use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Dataset\PlanningBlockDataset;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Zenstruck\Foundry\Story;

final class SchedulingPlanningBlockStory extends Story
{
    public function __construct(
        private readonly PlanningBlockRepositoryInterface $repository,
    ) {
    }

    public function build(): void
    {
        $clinicId = ClinicDataStory::INDEPENDENT_CLINIC_ID;

        $vetUser = ClinicUserFactory::repository()->findOneBy(['email' => 'vet@kiveto.local']);
        if (null === $vetUser) {
            throw new \RuntimeException('vet@kiveto.local not found.');
        }

        $contractorUser = ClinicUserFactory::repository()->findOneBy(['email' => 'contractor@kiveto.local']);
        if (null === $contractorUser) {
            throw new \RuntimeException('contractor@kiveto.local not found.');
        }

        $assistantUser = ClinicUserFactory::repository()->findOneBy(['email' => 'assistant@kiveto.local']);
        if (null === $assistantUser) {
            throw new \RuntimeException('assistant@kiveto.local not found.');
        }

        $vetUserId        = $vetUser->getId()->toRfc4122();
        $contractorUserId = $contractorUser->getId()->toRfc4122();
        $assistantUserId  = $assistantUser->getId()->toRfc4122();

        // Rousseau — weekly consultation Monday 08:00–12:00
        $this->createBlock(
            PlanningBlockDataset::BLOCK_ROUSSEAU_CONSULT_WEEKLY_ID,
            $clinicId,
            $vetUserId,
            PlanningBlockType::CONSULTATION,
            '2026-04-21',
            '08:00',
            '12:00',
            3,
            RecurrenceRule::weekly(),
            null,
        );

        // Rousseau — leave 2026-03-25 (entire day)
        $this->createBlock(
            PlanningBlockDataset::BLOCK_ROUSSEAU_CONGE_MARCH_25_ID,
            $clinicId,
            $vetUserId,
            PlanningBlockType::LEAVE,
            '2026-03-25',
            '00:00',
            '23:59',
            0,
            RecurrenceRule::none(),
            null,
        );

        // Martin — weekdays consultation 08:00–18:00
        $this->createBlock(
            PlanningBlockDataset::BLOCK_MARTIN_CONSULT_WEEKDAYS_ID,
            $clinicId,
            $assistantUserId,
            PlanningBlockType::CONSULTATION,
            '2026-04-21',
            '08:00',
            '18:00',
            4,
            RecurrenceRule::weekdays(),
            null,
        );

        // Dupont — weekly surgery Monday 13:00–17:00
        $this->createBlock(
            PlanningBlockDataset::BLOCK_DUPONT_CHIRURGIE_MONDAY_ID,
            $clinicId,
            $contractorUserId,
            PlanningBlockType::SURGERY,
            '2026-04-21',
            '13:00',
            '17:00',
            1,
            RecurrenceRule::weekly(),
            null,
        );

        // Dupont — at-capacity consultation 2026-03-24 09:00–10:00
        $this->createBlock(
            PlanningBlockDataset::BLOCK_DUPONT_AT_CAPACITY_ID,
            $clinicId,
            $contractorUserId,
            PlanningBlockType::CONSULTATION,
            '2026-03-24',
            '09:00',
            '10:00',
            1,
            RecurrenceRule::none(),
            null,
        );

        $this->buildLyonBlocks($vetUserId);
    }

    /**
     * Lyon runs on its own team, so its agenda needs its own blocks: without
     * them the week view there is an empty grid.
     */
    private function buildLyonBlocks(string $vetUserId): void
    {
        $clinicId = ClinicDataStory::GROUP_CLINIC_ID;

        $lyonVet = ClinicUserFactory::repository()->findOneBy(['email' => 'vet.lyon@kiveto.local']);
        if (null === $lyonVet) {
            throw new \RuntimeException('vet.lyon@kiveto.local not found.');
        }

        $lyonAssistant = ClinicUserFactory::repository()->findOneBy(['email' => 'assistant.lyon@kiveto.local']);
        if (null === $lyonAssistant) {
            throw new \RuntimeException('assistant.lyon@kiveto.local not found.');
        }

        // Rousseau — consultation on weekdays, 09:00–13:00
        $this->createBlock(
            PlanningBlockDataset::BLOCK_LYON_ROUSSEAU_CONSULT_ID,
            $clinicId,
            $vetUserId,
            PlanningBlockType::CONSULTATION,
            '2026-04-21',
            '09:00',
            '13:00',
            3,
            RecurrenceRule::weekdays(),
            null,
        );

        // Lemoine — consultation on weekdays, 13:00–19:00
        $this->createBlock(
            PlanningBlockDataset::BLOCK_LYON_LEMOINE_CONSULT_ID,
            $clinicId,
            $lyonVet->getId()->toRfc4122(),
            PlanningBlockType::CONSULTATION,
            '2026-04-21',
            '13:00',
            '19:00',
            4,
            RecurrenceRule::weekdays(),
            'Consultations de l\'après-midi',
        );

        // Lemoine — weekly surgery Tuesday 08:00–12:00
        $this->createBlock(
            PlanningBlockDataset::BLOCK_LYON_LEMOINE_SURGERY_ID,
            $clinicId,
            $lyonVet->getId()->toRfc4122(),
            PlanningBlockType::SURGERY,
            '2026-04-21',
            '08:00',
            '12:00',
            1,
            RecurrenceRule::weekly(),
            null,
        );

        // Benali — assistant, weekdays 08:00–17:00
        $this->createBlock(
            PlanningBlockDataset::BLOCK_LYON_BENALI_WEEKDAYS_ID,
            $clinicId,
            $lyonAssistant->getId()->toRfc4122(),
            PlanningBlockType::CONSULTATION,
            '2026-04-21',
            '08:00',
            '17:00',
            4,
            RecurrenceRule::weekdays(),
            null,
        );
    }

    private function createBlock(
        string $id,
        string $clinicId,
        string $staffUserId,
        PlanningBlockType $type,
        string $date,
        string $startTime,
        string $endTime,
        int $capacityPerHour,
        RecurrenceRule $rule,
        ?string $note,
    ): void {
        $block = PlanningBlock::create(
            id: PlanningBlockId::fromString($id),
            clinicId: ClinicId::fromString($clinicId),
            staffUserId: UserId::fromString($staffUserId),
            type: $type,
            timeRange: new TimeRange($date, $startTime, $endTime),
            capacityPerHour: $capacityPerHour,
            rule: $rule,
            note: $note,
        );

        $this->repository->save($block);
    }
}
