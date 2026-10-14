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

        $hospitalised = array_values(array_filter(
            $active,
            static fn (WaitingRoomItemDto $item): bool => 'in_hospitalization' === $item->locationStatus,
        ));
        $onSite = array_values(array_filter(
            $active,
            static fn (WaitingRoomItemDto $item): bool => 'in_hospitalization' !== $item->locationStatus,
        ));

        $animals = $this->animals($clinicId, $active);
        $opening = null === $settings ? null : $this->openingToday($settings, $now);

        $practitioners = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($practitioners));

        $stats = $this->queryBus->ask(new GetBillingStats($clinicId, $midnight->setTimezone($utc), $now->setTimezone($utc)));
        \assert($stats instanceof BillingStats);

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

        $isCalm = [] === $onSite && \count($upcoming) <= 2;

        $timeline = $this->timeline($appointments, $timeZone);

        return new DashboardView(
            timeZone: $timeZone->getName(),
            nowLabel: $now->format('H:i'),
            kpis: $this->kpis(
                $stats,
                $appointments,
                $opening,
                \count($practitioners),
                $onSite,
                $hospitalised,
                $active,
                $closedToday,
                $now,
                $midnight,
                $isCalm,
            ),
            beds: array_map(fn (WaitingRoomItemDto $item): HospitalBed => $this->bed($item, $animals, $now), $hospitalised),
            timeline: $timeline,
            appointmentCount: \count($appointments),
            rest: $this->rest($appointments, $opening, $now, $timeZone),
            flux: $this->flux($onSite, $animals, $now),
            nextExpected: $this->nextExpected($upcoming, $timeZone),
            tasks: $this->tasks($clinicId, $onSite, $alerts, $stats, $midnight->setTimezone($utc), $now),
            done: $this->done($clinicId, $closedToday, $appointments, $midnight->setTimezone($utc), $timeZone),
            isCalm: $isCalm,
            hasStockAlerts: [] !== $alerts,
            currentUserId: $userId,
            myAudiences: $this->audiences($clinicId, $userId),
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
     * The identified animals among the admissions, by animal id.
     *
     * @param list<WaitingRoomItemDto> $admissions
     *
     * @return array<string, AnimalView>
     */
    private function animals(string $clinicId, array $admissions): array
    {
        $animals = [];

        foreach ($admissions as $admission) {
            $animalId = $admission->knownAnimalId;

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
     * @param list<AppointmentItem>      $appointments
     * @param list<array{int, int}>|null $opening
     * @param list<WaitingRoomItemDto>   $onSite
     * @param list<WaitingRoomItemDto>   $hospitalised
     * @param list<WaitingRoomItemDto>   $active
     * @param list<WaitingRoomItemDto>   $closedToday
     *
     * @return list<DashboardKpi>
     */
    private function kpis(
        BillingStats $stats,
        array $appointments,
        ?array $opening,
        int $practitionerCount,
        array $onSite,
        array $hospitalised,
        array $active,
        array $closedToday,
        \DateTimeImmutable $now,
        \DateTimeImmutable $midnight,
        bool $isCalm,
    ): array {
        $kpis = [new DashboardKpi(
            value: $this->money($stats->collectedMinorUnits, $stats->currency),
            label: 'encaissés',
            note: $isCalm ? 'journée calme' : null,
        )];

        $booked = \count($appointments);

        if (null !== $opening && [] !== $opening) {
            $openMinutes = array_sum(array_map(static fn (array $i): int => max(0, $i[1] - $i[0]), $opening));
            $capacity    = intdiv($openMinutes, self::SLOT_MINUTES) * max(1, $practitionerCount);

            $note = null;
            $tone = '';

            if ($booked >= $capacity && $capacity > 0) {
                $note = 'complet';
                $tone = 'warn';
            } elseif ($this->afternoonIsFree($appointments, $opening, $now)) {
                $note = 'après-midi libre';
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
            $onSite,
            static fn (WaitingRoomItemDto $item): bool => 'in_waiting_room' === $item->locationStatus,
        ));
        $average = [] === $waiting ? 0 : intdiv(
            array_sum(array_map(fn (WaitingRoomItemDto $item): int => $this->minutesSince($item->openedAt, $now), $waiting)),
            \count($waiting),
        );
        $kpis[] = new DashboardKpi(
            $this->duration($average),
            'attente moyenne',
            $average > self::LONG_WAIT_MINUTES ? SheetText::plural(\count($waiting), 'en salle', 'en salle') : null,
            $average > self::LONG_WAIT_MINUTES ? 'warn' : '',
        );

        $kpis[] = new DashboardKpi((string) \count($hospitalised), \count($hospitalised) > 1 ? 'hospitalisés' : 'hospitalisé', id: 'kpiHospit');

        $walkIns = 0;

        foreach ([...$active, ...$closedToday] as $item) {
            if ('appointment_booked' !== $item->intakeChannel && new \DateTimeImmutable($item->openedAt) >= $midnight) {
                ++$walkIns;
            }
        }

        $kpis[] = new DashboardKpi((string) $walkIns, 'sans rendez-vous');

        return $kpis;
    }

    /**
     * True when the clinic opens in the afternoon and nothing is booked then.
     *
     * @param list<AppointmentItem> $appointments
     * @param list<array{int, int}> $opening
     */
    private function afternoonIsFree(array $appointments, array $opening, \DateTimeImmutable $now): bool
    {
        $noon = 13 * 60;

        if ([] === array_filter($opening, static fn (array $i): bool => $i[1] > $noon)) {
            return false;
        }

        foreach ($appointments as $appointment) {
            if ($this->dayMinutes($this->localStart($appointment, $now->getTimezone())) >= $noon) {
                return false;
            }
        }

        return true;
    }

    /**
     * Appointments grouped by start time, with the free gaps between them.
     *
     * @param list<AppointmentItem> $appointments
     *
     * @return list<TimelineSlot>
     */
    private function timeline(array $appointments, \DateTimeZone $timeZone): array
    {
        /** @var array<string, list<AppointmentItem>> $byTime */
        $byTime = [];

        foreach ($appointments as $appointment) {
            $byTime[$this->localStart($appointment, $timeZone)->format('H:i')][] = $appointment;
        }

        $slots   = [];
        $lastEnd = null;

        foreach ($byTime as $time => $group) {
            $start = $this->minutes((string) $time);

            if (null !== $lastEnd && $start - $lastEnd >= self::FREE_GAP_MINUTES) {
                $slots[] = new TimelineSlot($this->clockLabel($lastEnd), $lastEnd, $start, []);
            }

            $end = $start;

            foreach ($group as $item) {
                $end = max($end, $start + $item->durationMinutes);
            }

            $slots[] = new TimelineSlot(
                (string) $time,
                $start,
                $end,
                array_map(fn (AppointmentItem $item): TimelineAppointment => $this->timelineAppointment($item), $group),
            );

            $lastEnd = max($lastEnd ?? 0, $end);
        }

        return $slots;
    }

    private function timelineAppointment(AppointmentItem $item): TimelineAppointment
    {
        $state = match (true) {
            'COMPLETED' === $item->status     => 'done',
            'NO_SHOW' === $item->status       => 'noshow',
            null !== $item->linkedAdmissionId => 'onsite',
            default                           => 'planned',
        };

        $name  = $item->animalLabel ?? 'Créneau sans patient';
        $title = null !== $item->animalBreed && '' !== $item->animalBreed ? $name . ' · ' . $item->animalBreed : $name;
        $parts = array_filter(
            [$item->reason, $item->ownerLabel],
            static fn (?string $part): bool => null !== $part && '' !== $part,
        );

        return new TimelineAppointment(
            id: $item->id,
            emoji: $this->emoji($item->animalSpecies),
            title: $title,
            subtitle: [] === $parts ? 'Sans motif' : implode(' — ', $parts),
            durationLabel: match ($state) {
                'done'   => 'terminé',
                'noshow' => 'absent',
                'onsite' => 'sur place',
                default  => $item->durationMinutes . ' min',
            },
            state: $state,
            animalId: $item->animalId,
            animalName: $item->animalLabel,
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
     * Patients on site, emergencies first, then by arrival.
     *
     * @param list<WaitingRoomItemDto>  $onSite
     * @param array<string, AnimalView> $animals
     *
     * @return list<FluxCard>
     */
    private function flux(array $onSite, array $animals, \DateTimeImmutable $now): array
    {
        usort($onSite, static function (WaitingRoomItemDto $a, WaitingRoomItemDto $b): int {
            $urgent = \in_array($b->triageLevel, self::EMERGENCY_TRIAGE, true) <=> \in_array($a->triageLevel, self::EMERGENCY_TRIAGE, true);

            return 0 !== $urgent ? $urgent : $a->openedAt <=> $b->openedAt;
        });

        $cards = [];

        foreach ($onSite as $item) {
            $animal  = null === $item->knownAnimalId ? null : ($animals[$item->knownAnimalId] ?? null);
            $minutes = $this->minutesSince($item->openedAt, $now);
            $steps   = ['Accueil', 'Attente', 'in_surgery' === $item->locationStatus ? 'Bloc' : 'Consultation', 'Sortie'];
            $current = match ($item->locationStatus) {
                'in_consultation_room', 'in_surgery' => 2,
                'at_reception' => 3,
                default        => 1,
            };

            $cards[] = new FluxCard(
                admissionId: $item->admissionId,
                emoji: $this->emoji($animal?->species),
                name: $item->displayLabel,
                subtitle: $this->subtitle($item, $animal),
                isEmergency: \in_array($item->triageLevel, self::EMERGENCY_TRIAGE, true),
                chrono: $this->duration($minutes),
                isLong: 1 === $current && $minutes > self::LONG_WAIT_MINUTES,
                steps: $steps,
                currentStep: $current,
                where: match ($item->locationStatus) {
                    'in_consultation_room' => 'En consultation',
                    'in_surgery'           => 'Au bloc',
                    'at_reception'         => 'À l\'accueil',
                    default                => 'Salle d\'attente',
                },
                who: $item->practitionerLabel ?? ('in_waiting_room' === $item->locationStatus ? 'à attribuer' : 'sans praticien'),
            );
        }

        return $cards;
    }

    /**
     * @param array<string, AnimalView> $animals
     */
    private function bed(WaitingRoomItemDto $item, array $animals, \DateTimeImmutable $now): HospitalBed
    {
        $animal = null === $item->knownAnimalId ? null : ($animals[$item->knownAnimalId] ?? null);
        $opened = new \DateTimeImmutable($item->openedAt)->setTimezone($now->getTimezone());
        $days   = (int) $opened->setTime(0, 0)->diff($now->setTime(0, 0))->days;

        return new HospitalBed(
            admissionId: $item->admissionId,
            emoji: $this->emoji($animal?->species),
            name: $item->displayLabel,
            subtitle: $this->subtitle($item, $animal),
            stayLabel: 'J' . ($days + 1),
            admittedLabel: 0 === $days
                ? 'aujourd\'hui ' . $opened->format('H:i')
                : SheetText::date($opened, 'd MMM') . ' ' . $opened->format('H:i'),
            sinceLabel: $this->duration($this->minutesSince($item->openedAt, $now)),
            practitioner: $item->practitionerLabel ?? 'sans praticien',
            animalId: $item->knownAnimalId,
        );
    }

    private function subtitle(WaitingRoomItemDto $item, ?AnimalView $animal): string
    {
        $kind = null === $animal
            ? ($item->physicalDescription ?? 'Non identifié')
            : ($animal->breedName ?? self::SPECIES_LABEL[$animal->species] ?? 'Animal');
        $motif = $item->appointmentReason ?? $item->triageNotes;

        return null === $motif || '' === $motif ? $kind : $kind . ' · ' . $motif;
    }

    /**
     * Tasks raised from the data: patients waiting at the desk to pay, stock
     * alerts, consultations left open from a previous day, unpaid invoices.
     *
     * @param list<WaitingRoomItemDto> $onSite
     * @param list<StockWatchRow>      $alerts
     *
     * @return list<DashboardTask>
     */
    private function tasks(
        string $clinicId,
        array $onSite,
        array $alerts,
        BillingStats $stats,
        \DateTimeImmutable $midnightUtc,
        \DateTimeImmutable $now,
    ): array {
        $tasks = [];

        $atDesk = array_values(array_filter(
            $onSite,
            static fn (WaitingRoomItemDto $item): bool => 'at_reception' === $item->locationStatus,
        ));

        $totals = [] === $atDesk ? [] : $this->queryBus->ask(new GetConsultationTotalsForAdmissions(
            $clinicId,
            array_map(static fn (WaitingRoomItemDto $item): string => $item->admissionId, $atDesk),
        ));
        \assert(\is_array($totals));

        foreach ($atDesk as $item) {
            $total   = $totals[$item->admissionId] ?? null;
            $tasks[] = new DashboardTask(
                group: 'urgent',
                icon: 'fact',
                title: 'Encaisser ' . $item->displayLabel,
                subtitle: ($total instanceof AdmissionTotal ? $this->money($total->totalTtcMinorUnits, $total->currency) . ' · ' : '')
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

        $tasks = [...$tasks, ...$this->openConsultationTasks($clinicId, $midnightUtc, $now)];

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
     * @return list<DashboardTask>
     */
    private function openConsultationTasks(string $clinicId, \DateTimeImmutable $midnightUtc, \DateTimeImmutable $now): array
    {
        $result = $this->queryBus->ask(new SearchConsultations($clinicId, statuses: ['OPEN'], sort: 'datetime', direction: 'asc', limit: 100));
        \assert(\is_array($result) && \is_array($result['items'] ?? null));

        $stale = [];

        foreach ($result['items'] as $item) {
            \assert($item instanceof ConsultationListItemView);

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
                actionUrl: $this->urls->generate('clinic_consultations', ['status' => ['OPEN']]),
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
