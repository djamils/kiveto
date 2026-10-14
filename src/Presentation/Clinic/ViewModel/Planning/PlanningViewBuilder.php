<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Planning;

use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\ListPlanningBlocksForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\PlanningBlockView;
use App\Presentation\Clinic\ViewModel\Agenda\AgendaViewBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;

/**
 * Data of the planning page (docs/frontend-theme/kiveto-theme/planning-v-topbar_5.html):
 * the practitioners and the occurrences of their planning blocks over a date
 * range, in clinic-local dates and minutes since midnight — the shape the
 * mock-up's grid is drawn from (assets/js/pages/scheduling/planning.js).
 *
 * A block keeps its domain type (`type`, sent back on save) next to the
 * mock-up's key (`t`), and the anchor day of its series (`base`): a recurring
 * block is one row whose occurrences all share its id.
 *
 * @phpstan-import-type AgendaVet from AgendaViewBuilder
 *
 * @phpstan-type PlanningBlock array{
 *     id: string, date: string, base: string, vet: string, s: int, e: int,
 *     t: string, type: string, cap: int, rec: string, until: ?string, note: ?string
 * }
 * @phpstan-type PlanningRange array{from: string, to: string, blocks: list<PlanningBlock>}
 */
final readonly class PlanningViewBuilder
{
    /** Widest range a single fetch may cover (a month grid is 42 days). */
    public const int MAX_RANGE_DAYS = 62;

    /** Planning block types as the mock-up names them. */
    private const array TYPE = [
        'consultation' => 'consult',
        'health_check' => 'bilan',
        'surgery'      => 'chir',
        'emergency'    => 'garde',
        'on_call'      => 'garde',
        'admin'        => 'admin',
        'training'     => 'formation',
        'leave'        => 'conge',
        'break'        => 'pause',
    ];

    private const array VIEWS = ['day', 'week', 'month'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private AgendaViewBuilder $agenda,
        private ClockInterface $clock,
    ) {
    }

    public function timeZone(string $clinicId): \DateTimeZone
    {
        return $this->agenda->timeZone($clinicId);
    }

    /**
     * The page payload: today, the selected period and the blocks of the
     * 6-week grid of the selected month, which holds the selected week.
     *
     * @return array{
     *     today: string, nowMin: int, view: string, date: string, me: string,
     *     vets: list<AgendaVet>, range: PlanningRange
     * }
     */
    public function page(string $clinicId, string $userId, ?string $date, ?string $view): array
    {
        $tz    = $this->timeZone($clinicId);
        $now   = $this->clock->now()->setTimezone($tz);
        $today = $now->setTime(0, 0);

        $selected = $today;
        if (null !== $date && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
            if (false !== $parsed && $parsed->format('Y-m-d') === $date) {
                $selected = $parsed;
            }
        }

        $firstOfMonth = $selected->modify('first day of this month');
        $gridStart    = $firstOfMonth->modify(\sprintf('-%d days', (int) $firstOfMonth->format('N') - 1));

        return [
            'today'  => $today->format('Y-m-d'),
            'nowMin' => (int) $now->format('G') * 60 + (int) $now->format('i'),
            'view'   => \in_array($view, self::VIEWS, true) ? $view : 'day',
            'date'   => $selected->format('Y-m-d'),
            'me'     => $userId,
            'vets'   => $this->agenda->vets($clinicId, $userId),
            'range'  => $this->range($clinicId, $gridStart, $gridStart->modify('+41 days')),
        ];
    }

    /**
     * Block occurrences between two clinic-local days (inclusive).
     *
     * @return PlanningRange
     */
    public function range(string $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $views = $this->queryBus->ask(new ListPlanningBlocksForClinicDateRange(
            clinicId: $clinicId,
            fromDate: $from->format('Y-m-d'),
            toDate: $to->format('Y-m-d'),
        ));
        \assert(\is_array($views));

        $blocks = [];
        foreach ($views as $view) {
            \assert($view instanceof PlanningBlockView);
            $blocks[] = [
                'id'    => $view->id,
                'date'  => $view->date,
                'base'  => $view->baseDate,
                'vet'   => $view->staffUserId,
                's'     => $this->minutes($view->startTime),
                'e'     => $this->minutes($view->endTime),
                't'     => self::TYPE[$view->type] ?? 'consult',
                'type'  => $view->type,
                'cap'   => $view->capacityPerHour,
                'rec'   => strtoupper($view->recurrenceFreq),
                'until' => $view->recurrenceUntil,
                'note'  => $view->note,
            ];
        }

        return [
            'from'   => $from->format('Y-m-d'),
            'to'     => $to->format('Y-m-d'),
            'blocks' => $blocks,
        ];
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [1 => '0']);

        return $h * 60 + $m;
    }
}
