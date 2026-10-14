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
use App\Shared\Domain\Time\ClockInterface;
use Zenstruck\Foundry\Story;

final class SchedulingPlanningBlockStory extends Story
{
    /**
     * Paris weekly trame on top of the historical blocks above, so the
     * agenda's « Blocs planning » shows full days (consultations, surgery,
     * evening and week-end on-call, meetings, training) and its loaded week
     * (SchedulingStory) is booked inside them. Non-bookable blocks stay clear
     * of the hours SchedulingStory books today, whatever weekday that is.
     *
     * [ISO weekday, 'vet' | 'dupont', start, end, type, capacity per hour, note]
     *
     * @var list<array{int, string, string, string, string, int, ?string}>
     */
    public const array PARIS_TRAME = [
        [1, 'vet', '08:00', '12:30', 'consultation', 4, null],
        [1, 'vet', '14:00', '18:30', 'consultation', 4, null],
        [1, 'vet', '18:30', '20:00', 'on_call', 2, 'Garde du soir'],
        [2, 'vet', '14:00', '19:00', 'consultation', 4, null],
        [3, 'vet', '08:00', '12:30', 'consultation', 4, null],
        [3, 'vet', '12:30', '13:30', 'admin', 0, 'Réunion d\'équipe'],
        [3, 'vet', '14:00', '19:00', 'consultation', 4, null],
        [4, 'vet', '08:00', '12:00', 'surgery', 1, 'Stérilisations'],
        [4, 'vet', '14:00', '18:30', 'consultation', 4, null],
        [4, 'vet', '18:30', '20:00', 'on_call', 2, 'Garde du soir'],
        [5, 'vet', '08:00', '12:30', 'consultation', 4, null],
        [5, 'vet', '13:00', '14:00', 'admin', 0, 'Gestion & compta'],
        [5, 'vet', '14:00', '18:30', 'consultation', 4, null],
        [6, 'vet', '09:00', '12:30', 'consultation', 3, 'Samedi matin'],
        [1, 'dupont', '08:00', '12:00', 'consultation', 3, null],
        [1, 'dupont', '14:00', '18:00', 'consultation', 3, null],
        [2, 'dupont', '08:00', '12:00', 'consultation', 3, null],
        [3, 'dupont', '08:00', '12:00', 'consultation', 3, null],
        [3, 'dupont', '14:00', '18:00', 'surgery', 1, 'Chirurgie tissus mous'],
        [4, 'dupont', '08:00', '12:00', 'surgery', 1, 'Orthopédie'],
        [4, 'dupont', '14:00', '18:00', 'consultation', 3, null],
        [4, 'dupont', '18:00', '19:30', 'training', 0, 'Webinaire dermatologie'],
        [6, 'dupont', '12:30', '20:00', 'on_call', 2, 'Garde du week-end'],
        [7, 'dupont', '09:00', '20:00', 'on_call', 2, 'Garde du week-end'],
    ];

    /** A Monday of the trame's first week: weekly blocks repeat on its weekdays. */
    private const string TRAME_MONDAY = '2026-04-20';

    public function __construct(
        private readonly PlanningBlockRepositoryInterface $repository,
        private readonly ClockInterface $clock,
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

        $this->buildParisTrame($clinicId, $vetUserId, $contractorUserId);

        $this->buildLyonBlocks($vetUserId);
    }

    private function buildParisTrame(string $clinicId, string $vetUserId, string $contractorUserId): void
    {
        $staff  = ['vet' => $vetUserId, 'dupont' => $contractorUserId];
        $monday = new \DateTimeImmutable(self::TRAME_MONDAY);

        foreach (self::PARIS_TRAME as $i => [$weekday, $who, $start, $end, $type, $capacity, $note]) {
            $this->createBlock(
                \sprintf('01960000-0000-7000-8000-00000000c%03d', $i + 1),
                $clinicId,
                $staff[$who],
                PlanningBlockType::from($type),
                $monday->modify(\sprintf('+%d days', $weekday - 1))->format('Y-m-d'),
                $start,
                $end,
                $capacity,
                RecurrenceRule::weekly(),
                $note,
            );
        }

        $bernard = ClinicUserFactory::repository()->findOneBy(['email' => 'vet2@kiveto.local']);
        $parisTz = new \DateTimeZone('Europe/Paris');
        $now     = $this->clock->now()->setTimezone($parisTz);
        $week    = $now->modify(\sprintf('-%d days', (int) $now->format('N') - 1));

        // Dr Dupont is off next Friday: a whole-day leave over an empty trame day
        $this->createBlock(
            '01960000-0000-7000-8000-00000000c901',
            $clinicId,
            $contractorUserId,
            PlanningBlockType::LEAVE,
            $week->modify('+11 days')->format('Y-m-d'),
            '08:00',
            '19:00',
            0,
            RecurrenceRule::none(),
            'Congés',
        );

        // Dr Bernard, the Paris vet without bookings, is on leave this week
        if (null !== $bernard) {
            $this->createBlock(
                '01960000-0000-7000-8000-00000000c902',
                $clinicId,
                $bernard->getId()->toRfc4122(),
                PlanningBlockType::LEAVE,
                $week->format('Y-m-d'),
                '08:00',
                '19:00',
                0,
                RecurrenceRule::weekdays($week->modify('+4 days')->format('Y-m-d')),
                'Congés d\'automne',
            );
        }
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
