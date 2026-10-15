<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Admission\Application\Query\ListActiveAdmissions\ListActiveAdmissions;
use App\Context\Admission\Application\Query\ListAdmissionsClosedSince\ListAdmissionsClosedSince;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetBillingStats\GetBillingStats;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListArticles;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\GetUserMembershipInClinic;
use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\MembershipDetails;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions\AdmissionTotal;
use App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions\GetConsultationTotalsForAdmissions;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListItemView;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultations;
use App\Context\Inventory\Application\Query\WatchStock\StockWatchKind;
use App\Context\Inventory\Application\Query\WatchStock\StockWatchRow;
use App\Context\Inventory\Application\Query\WatchStock\WatchStock;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\ListPlanningBlocksForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\PlanningBlockView;
use App\Presentation\Clinic\ViewModel\Agenda\AgendaViewBuilder;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the clinic dashboard from the contexts that own each block:
 * Scheduling (the day's appointments), Admission (who is on site, who left),
 * Consultation (consultations left open or closed today, what each patient at
 * the desk owes), Billing (takings, unpaid invoices), Inventory (stock alerts)
 * and Clinic (opening hours, team, the member's role). Every figure comes from
 * a query; a block with nothing behind it renders its empty state.
 */
final readonly class DashboardViewBuilder
{
    /** Booking grid used to turn opening hours into slots. */
    private const int SLOT_MINUTES = 30;

    /** A gap between two appointments shown as « libre » from this length on. */
    private const int FREE_GAP_MINUTES = 30;

    /** A patient waiting longer than this gets the red chrono. */
    private const int LONG_WAIT_MINUTES = 15;

    /** A booking this late shows its delay instead of its length. */
    private const int LATE_SHOWN_MINUTES = 5;

    /** The day runs late — the timeline count and the waiting KPI say it — from this delay on. */
    private const int DELAY_SHOWN_MINUTES = 10;

    /** A booking missed by more than this is no longer a delay but a patient who did not come. */
    private const int LATE_HORIZON_MINUTES = 120;

    /** Individual tasks kept per source before the rest is summed up in one line. */
    private const int TASKS_PER_SOURCE = 4;

    /** The units of the shelves, as the stock page says them. */
    private const array UNITS = [
        'TABLET' => 'cp', 'CAPSULE' => 'gélule', 'VIAL' => 'flacon', 'BOTTLE' => 'flacon', 'UNIT' => 'unité', 'BOX' => 'boîte',
        'BAG'    => 'sac', 'PIPETTE' => 'pipette', 'DOSE' => 'dose', 'SACHET' => 'sachet', 'AMPULE' => 'ampoule', 'ML' => 'mL', 'L' => 'L', 'G' => 'g', 'KG' => 'kg',
    ];

    private const array EMERGENCY_TRIAGE = ['emergency', 'vital_emergency'];

    private const array SPECIES_EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇'];

    private const array SPECIES_LABEL = ['dog' => 'Chien', 'cat' => 'Chat', 'nac' => 'NAC', 'other' => 'Animal'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urls,
        private PatientFactsReader $facts,
    ) {
    }

    public function build(string $clinicId, ?string $userId): DashboardView
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));
        $settings = $settings instanceof ClinicSettingsView ? $settings : null;
        $timeZone = new \DateTimeZone(null !== $settings ? $settings->timeZone : 'Europe/Paris');
        $utc      = new \DateTimeZone('UTC');
        $now      = $this->clock->now()->setTimezone($timeZone);
        $midnight = $now->setTime(0, 0);

        $appointments = $this->appointments($clinicId, $now, $timeZone);
        $active       = $this->admissions(new ListActiveAdmissions($clinicId));
        $closedToday  = $this->admissions(new ListAdmissionsClosedSince($clinicId, $midnight->setTimezone($utc)));

        // Emergencies that walked in today without a booking join the « Fil du
        // jour » at their arrival time.
        $walkIns = array_values(array_filter(
            [...$active, ...$closedToday],
            static fn (WaitingRoomItemDto $item): bool => 'appointment_booked' !== $item->intakeChannel
                && \in_array($item->triageLevel, self::EMERGENCY_TRIAGE, true)
                && new \DateTimeImmutable($item->openedAt) >= $midnight,
        ));

        $animals = $this->animals($clinicId, [
            ...array_map(static fn (WaitingRoomItemDto $item): ?string => $item->knownAnimalId, [...$active, ...$walkIns]),
            ...array_map(static fn (AppointmentItem $item): ?string => $item->animalId, $appointments),
        ]);
        $opening = null === $settings ? null : $this->openingToday($settings, $now);
        $slots   = $this->bookableSlots($clinicId, $now);

        $practitioners = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($practitioners));
        $vets = $this->vetNames($practitioners);

        $facts = $this->facts->read($clinicId, $animals, $vets, $now);

        $openConsultations = $this->openConsultations($clinicId);
        $openByPatient     = [];
        foreach ($openConsultations as $consultation) {
            // Most recent first: the latest open consultation of a patient wins.
            $openByPatient[$consultation->patientId] ??= $consultation->consultationId;
        }

        $admissionsById = [];
        foreach ([...$closedToday, ...$active] as $item) {
            $admissionsById[$item->admissionId] = $item;
        }

        $totals = $this->totals($clinicId, $active);

        $stats = $this->queryBus->ask(new GetBillingStats($clinicId, $midnight->setTimezone($utc), $now->setTimezone($utc)));
        \assert($stats instanceof BillingStats);

        // The same weekday a week earlier, up to the same time: the takings
        // compare with it.
        $lastWeek = $this->queryBus->ask(new GetBillingStats(
            $clinicId,
            $midnight->modify('-7 days')->setTimezone($utc),
            $now->modify('-7 days')->setTimezone($utc),
        ));
        \assert($lastWeek instanceof BillingStats);

        // The stock tasks read the shelves as the stock page and the
        // navigation badge do, so the three never disagree.
        $alerts = $this->queryBus->ask(new WatchStock($clinicId, $timeZone->getName()));
        \assert(\is_array($alerts));
        /** @var list<StockWatchRow> $alerts */
        $upcoming = array_values(array_filter(
            $appointments,
            fn (AppointmentItem $item): bool => 'PLANNED' === $item->status
                && null === $item->linkedAdmissionId
                && $this->localStart($item, $timeZone) >= $now,
        ));

        $isCalm = [] === $active && \count($upcoming) <= 2;
        $delay  = $this->delay($appointments, $admissionsById, $now, $timeZone);

        $timeline = $this->timeline($appointments, $walkIns, $timeZone, $now, $animals, $facts, $openByPatient, $admissionsById);

        $rows = 0;
        $done = 0;
        foreach ($timeline as $slot) {
            foreach ($slot->appointments as $row) {
                ++$rows;
                $done += $row->isDone() ? 1 : 0;
            }
        }

        $count = $delay >= self::DELAY_SHOWN_MINUTES
            ? \sprintf('%d RDV · +%s', $rows, $this->duration($delay))
            : SheetText::plural($rows, 'rendez-vous', 'rendez-vous');

        return new DashboardView(
            timeZone: $timeZone->getName(),
            nowLabel: $now->format('H:i'),
            kpis: $this->kpis(
                $stats,
                $lastWeek,
                $appointments,
                $opening,
                $slots,
                \count($practitioners),
                $active,
                $closedToday,
                $now,
                $midnight,
                $isCalm,
                $delay,
            ),
            timeline: $timeline,
            appointmentCount: $rows,
            rest: $this->rest($appointments, $opening, $now, $timeZone),
            flux: $this->flux($active, $animals, $facts, $vets, $openByPatient, $totals, $now),
            nextExpected: $this->nextExpected($upcoming, $timeZone),
            tasks: $this->tasks($clinicId, $active, $totals, $alerts, $stats, $openConsultations, $midnight->setTimezone($utc), $now),
            done: $this->done($clinicId, $closedToday, $appointments, $midnight->setTimezone($utc), $timeZone),
            isCalm: $isCalm,
            hasStockAlerts: [] !== $alerts,
            currentUserId: $userId,
            myAudiences: $this->audiences($clinicId, $userId),
            countLabel: $done > 0 ? (string) preg_replace('/^\d+ (RDV|rendez-vous)/u', ($rows - $done) . ' à venir', $count) : $count,
            doneCount: $done,
            doneWhen: (int) $now->format('G') < 13 ? 'ce matin' : 'aujourd\'hui',
        );
    }

    /**
     * Today's appointments, cancelled ones left out, earliest first.
     *
     * @return list<AppointmentItem>
     */
    private function appointments(string $clinicId, \DateTimeImmutable $now, \DateTimeZone $timeZone): array
    {
        $items = $this->queryBus->ask(GetAgendaForClinicDateRange::forDay($clinicId, $now, $timeZone));
        \assert(\is_array($items));

        $appointments = [];

        foreach ($items as $item) {
            \assert($item instanceof AppointmentItem);

            if ('CANCELLED' !== $item->status) {
                $appointments[] = $item;
            }
        }

        usort($appointments, static fn (AppointmentItem $a, AppointmentItem $b): int => $a->startsAtUtc <=> $b->startsAtUtc);

        return $appointments;
    }

    /**
     * @return list<WaitingRoomItemDto>
     */
    private function admissions(ListActiveAdmissions|ListAdmissionsClosedSince $query): array
    {
        $items = $this->queryBus->ask($query);
        \assert(\is_array($items));

        return array_values(array_filter($items, static fn (mixed $item): bool => $item instanceof WaitingRoomItemDto));
    }

    /**
     * Today's opening intervals in minutes since midnight, or an empty list on
     * a closed day; null when the clinic has not entered its hours.
     *
     * @return list<array{int, int}>|null
     */
    private function openingToday(ClinicSettingsView $settings, \DateTimeImmutable $now): ?array
    {
        if ([] === array_filter($settings->week)) {
            return null;
        }

        foreach ($settings->closures as $closure) {
            if ($closure['date'] === $now->format('Y-m-d')) {
                return [];
            }
        }

        $intervals = [];

        foreach ($settings->week[(int) $now->format('N')] ?? [] as $slot) {
            $intervals[] = [$this->minutes($slot['opensAt']), $this->minutes($slot['closesAt'])];
        }

        return $intervals;
    }

    /**
     * The identified animals of the day, by animal id.
     *
     * @param list<string|null> $animalIds
     *
     * @return array<string, AnimalView>
     */
    private function animals(string $clinicId, array $animalIds): array
    {
        $animals = [];

        foreach ($animalIds as $animalId) {
            if (null === $animalId || isset($animals[$animalId])) {
                continue;
            }

            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $animalId));

            if ($animal instanceof AnimalView) {
                $animals[$animalId] = $animal;
            }
        }

        return $animals;
    }

    /**
     * « Dr Lambert » by user id, as the cards name practitioners.
     *
     * @param array<mixed> $practitioners
     *
     * @return array<string, string>
     */
    private function vetNames(array $practitioners): array
    {
        $names = [];

        foreach ($practitioners as $item) {
            \assert($item instanceof ClinicVeterinarianItem);
            $title                = ['DR' => 'Dr ', 'PR' => 'Pr '][$item->professionalTitle ?? ''] ?? '';
            $names[$item->userId] = $title . ($item->displayName ?? 'Praticien');
        }

        return $names;
    }

    /**
     * Consultations still open, most recent first.
     *
     * @return list<ConsultationListItemView>
     */
    private function openConsultations(string $clinicId): array
    {
        $result = $this->queryBus->ask(new SearchConsultations($clinicId, statuses: ['OPEN'], sort: 'datetime', direction: 'desc', limit: 100));
        \assert(\is_array($result) && \is_array($result['items'] ?? null));

        return array_values(array_filter(
            $result['items'],
            static fn (mixed $item): bool => $item instanceof ConsultationListItemView,
        ));
    }

    /**
     * What each patient waiting at the desk owes, by admission id.
     *
     * @param list<WaitingRoomItemDto> $active
     *
     * @return array<string, AdmissionTotal>
     */
    private function totals(string $clinicId, array $active): array
    {
        $ids = [];
        foreach ($active as $item) {
            if ('at_reception' === $item->locationStatus) {
                $ids[] = $item->admissionId;
            }
        }

        if ([] === $ids) {
            return [];
        }

        $totals = $this->queryBus->ask(new GetConsultationTotalsForAdmissions($clinicId, $ids));
        \assert(\is_array($totals));

        $byAdmission = [];
        foreach ($totals as $total) {
            \assert($total instanceof AdmissionTotal);
            $byAdmission[$total->admissionId] = $total;
        }

        return $byAdmission;
    }

    /**
     * How late the day runs: the longest wait past its booked time of an
     * appointment not taken in charge yet — not arrived, or arrived and still
     * in the waiting room. Bookings missed by more than LATE_HORIZON_MINUTES
     * are no longer a delay but a patient who did not come.
     *
     * @param list<AppointmentItem>             $appointments
     * @param array<string, WaitingRoomItemDto> $admissionsById
     */
    private function delay(array $appointments, array $admissionsById, \DateTimeImmutable $now, \DateTimeZone $timeZone): int
    {
        $delay = 0;

        foreach ($appointments as $item) {
            $late = $this->lateness($item, $admissionsById, $now, $timeZone);

            if ($late <= self::LATE_HORIZON_MINUTES) {
                $delay = max($delay, $late);
            }
        }

        return $delay;
    }

    /**
     * Minutes an appointment waits past its booked time, 0 once the patient
     * is taken in charge (or the appointment is over).
     *
     * @param array<string, WaitingRoomItemDto> $admissionsById
     */
    private function lateness(AppointmentItem $item, array $admissionsById, \DateTimeImmutable $now, \DateTimeZone $timeZone): int
    {
        if ('PLANNED' !== $item->status) {
            return 0;
        }

        $admission = null === $item->linkedAdmissionId ? null : ($admissionsById[$item->linkedAdmissionId] ?? null);

        if (null !== $item->linkedAdmissionId && (null === $admission || 'in_waiting_room' !== $admission->locationStatus || null !== $admission->closedAt)) {
            return 0;
        }

        return max(0, intdiv($now->getTimestamp() - $this->localStart($item, $timeZone)->getTimestamp(), 60));
    }

    /**
     * @param list<AppointmentItem>        $appointments
     * @param list<array{int, int}>|null   $opening
     * @param list<array{int, int, float}> $slots        today's bookable blocks
     * @param list<WaitingRoomItemDto>     $active
     * @param list<WaitingRoomItemDto>     $closedToday
     *
     * @return list<DashboardKpi>
     */
    private function kpis(
        BillingStats $stats,
        BillingStats $lastWeek,
        array $appointments,
        ?array $opening,
        array $slots,
        int $practitionerCount,
        array $active,
        array $closedToday,
        \DateTimeImmutable $now,
        \DateTimeImmutable $midnight,
        bool $isCalm,
        int $delay,
    ): array {
        $trend = null;
        $tone  = '';

        if (!$isCalm && $lastWeek->collectedMinorUnits > 0) {
            $change = (int) round(($stats->collectedMinorUnits - $lastWeek->collectedMinorUnits) * 100 / $lastWeek->collectedMinorUnits);
            $trend  = ($change >= 0 ? '▲ ' : '▼ ') . abs($change) . ' %';
            $tone   = $change >= 0 ? 'up' : 'warn';
        }

        $kpis = [new DashboardKpi(
            value: $this->money($stats->collectedMinorUnits, $stats->currency),
            label: 'encaissés',
            note: $isCalm ? 'journée calme' : $trend,
            noteTone: $isCalm ? '' : $tone,
        )];

        $booked = \count($appointments);

        if (null !== $opening && [] !== $opening) {
            // The practitioners' bookable blocks of the day say how many
            // appointments it holds; without a planning, the opening hours
            // times the practitioners.
            if ([] === $slots) {
                $slots = [];
                foreach ($opening as [$opens, $closes]) {
                    $slots[] = [$opens, $closes, 60 / self::SLOT_MINUTES * max(1, $practitionerCount)];
                }
            }

            $capacity  = $this->capacity($slots, 0);
            $afternoon = $this->afternoonSlots($appointments, $slots, $now);

            $note = null;
            $tone = '';

            if ($booked >= $capacity && $capacity > 0) {
                $note = 'complet';
                $tone = 'warn';
            } elseif (null !== $afternoon && $afternoon['booked'] <= 1) {
                // One booking at most: the afternoon is as good as free.
                $note = 'après-midi libre';
                $tone = 'up';
            } elseif (null !== $afternoon && $afternoon['free'] > 0) {
                $note = SheetText::plural($afternoon['free'], 'libre', 'libres') . ' cet aprem';
                $tone = 'up';
            }

            $kpis[] = new DashboardKpi($booked . '/' . $capacity, 'créneaux remplis', $note, $tone);
        } else {
            $kpis[] = new DashboardKpi(
                (string) $booked,
                'rendez-vous',
                null !== $opening ? 'fermé aujourd\'hui' : null,
            );
        }

        $waiting = array_values(array_filter(
            $active,
            static fn (WaitingRoomItemDto $item): bool => 'in_waiting_room' === $item->locationStatus,
        ));
        $average = [] === $waiting ? 0 : intdiv(
            array_sum(array_map(fn (WaitingRoomItemDto $item): int => $this->minutesSince($item->openedAt, $now), $waiting)),
            \count($waiting),
        );
        $late   = $delay >= self::DELAY_SHOWN_MINUTES;
        $kpis[] = new DashboardKpi(
            $this->duration($average),
            'attente moyenne',
            $late ? '+' . $this->duration($delay) : null,
            $late ? 'warn' : '',
        );

        $walkIns     = 0;
        $emergencies = 0;

        foreach ([...$active, ...$closedToday] as $item) {
            if ('appointment_booked' !== $item->intakeChannel && new \DateTimeImmutable($item->openedAt) >= $midnight) {
                ++$walkIns;
                $emergencies += \in_array($item->triageLevel, self::EMERGENCY_TRIAGE, true) ? 1 : 0;
            }
        }

        $kpis[] = new DashboardKpi(
            (string) $walkIns,
            'sans rendez-vous',
            $emergencies > 0 ? SheetText::plural($emergencies, 'urgence', 'urgences') : null,
            $emergencies > 0 ? 'warn' : '',
        );

        return $kpis;
    }

    /**
     * Today's blocks open to bookings: [start, end] in minutes since the
     * clinic's midnight and the appointments they hold per hour.
     *
     * @return list<array{int, int, float}>
     */
    private function bookableSlots(string $clinicId, \DateTimeImmutable $now): array
    {
        $views = $this->queryBus->ask(new ListPlanningBlocksForClinicDateRange($clinicId, $now->format('Y-m-d'), $now->format('Y-m-d')));
        \assert(\is_array($views));

        $slots = [];

        foreach ($views as $view) {
            \assert($view instanceof PlanningBlockView);

            if ($view->acceptsAppointments) {
                $slots[] = [
                    $this->minutes($view->startTime),
                    $this->minutes($view->endTime),
                    $view->hasCapacityLimit ? (float) $view->capacityPerHour : 60 / self::SLOT_MINUTES,
                ];
            }
        }

        return $slots;
    }

    /**
     * Appointments the slots hold from a time of day on.
     *
     * @param list<array{int, int, float}> $slots
     */
    private function capacity(array $slots, int $from): int
    {
        $capacity = 0.0;

        foreach ($slots as [$start, $end, $perHour]) {
            $capacity += max(0, $end - max($start, $from)) / 60 * $perHour;
        }

        return (int) floor($capacity);
    }

    /**
     * The afternoon's capacity (from 13:00) and how much of it is booked;
     * null when nothing can be booked in the afternoon.
     *
     * @param list<AppointmentItem>        $appointments
     * @param list<array{int, int, float}> $slots
     *
     * @return array{booked: int, free: int}|null
     */
    private function afternoonSlots(array $appointments, array $slots, \DateTimeImmutable $now): ?array
    {
        $noon     = 13 * 60;
        $capacity = $this->capacity($slots, $noon);

        if (0 === $capacity) {
            return null;
        }

        $booked = 0;

        foreach ($appointments as $appointment) {
            if ($this->dayMinutes($this->localStart($appointment, $now->getTimezone())) >= $noon) {
                ++$booked;
            }
        }

        return ['booked' => $booked, 'free' => max(0, $capacity - $booked)];
    }

    /**
     * Appointments and walk-in emergencies grouped by start time, with the
     * free gaps between them.
     *
     * @param list<AppointmentItem>             $appointments
     * @param list<WaitingRoomItemDto>          $walkIns
     * @param array<string, AnimalView>         $animals
     * @param array<string, PatientFacts>       $facts
     * @param array<string, string>             $openByPatient
     * @param array<string, WaitingRoomItemDto> $admissionsById
     *
     * @return list<TimelineSlot>
     */
    private function timeline(
        array $appointments,
        array $walkIns,
        \DateTimeZone $timeZone,
        \DateTimeImmutable $now,
        array $animals,
        array $facts,
        array $openByPatient,
        array $admissionsById,
    ): array {
        /** @var array<string, list<array{int, TimelineAppointment}>> $byTime */
        $byTime = [];

        foreach ($appointments as $appointment) {
            $time            = $this->localStart($appointment, $timeZone)->format('H:i');
            $byTime[$time][] = [$appointment->durationMinutes, $this->timelineAppointment($appointment, $time, $now, $timeZone, $facts, $openByPatient, $admissionsById)];
        }

        foreach ($walkIns as $item) {
            $time            = new \DateTimeImmutable($item->openedAt)->setTimezone($timeZone)->format('H:i');
            $byTime[$time][] = [self::SLOT_MINUTES, $this->walkInRow($item, $time, $animals, $facts, $openByPatient)];
        }

        ksort($byTime);

        $slots   = [];
        $lastEnd = null;

        foreach ($byTime as $time => $group) {
            $start = $this->minutes((string) $time);

            if (null !== $lastEnd && $start - $lastEnd >= self::FREE_GAP_MINUTES) {
                $slots[] = new TimelineSlot($this->clockLabel($lastEnd), $lastEnd, $start, []);
            }

            $end = $start;

            foreach ($group as [$duration]) {
                $end = max($end, $start + $duration);
            }

            $slots[] = new TimelineSlot((string) $time, $start, $end, array_map(static fn (array $row): TimelineAppointment => $row[1], $group));

            $lastEnd = max($lastEnd ?? 0, $end);
        }

        return $slots;
    }

    /**
     * @param array<string, PatientFacts>       $facts
     * @param array<string, string>             $openByPatient
     * @param array<string, WaitingRoomItemDto> $admissionsById
     */
    private function timelineAppointment(
        AppointmentItem $item,
        string $time,
        \DateTimeImmutable $now,
        \DateTimeZone $timeZone,
        array $facts,
        array $openByPatient,
        array $admissionsById,
    ): TimelineAppointment {
        $state = match (true) {
            'COMPLETED' === $item->status     => 'done',
            'NO_SHOW' === $item->status       => 'noshow',
            null !== $item->linkedAdmissionId => 'onsite',
            default                           => 'planned',
        };

        $fact      = null === $item->animalId ? null : ($facts[$item->animalId] ?? null);
        $admission = null === $item->linkedAdmissionId ? null : ($admissionsById[$item->linkedAdmissionId] ?? null);
        $late      = 'planned' === $state ? $this->lateness($item, $admissionsById, $now, $timeZone) : 0;
        $kind      = match (AgendaViewBuilder::motif($item->reason)) {
            'chir'  => 'chir',
            'urg'   => 'urg',
            default => 'consult',
        };

        $name  = $item->animalLabel ?? 'Créneau sans patient';
        $breed = null !== $item->animalBreed && '' !== $item->animalBreed ? $item->animalBreed : '';
        $owner = $fact->owner ?? (null === $item->ownerLabel ? null : $this->shortName($item->ownerLabel));
        $parts = array_filter(
            [$item->reason, $owner],
            static fn (?string $part): bool => null !== $part && '' !== $part,
        );

        $consultationId = match ($state) {
            'done'   => $fact?->todayConsultationId,
            'onsite' => null === $admission ? null : ($openByPatient[$admission->patientId] ?? null),
            default  => null,
        };

        return new TimelineAppointment(
            id: $item->id,
            emoji: $this->emoji($item->animalSpecies),
            title: '' === $breed ? $name : $name . ' · ' . $breed,
            subtitle: [] === $parts ? 'Sans motif' : implode(' — ', $parts),
            durationLabel: match (true) {
                'done' === $state                                                        => 'terminé',
                'noshow' === $state                                                      => 'absent',
                'onsite' === $state                                                      => 'sur place',
                $late >= self::LATE_SHOWN_MINUTES && $late <= self::LATE_HORIZON_MINUTES => '+' . $this->duration($late),
                default                                                                  => $item->durationMinutes . ' min',
            },
            state: $state,
            animalId: $item->animalId,
            animalName: $item->animalLabel,
            kind: $kind,
            tag: ['chir' => 'Chirurgie', 'urg' => 'Urgence'][$kind] ?? null,
            time: $time,
            lateMinutes: $late,
            admissionId: $item->linkedAdmissionId,
            location: $admission?->locationStatus,
            breed: '' === $breed ? 'Animal' : $breed,
            motif: $item->reason ?? '',
            consultationId: $consultationId,
            facts: $fact,
        );
    }

    /**
     * An emergency that walked in without an appointment, as a timeline row.
     *
     * @param array<string, AnimalView>   $animals
     * @param array<string, PatientFacts> $facts
     * @param array<string, string>       $openByPatient
     */
    private function walkInRow(WaitingRoomItemDto $item, string $time, array $animals, array $facts, array $openByPatient): TimelineAppointment
    {
        $animal = null === $item->knownAnimalId ? null : ($animals[$item->knownAnimalId] ?? null);
        $fact   = null === $item->knownAnimalId ? null : ($facts[$item->knownAnimalId] ?? null);
        $breed  = null === $animal
            ? ($item->physicalDescription ?? 'Non identifié')
            : ($animal->breedName ?? self::SPECIES_LABEL[$animal->species] ?? 'Animal');
        $closed = null !== $item->closedAt;
        $motif  = $this->motif($item->appointmentReason ?? $item->triageNotes);

        return new TimelineAppointment(
            id: $item->admissionId,
            emoji: $this->emoji($animal?->species),
            title: $item->displayLabel . ' · ' . $breed,
            subtitle: $motif . ' — sans rendez-vous',
            durationLabel: $closed ? 'terminé' : null,
            state: $closed ? 'done' : 'onsite',
            animalId: $item->knownAnimalId,
            animalName: $item->displayLabel,
            kind: 'urg',
            tag: 'Urgence',
            time: $time,
            isWalkIn: true,
            admissionId: $item->admissionId,
            location: $closed ? null : $item->locationStatus,
            breed: $breed,
            motif: $motif,
            consultationId: $closed ? $fact?->todayConsultationId : ($openByPatient[$item->patientId] ?? null),
            facts: $fact,
        );
    }

    /**
     * The end of the day left free after the last appointment, if it is worth
     * pointing out (an hour or more of opening time).
     *
     * @param list<AppointmentItem>      $appointments
     * @param list<array{int, int}>|null $opening
     */
    private function rest(array $appointments, ?array $opening, \DateTimeImmutable $now, \DateTimeZone $timeZone): ?TimelineRest
    {
        if ([] === $opening) {
            return new TimelineRest(
                'La clinique est fermée aujourd\'hui',
                [] === $appointments
                    ? 'Aucun rendez-vous n\'est prévu. Les prochaines réservations apparaîtront ici.'
                    : 'Les rendez-vous ci-dessus ont été pris malgré la fermeture.',
            );
        }

        $from = $this->dayMinutes($now);

        foreach ($appointments as $appointment) {
            $from = max($from, $this->dayMinutes($this->localStart($appointment, $timeZone)) + $appointment->durationMinutes);
        }

        if (null === $opening) {
            return [] === $appointments
                ? new TimelineRest(
                    'La journée est libre',
                    'Aucun rendez-vous aujourd\'hui — bloquez une plage pour la chirurgie, l\'administratif, la formation…',
                )
                : null;
        }

        $free  = 0;
        $close = 0;

        foreach ($opening as [$opens, $closes]) {
            $free += max(0, $closes - max($opens, $from));
            $close = max($close, $closes);
        }

        if ($free < 60) {
            return null;
        }

        $slots = intdiv($free, self::SLOT_MINUTES);

        return new TimelineRest(
            [] === $appointments ? 'La journée est libre' : 'Le reste de la journée est libre',
            \sprintf(
                '%s d\'ici la fermeture (%s) — ou bloquez une plage pour la chirurgie, l\'administratif, la formation…',
                SheetText::plural($slots, 'créneau libre', 'créneaux libres'),
                $this->clockLabel($close),
            ),
        );
    }

    /**
     * @param list<AppointmentItem> $upcoming
     */
    private function nextExpected(array $upcoming, \DateTimeZone $timeZone): ?string
    {
        $next = $upcoming[0] ?? null;

        if (null === $next) {
            return null;
        }

        return \sprintf('%s à %s', $next->animalLabel ?? 'Un patient', $this->localStart($next, $timeZone)->format('H:i'));
    }

    /**
     * Patients on site, emergencies first, then by arrival. Each follows its
     * own sequence: « Arrivée · Consult. · Règlement », with « Soins » for an
     * emergency or a patient in surgery.
     *
     * @param list<WaitingRoomItemDto>      $onSite
     * @param array<string, AnimalView>     $animals
     * @param array<string, PatientFacts>   $facts
     * @param array<string, string>         $vets
     * @param array<string, string>         $openByPatient
     * @param array<string, AdmissionTotal> $totals
     *
     * @return list<FluxCard>
     */
    private function flux(array $onSite, array $animals, array $facts, array $vets, array $openByPatient, array $totals, \DateTimeImmutable $now): array
    {
        usort($onSite, static function (WaitingRoomItemDto $a, WaitingRoomItemDto $b): int {
            $urgent = (int) \in_array($b->triageLevel, self::EMERGENCY_TRIAGE, true) <=> (int) \in_array($a->triageLevel, self::EMERGENCY_TRIAGE, true);

            return 0 !== $urgent ? $urgent : $a->openedAt <=> $b->openedAt;
        });

        $cards = [];

        foreach ($onSite as $item) {
            $animal      = null === $item->knownAnimalId ? null : ($animals[$item->knownAnimalId] ?? null);
            $fact        = null === $item->knownAnimalId ? null : ($facts[$item->knownAnimalId] ?? null);
            $isEmergency = \in_array($item->triageLevel, self::EMERGENCY_TRIAGE, true);
            $minutes     = $this->minutesSince($item->locationSince ?? $item->openedAt, $now);
            $motif       = $this->motif($item->appointmentReason ?? $item->triageNotes);
            $steps       = $isEmergency || 'in_surgery' === $item->locationStatus
                ? ['Arrivée', 'Consult.', 'Soins', 'Règlement']
                : ['Arrivée', 'Consult.', 'Règlement'];
            $current = match ($item->locationStatus) {
                'in_waiting_room'      => 0,
                'in_consultation_room' => 1,
                'in_surgery'           => 2,
                default                => \count($steps) - 1,
            };
            $vet   = null === $item->practitionerUserId ? $item->practitionerLabel : ($vets[$item->practitionerUserId] ?? $item->practitionerLabel);
            $total = $totals[$item->admissionId] ?? null;

            $kind = null === $animal
                ? ($item->physicalDescription ?? 'Non identifié')
                : ($animal->breedName ?? self::SPECIES_LABEL[$animal->species] ?? 'Animal');
            $age = null === $animal ? null : PatientFactsReader::age($animal, $now);

            $cards[] = new FluxCard(
                admissionId: $item->admissionId,
                emoji: $this->emoji($animal?->species),
                name: $item->displayLabel,
                subtitle: implode(' · ', array_filter([$kind, $age, $fact?->owner], static fn (?string $part): bool => null !== $part && '' !== $part)),
                isEmergency: $isEmergency,
                chrono: $this->duration($minutes),
                isLong: 0 === $current && $minutes > self::LONG_WAIT_MINUTES,
                steps: $steps,
                currentStep: $current,
                where: match ($item->locationStatus) {
                    'in_waiting_room'      => 'En salle d\'attente',
                    'in_consultation_room' => 'En consultation',
                    'in_surgery'           => 'En soins — ' . mb_strtolower($motif),
                    default                => 'Règlement' . (null === $total ? '' : ' — ' . SheetText::money($total->totalTtcMinorUnits, $total->currency)),
                },
                who: match ($item->locationStatus) {
                    'in_waiting_room' => null === $vet ? 'à attribuer' : $vet . ' dès que libre',
                    'in_consultation_room', 'in_surgery' => $vet ?? 'sans praticien',
                    default => 'À l\'accueil',
                },
                location: $item->locationStatus,
                animalId: $item->knownAnimalId,
                motif: $motif,
                consultationId: $openByPatient[$item->patientId] ?? null,
                facts: $fact,
            );
        }

        return $cards;
    }

    /** The motif as a short chip: the first sentence of a longer note. */
    private function motif(?string $text): string
    {
        $text = trim((string) $text);

        if ('' === $text) {
            return 'Motif à préciser';
        }

        $first = preg_split('/(?<=[.!?])\s/u', $text)[0] ?? $text;

        return rtrim($first, '.');
    }

    /** « Sophie Marie Dupont » reads « Sophie Dupont » on a card. */
    private function shortName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];

        return \count($words) > 2 ? $words[0] . ' ' . $words[\count($words) - 1] : trim($name);
    }

    /**
     * Tasks raised from the data: patients waiting at the desk to pay, stock
     * alerts, consultations left open from a previous day, unpaid invoices.
     *
     * @param list<WaitingRoomItemDto>       $onSite
     * @param array<string, AdmissionTotal>  $totals
     * @param list<StockWatchRow>            $alerts
     * @param list<ConsultationListItemView> $openConsultations
     *
     * @return list<DashboardTask>
     */
    private function tasks(
        string $clinicId,
        array $onSite,
        array $totals,
        array $alerts,
        BillingStats $stats,
        array $openConsultations,
        \DateTimeImmutable $midnightUtc,
        \DateTimeImmutable $now,
    ): array {
        $tasks = [];

        $atDesk = array_values(array_filter(
            $onSite,
            static fn (WaitingRoomItemDto $item): bool => 'at_reception' === $item->locationStatus,
        ));

        foreach ($atDesk as $item) {
            $total   = $totals[$item->admissionId] ?? null;
            $tasks[] = new DashboardTask(
                group: 'urgent',
                icon: 'fact',
                title: 'Encaisser ' . $item->displayLabel,
                subtitle: (null !== $total ? $this->money($total->totalTtcMinorUnits, $total->currency) . ' · ' : '')
                    . 'à l\'accueil depuis ' . $this->duration($this->minutesSince($item->openedAt, $now)),
                audience: 'asv',
                assigneeUserId: null,
                actionLabel: 'Encaisser',
                actionUrl: $this->urls->generate('clinic_checkout', ['admissionId' => $item->admissionId]),
            );
        }

        usort(
            $alerts,
            static fn (StockWatchRow $a, StockWatchRow $b): int => self::severityRank($a->severity)
                <=> self::severityRank($b->severity),
        );

        $names = $this->articleNames($clinicId, array_map(
            static fn (StockWatchRow $alert): string => $alert->articleId,
            \array_slice($alerts, 0, self::TASKS_PER_SOURCE),
        ));

        foreach (\array_slice($alerts, 0, self::TASKS_PER_SOURCE) as $alert) {
            $name    = $names[$alert->articleId] ?? 'article';
            $tasks[] = new DashboardTask(
                group: match ($alert->severity) {
                    StockAlertSeverity::CRITICAL => 'urgent',
                    StockAlertSeverity::WARNING  => 'jour',
                    StockAlertSeverity::INFO     => 'fond',
                },
                icon: 'stock',
                title: match ($alert->kind) {
                    StockWatchKind::EXPIRED_LOT     => 'Lot périmé — ' . $name,
                    StockWatchKind::OUT_OF_STOCK    => 'Rupture — ' . $name,
                    StockWatchKind::BELOW_THRESHOLD => 'Stock bas — ' . $name,
                    StockWatchKind::EXPIRING_LOT    => 'Péremption proche — ' . $name,
                    StockWatchKind::RECALLED_LOT    => 'Lot rappelé — ' . $name,
                },
                subtitle: $this->alertDetail($alert),
                audience: 'clinique',
                assigneeUserId: null,
                actionLabel: match ($alert->kind) {
                    StockWatchKind::OUT_OF_STOCK, StockWatchKind::BELOW_THRESHOLD => 'Commander',
                    StockWatchKind::EXPIRED_LOT => 'Sortir',
                    default                     => 'Voir',
                },
                actionUrl: $this->urls->generate('clinic_stock_alerts'),
            );
        }

        if (\count($alerts) > self::TASKS_PER_SOURCE) {
            $rest    = \count($alerts) - self::TASKS_PER_SOURCE;
            $tasks[] = new DashboardTask(
                group: 'fond',
                icon: 'stock',
                title: SheetText::plural($rest, 'autre alerte de stock', 'autres alertes de stock'),
                subtitle: 'Seuils, ruptures et péremptions à revoir',
                audience: 'clinique',
                assigneeUserId: null,
                actionLabel: 'Voir',
                actionUrl: $this->urls->generate('clinic_stock_alerts'),
            );
        }

        $tasks = [...$tasks, ...$this->openConsultationTasks($clinicId, $openConsultations, $midnightUtc, $now)];

        if ($stats->outstandingCount > 0) {
            $tasks[] = new DashboardTask(
                group: 'fond',
                icon: 'fact',
                title: SheetText::plural($stats->outstandingCount, 'facture impayée', 'factures impayées'),
                subtitle: $this->money($stats->outstandingMinorUnits, $stats->currency) . ' restant dû',
                audience: 'clinique',
                assigneeUserId: null,
                actionLabel: 'Relancer',
                actionUrl: $this->urls->generate('clinic_invoices', ['pay' => 'du', 'per' => 'tout']),
            );
        }

        return $tasks;
    }

    /**
     * Consultations still open from a previous day: their record is unfinished.
     *
     * @param list<ConsultationListItemView> $openConsultations most recent first
     *
     * @return list<DashboardTask>
     */
    private function openConsultationTasks(string $clinicId, array $openConsultations, \DateTimeImmutable $midnightUtc, \DateTimeImmutable $now): array
    {
        $stale = [];

        // Oldest first: the longest forgotten record comes first.
        foreach (array_reverse($openConsultations) as $item) {
            if (new \DateTimeImmutable($item->startedAtUtc) < $midnightUtc) {
                $stale[] = $item;
            }
        }

        $tasks = [];

        foreach (\array_slice($stale, 0, self::TASKS_PER_SOURCE) as $item) {
            $link    = $this->queryBus->ask(new GetPatientAnimalLink($item->patientId, $clinicId));
            $started = new \DateTimeImmutable($item->startedAtUtc)->setTimezone($now->getTimezone());
            $motif   = $item->chiefComplaint ?? ($item->motifs[0] ?? null);

            $tasks[] = new DashboardTask(
                group: 'jour',
                icon: 'cr',
                title: 'Clôturer la consultation de ' . ($link instanceof PatientAnimalLinkDto ? $link->displayLabel : 'patient'),
                subtitle: 'Ouverte le ' . SheetText::date($started, 'd MMM') . (null === $motif || '' === $motif ? '' : ' · ' . $motif),
                audience: 'veto',
                assigneeUserId: $item->practitionerUserId,
                actionLabel: 'Ouvrir',
                actionUrl: $this->urls->generate('clinic_consultation_details', ['id' => $item->consultationId]),
            );
        }

        if (\count($stale) > self::TASKS_PER_SOURCE) {
            $tasks[] = new DashboardTask(
                group: 'fond',
                icon: 'cr',
                title: SheetText::plural(\count($stale) - self::TASKS_PER_SOURCE, 'autre consultation ouverte', 'autres consultations ouvertes'),
                subtitle: 'Comptes rendus des jours précédents à finaliser',
                audience: 'veto',
                assigneeUserId: null,
                actionLabel: 'Voir',
                actionUrl: $this->urls->generate('clinic_consultations', ['statut' => 'aclot', 'per' => 'tout']),
            );
        }

        return $tasks;
    }

    private static function severityRank(StockAlertSeverity $severity): int
    {
        return match ($severity) {
            StockAlertSeverity::CRITICAL => 0,
            StockAlertSeverity::WARNING  => 1,
            StockAlertSeverity::INFO     => 2,
        };
    }

    private function alertDetail(StockWatchRow $alert): string
    {
        if (null !== $alert->lotNumber && null !== $alert->expiryDate) {
            $verb = null !== $alert->days && $alert->days < 0 ? 'périmé le ' : 'périme le ';

            return 'Lot ' . $alert->lotNumber . ' · ' . (StockWatchKind::RECALLED_LOT === $alert->kind
                ? 'retiré de la vente'
                : $verb . SheetText::date(new \DateTimeImmutable($alert->expiryDate), 'd MMM yyyy'));
        }

        $amount = rtrim(rtrim($alert->quantity, '0'), '.');

        return 'Reste ' . ('' === $amount ? '0' : str_replace('.', ',', $amount)) . ' ' . (self::UNITS[$alert->unit] ?? mb_strtolower($alert->unit));
    }

    /**
     * The names of the articles of the stock tasks.
     *
     * @param list<string> $articleIds
     *
     * @return array<string, string>
     */
    private function articleNames(string $clinicId, array $articleIds): array
    {
        if ([] === $articleIds) {
            return [];
        }

        $page = $this->queryBus->ask(new ListArticles($clinicId, new CatalogListCriteria(status: null, articleIds: array_values(array_unique($articleIds)), perPage: 100)));
        $out  = [];

        foreach ($page instanceof CatalogPage ? $page->items : [] as $article) {
            if ($article instanceof ArticleListRow) {
                $out[$article->id] = $article->name;
            }
        }

        return $out;
    }

    /**
     * What has already been dealt with today.
     *
     * @param list<WaitingRoomItemDto> $closedToday
     * @param list<AppointmentItem>    $appointments
     *
     * @return list<DoneItem>
     */
    private function done(
        string $clinicId,
        array $closedToday,
        array $appointments,
        \DateTimeImmutable $midnightUtc,
        \DateTimeZone $timeZone,
    ): array {
        $done = [];

        $completed = array_values(array_filter(
            $appointments,
            static fn (AppointmentItem $item): bool => 'COMPLETED' === $item->status,
        ));

        if ([] !== $completed) {
            $last   = $completed[\count($completed) - 1];
            $done[] = new DoneItem(
                '📅',
                SheetText::plural(\count($completed), 'rendez-vous honoré', 'rendez-vous honorés'),
                $this->localStart($last, $timeZone)->format('H:i'),
            );
        }

        $result = $this->queryBus->ask(new SearchConsultations($clinicId, startedAfterUtc: $midnightUtc, statuses: ['CLOSED'], limit: 100));
        \assert(\is_array($result) && \is_array($result['items'] ?? null));

        $closedAt = [];

        foreach ($result['items'] as $item) {
            \assert($item instanceof ConsultationListItemView);

            if (null !== $item->closedAtUtc) {
                $closedAt[] = $item->closedAtUtc;
            }
        }

        if ([] !== $closedAt) {
            $done[] = new DoneItem(
                '🩺',
                SheetText::plural(\count($closedAt), 'consultation clôturée', 'consultations clôturées'),
                new \DateTimeImmutable(max($closedAt))->setTimezone($timeZone)->format('H:i'),
            );
        }

        if ([] !== $closedToday) {
            $latest = $closedToday[0]->closedAt;
            $done[] = new DoneItem(
                '🏠',
                SheetText::plural(\count($closedToday), 'patient sorti', 'patients sortis'),
                null === $latest ? null : new \DateTimeImmutable($latest)->setTimezone($timeZone)->format('H:i'),
            );
        }

        return $done;
    }

    /**
     * Which task audiences « Pour moi » keeps for the member.
     *
     * @return list<string>
     */
    private function audiences(string $clinicId, ?string $userId): array
    {
        $membership = null === $userId ? null : $this->queryBus->ask(new GetUserMembershipInClinic($userId, $clinicId));

        if (!$membership instanceof MembershipDetails) {
            return ['clinique'];
        }

        return match ($membership->role) {
            ClinicMemberRole::VETERINARY => ['clinique', 'veto'],
            ClinicMemberRole::VETERINARY_ASSISTANT, ClinicMemberRole::RECEPTIONIST => ['clinique', 'asv'],
            ClinicMemberRole::MANAGER => ['clinique', 'asv', 'veto'],
        };
    }

    private function localStart(AppointmentItem $item, \DateTimeZone $timeZone): \DateTimeImmutable
    {
        return new \DateTimeImmutable($item->startsAtUtc, new \DateTimeZone('UTC'))->setTimezone($timeZone);
    }

    private function minutesSince(string $instant, \DateTimeImmutable $now): int
    {
        return max(0, intdiv($now->getTimestamp() - new \DateTimeImmutable($instant)->getTimestamp(), 60));
    }

    private function minutes(string $clock): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $clock) + [1 => '0']);

        return $hours * 60 + $minutes;
    }

    private function dayMinutes(\DateTimeImmutable $instant): int
    {
        return (int) $instant->format('G') * 60 + (int) $instant->format('i');
    }

    private function clockLabel(int $minutes): string
    {
        return \sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * « 12 min », « 1 h 05 », « 3 j ».
     */
    private function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        if ($minutes < 24 * 60) {
            return \sprintf('%d h %02d', intdiv($minutes, 60), $minutes % 60);
        }

        return intdiv($minutes, 24 * 60) . ' j';
    }

    private function emoji(?string $species): string
    {
        return self::SPECIES_EMOJI[$species ?? ''] ?? '🐾';
    }

    /**
     * Whole amounts without cents, as the mock-up's « 248 € ».
     */
    private function money(int $minorUnits, string $currency): string
    {
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY);

        if (0 === $minorUnits % 100) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
        }

        return $formatter->formatCurrency($minorUnits / 100, $currency);
    }
}
