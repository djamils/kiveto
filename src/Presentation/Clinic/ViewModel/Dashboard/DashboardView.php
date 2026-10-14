<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * Everything the clinic dashboard shows: the day's figures, the patients in
 * hospitalisation, the timeline of the day, the patients on site, what is
 * waiting to be dealt with and what has already been done today.
 */
final readonly class DashboardView
{
    /**
     * @param list<DashboardKpi>  $kpis
     * @param list<HospitalBed>   $beds
     * @param list<TimelineSlot>  $timeline
     * @param list<FluxCard>      $flux
     * @param list<DashboardTask> $tasks
     * @param list<DoneItem>      $done
     * @param list<string>        $myAudiences audiences the current member belongs to (« Pour moi »)
     */
    public function __construct(
        public string $timeZone,
        public string $nowLabel,
        public array $kpis,
        public array $beds,
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
    ) {
    }
}
