<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * Everything the clinic dashboard shows: the day's figures, the timeline of
 * the day, the patients on site, what is waiting to be dealt with and what
 * has already been done today.
 */
final readonly class DashboardView
{
    /**
     * @param list<DashboardKpi>  $kpis
     * @param list<TimelineSlot>  $timeline
     * @param list<FluxCard>      $flux
     * @param list<DashboardTask> $tasks
     * @param list<DoneItem>      $done
     * @param list<string>        $myAudiences audiences the current member belongs to (« Pour moi »)
     * @param string              $countLabel  « 9 rendez-vous », « 5 à venir », « 16 RDV · +25 min »
     * @param int                 $doneCount   rows of the timeline already seen, folded under one line
     * @param string              $doneWhen    « ce matin » or « aujourd'hui »
     */
    public function __construct(
        public string $timeZone,
        public string $nowLabel,
        public array $kpis,
        public array $timeline,
        public int $appointmentCount,
        public ?TimelineRest $rest,
        public array $flux,
        public ?string $nextExpected,
        public array $tasks,
        public array $done,
        public bool $isCalm,
        public bool $hasStockAlerts,
        public ?string $currentUserId,
        public array $myAudiences,
        public string $countLabel = '',
        public int $doneCount = 0,
        public string $doneWhen = 'ce matin',
    ) {
    }
}
