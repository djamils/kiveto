<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Flux;

use App\Context\Admission\Application\Port\ArrivalCheckInView;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Admission\Application\Query\ListActiveAdmissions\ListActiveAdmissions;
use App\Context\Admission\Application\Query\ListAdmissionsClosedSince\ListAdmissionsClosedSince;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\ListAnimalWeightRecords;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimal;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Billing\Application\Query\ListOutstandingBalances\ListOutstandingBalances;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListItemView;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoices;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions\AdmissionTotal;
use App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions\GetConsultationTotalsForAdmissions;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListItemView;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultations;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Presentation\Clinic\ViewModel\Dashboard\PatientFactsReader;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;

/**
 * Builds the ambulatory flow board (« Flux ambulatoire ») from the contexts
 * that own each piece: Scheduling (today's bookings not arrived yet, the
 * no-shows), Admission (who is on site, where, since when, who brought an
 * emergency in, what the desk noted at arrival, who left today), Animal and
 * Client (species, breed, age, alerts, last weighing, owner and phone),
 * Consultation (the open consultation of a patient taken in charge, what a
 * patient at the desk owes, the report of a visit over), Billing (what a
 * departure paid, what a booked owner still owes) and Clinic (time zone,
 * practitioners).
 */
final readonly class FluxBoardViewBuilder
{
    /** A patient waiting longer than this gets the red chrono. */
    private const int LONG_WAIT_MINUTES = 15;

    private const array EMERGENCY_TRIAGE = ['emergency', 'vital_emergency'];

    private const array SPECIES_EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇'];

    /** What the desk picks for an unidentified emergency (« Espèce »). */
    private const array OBSERVED_EMOJI = ['Chien' => '🐕', 'Chat' => '🐈', 'Lapin' => '🐇', 'Oiseau' => '🐦'];

    private const array CHANNEL_LABEL = [
        'walk_in'                    => 'Sans RDV',
        'emergency'                  => 'Urgence',
        'emergency_by_third_party'   => 'Urgence · tiers',
        'emergency_by_authority'     => 'Urgence · secours',
        'emergency_by_association'   => 'Urgence · association',
        'emergency_by_municipality'  => 'Urgence · mairie',
        'transfer_from_other_clinic' => 'Transfert',
    ];

    /** An arrival cancelled at the desk is no passage. */
    private const string CANCELLED_ARRIVAL = 'administrative_error';

    public function __construct(
        private QueryBusInterface $queryBus,
        private ClockInterface $clock,
    ) {
    }

    public function build(string $clinicId): FluxBoardView
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));
        $timeZone = new \DateTimeZone($settings instanceof ClinicSettingsView ? $settings->timeZone : 'Europe/Paris');
        $now      = $this->clock->now()->setTimezone($timeZone);
        $midnight = $now->setTime(0, 0)->setTimezone(new \DateTimeZone('UTC'));

        $active = $this->admissions(new ListActiveAdmissions($clinicId));
        $closed = array_values(array_filter(
            $this->admissions(new ListAdmissionsClosedSince($clinicId, $midnight)),
            static fn (WaitingRoomItemDto $item): bool => self::CANCELLED_ARRIVAL !== $item->closureReason,
        ));

        [$appointments, $noShows] = $this->todaysBookings($clinicId, $now, $timeZone);
        $vets                     = $this->veterinarians($clinicId);
        $consultation             = $this->openConsultationsByPatient($clinicId);
        $totals                   = $this->totals($clinicId, $active);

        $animalIds = array_merge(
            array_map(static fn (WaitingRoomItemDto $i): ?string => $i->knownAnimalId, $active),
            array_map(static fn (AppointmentItem $a): ?string => $a->animalId, $appointments),
            array_map(static fn (WaitingRoomItemDto $i): ?string => $i->knownAnimalId, $closed),
        );
        $animals = $this->animals($clinicId, $animalIds);
        $owners  = $this->owners($clinicId, $animals);
        $alerts  = $this->alerts($clinicId, $animals);

        $urgent   = [];
        $priority = [];
        $standard = [];
        $inCharge = [];
        $checkout = [];
        $busy     = [];

        foreach ($active as $item) {
            $open = $consultation[$item->patientId] ?? null;

            if (null !== $open) {
                $busy[$open->practitionerUserId] = true;
            }

            $card = $this->admissionCard($item, $open, $animals, $owners, $alerts, $totals, $vets, $now);

            if ('charge' === $card->column) {
                $inCharge[] = $card;
            } elseif ('sortie' === $card->column) {
                $checkout[] = $card;
            } elseif ($card->isEmergency) {
                $urgent[] = $card;
            } elseif ($card->isPriority) {
                $priority[] = $card;
            } else {
                $standard[] = $card;
            }
        }

        $bySince = static fn (FluxPatient $a, FluxPatient $b): int => $a->sinceEpoch <=> $b->sinceEpoch;
        // As in the waiting room, an emergency stays ahead in the other columns.
        $byUrgency = static fn (FluxPatient $a, FluxPatient $b): int => [$b->isEmergency, $b->isPriority, $a->sinceEpoch] <=> [$a->isEmergency, $a->isPriority, $b->sinceEpoch];
        usort($urgent, $bySince);
        usort($priority, $bySince);
        usort($standard, $bySince);
        usort($inCharge, $byUrgency);
        usort($checkout, $byUrgency);

        $waiting = [...$urgent, ...$priority, ...$standard];
        $average = [] === $waiting ? 0 : (int) round(array_sum(array_map(
            static fn (FluxPatient $p): int => intdiv(max(0, $now->getTimestamp() - $p->sinceEpoch), 60),
            $waiting,
        )) / \count($waiting));

        $desk               = $this->desk($clinicId, $appointments, $now);
        [$slots, $byAnimal] = $this->planned($appointments, $animals, $alerts, $vets, $desk, $timeZone);

        $finished = $this->finished($clinicId, $closed, $animals, $vets, $midnight, $timeZone);
        $passages = [...$finished, ...$this->noShows($noShows, $vets, $timeZone)];
        usort($passages, static fn (FluxPassage $a, FluxPassage $b): int => $a->sortKey() <=> $b->sortKey());

        return new FluxBoardView(
            timeZone: $timeZone->getName(),
            dateLabel: SheetText::date($now, 'EEEE d MMMM'),
            clockLabel: $now->format('H:i:s'),
            nowEpoch: $now->getTimestamp(),
            planned: $slots,
            plannedCount: \count($appointments),
            urgent: $urgent,
            priority: $priority,
            standard: $standard,
            inCharge: $inCharge,
            checkout: $checkout,
            passages: $passages,
            finishedCount: \count($finished),
            noShowCount: \count($noShows),
            onSiteCount: \count($active),
            averageWaitMinutes: $average,
            freePractitioners: max(0, \count($vets) - \count(array_intersect_key($busy, $vets))),
            appointmentsByAnimal: $byAnimal,
        );
    }

    /** A surgery or an anaesthesia: the arrival window asks whether the patient fasted. */
    public static function isSurgical(string $motif): bool
    {
        return 1 === preg_match('/\bAG\b/u', $motif)
            || 1 === preg_match('/anesth|chirurg|détartrage|stérilis|castration/iu', $motif);
    }

    /**
     * @param array<string, AnimalView>     $animals
     * @param array<string, ClientView>     $owners  by animal id
     * @param array<string, list<string>>   $alerts  by animal id
     * @param array<string, AdmissionTotal> $totals  by admission id
     * @param array<string, string>         $vets    display name by user id
     */
    private function admissionCard(
        WaitingRoomItemDto $item,
        ?ConsultationListItemView $open,
        array $animals,
        array $owners,
        array $alerts,
        array $totals,
        array $vets,
        \DateTimeImmutable $now,
    ): FluxPatient {
        $animalId = $item->knownAnimalId;
        $animal   = null === $animalId ? null : ($animals[$animalId] ?? null);
        $owner    = null === $animalId ? null : ($owners[$animalId] ?? null);
        $since    = new \DateTimeImmutable($item->locationSince ?? $item->openedAt);
        $opened   = new \DateTimeImmutable($item->openedAt)->setTimezone($now->getTimezone())->format('H:i');
        $minutes  = intdiv(max(0, $now->getTimestamp() - $since->getTimestamp()), 60);
        $column   = match ($item->locationStatus) {
            'in_waiting_room' => 'attente',
            'at_reception'    => 'sortie',
            default           => 'charge',
        };
        $total = $totals[$item->admissionId] ?? null;

        $practitioner = $item->practitionerLabel;
        if (null !== $item->practitionerUserId && isset($vets[$item->practitionerUserId])) {
            $practitioner = $vets[$item->practitionerUserId];
        }
        if (null !== $open && isset($vets[$open->practitionerUserId])) {
            $practitioner = $vets[$open->practitionerUserId];
        }

        $intake = null === $animalId ? $this->intake($item, $opened) : null;

        if (null !== $intake) {
            // The mock-up's « Inconnu »: species as the desk saw it, admission time.
            $breed      = $intake->species ?? 'Espèce à préciser';
            $breedAndAg = $breed . ' · admis ' . $opened;
            $ownerName  = 'proprio' === $intake->broughtBy ? 'Propriétaire présent' : 'Propriétaire inconnu';
        } else {
            $breed      = $this->breed($animal);
            $breedAndAg = $this->breedAndAge($animal);
            $ownerName  = null !== $owner ? $owner->fullName() : '';
        }

        return new FluxPatient(
            key: 'a-' . $item->admissionId,
            column: $column,
            admissionId: $item->admissionId,
            appointmentId: $item->appointmentId,
            animalId: $animalId,
            consultationId: $open?->consultationId,
            emoji: null !== $intake ? (self::OBSERVED_EMOJI[$intake->species ?? ''] ?? '🐾') : $this->emoji($animal?->species),
            name: null !== $intake ? 'Inconnu' : $item->displayLabel,
            breed: $breed,
            breedAndAge: $breedAndAg,
            owner: $ownerName,
            ownerShort: $this->shortName($ownerName),
            phone: $this->phone($owner),
            motif: $this->motif($item->triageNotes ?? $item->appointmentReason),
            practitioner: $practitioner,
            sinceEpoch: $since->getTimestamp(),
            sinceLabel: $opened,
            channel: 'appointment_booked' === $item->intakeChannel
                ? 'RDV'
                : (self::CHANNEL_LABEL[$item->intakeChannel] ?? 'Sans RDV'),
            triageLevel: $item->triageLevel,
            isEmergency: \in_array($item->triageLevel, self::EMERGENCY_TRIAGE, true),
            isPriority: 'priority' === $item->triageLevel,
            isUnidentified: null !== $intake,
            isLong: 'attente' === $column && $minutes > self::LONG_WAIT_MINUTES,
            waitLabel: $this->duration($minutes),
            location: $item->locationStatus,
            alerts: null === $animalId ? [] : ($alerts[$animalId] ?? []),
            amountLabel: null === $total ? null : SheetText::money($total->totalTtcMinorUnits, $total->currency),
            slotLabel: null,
            arrivalChips: $this->arrivalChips($item->arrivalCheckIn),
            intake: $intake,
            ownerId: $owner?->id,
        );
    }

    private function intake(WaitingRoomItemDto $item, string $opened): FluxIntake
    {
        $broughtBy = match (true) {
            'third_party' === $item->presenterRole,
            'emergency_by_third_party' === $item->intakeChannel => 'tiers',
            'authority' === $item->presenterRole,
            'emergency_by_authority' === $item->intakeChannel => 'secours',
            default                                           => 'proprio',
        };

        return new FluxIntake(
            time: $opened,
            broughtBy: $broughtBy,
            declarant: $item->presenterName,
            declarantPhone: null === $item->presenterPhone ? null : PatientFactsReader::formatPhone($item->presenterPhone),
            scannedMicrochip: $item->scannedMicrochip,
            species: null === $item->observedSpecies || '' === trim($item->observedSpecies) ? null : trim($item->observedSpecies),
        );
    }

    /**
     * « ⚖ 4,2 kg » and « 📝 à jeun · nouveau symptôme · boite depuis hier ».
     *
     * @return list<string>
     */
    private function arrivalChips(?ArrivalCheckInView $checkIn): array
    {
        if (null === $checkIn) {
            return [];
        }

        $chips = [];

        if (null !== $checkIn->weightGrams) {
            $chips[] = '⚖ ' . PatientFactsReader::kilograms($checkIn->weightGrams) . ' kg';
        }

        $notes = array_keys(array_filter([
            'à jeun'              => $checkIn->fasting,
            'nouveau symptôme'    => $checkIn->newSymptom,
            'documents apportés'  => $checkIn->documentsBrought,
            'traitement en cours' => $checkIn->ongoingTreatment,
        ]));

        if (null !== $checkIn->note) {
            $notes[] = $checkIn->note;
        }

        if ([] !== $notes) {
            $chips[] = '📝 ' . implode(' · ', $notes);
        }

        return $chips;
    }

    /**
     * The « Planifié » column grouped by start time, and today's booking of
     * each animal so the check-in modal can offer to honour it.
     *
     * @param list<AppointmentItem>                                                                                $appointments
     * @param array<string, AnimalView>                                                                            $animals
     * @param array<string, list<string>>                                                                          $alerts
     * @param array<string, string>                                                                                $vets         display name by user id
     * @param array{weights: array<string, string>, balances: array<string, int>, invoices: array<string, string>} $desk
     *
     * @return array{list<FluxSlot>, array<string, array{appointmentId: string, time: string, motif: string}>}
     */
    private function planned(
        array $appointments,
        array $animals,
        array $alerts,
        array $vets,
        array $desk,
        \DateTimeZone $timeZone,
    ): array {
        $bySlot   = [];
        $byAnimal = [];

        foreach ($appointments as $appointment) {
            $start   = new \DateTimeImmutable($appointment->startsAtUtc)->setTimezone($timeZone);
            $time    = $start->format('H:i');
            $animal  = null === $appointment->animalId ? null : ($animals[$appointment->animalId] ?? null);
            $owner   = $appointment->ownerLabel ?? '';
            $motif   = $this->motif($appointment->reason);
            $ownerId = $appointment->ownerId;
            $owed    = null === $ownerId ? 0 : ($desk['balances'][$ownerId] ?? 0);

            $bySlot[$time][] = new FluxPatient(
                key: 'r-' . $appointment->id,
                column: 'planifie',
                admissionId: null,
                appointmentId: $appointment->id,
                animalId: $appointment->animalId,
                consultationId: null,
                emoji: $this->emoji($animal->species ?? $appointment->animalSpecies),
                name: $appointment->animalLabel ?? 'Patient à préciser',
                breed: $this->breed($animal),
                breedAndAge: $this->breedAndAge($animal),
                owner: $owner,
                ownerShort: $this->shortName($owner),
                phone: null === $appointment->ownerPhone ? null : PatientFactsReader::formatPhone($appointment->ownerPhone),
                motif: $motif,
                practitioner: $vets[$appointment->practitionerUserId] ?? $appointment->practitionerLabel,
                sinceEpoch: $start->getTimestamp(),
                sinceLabel: $time,
                channel: 'RDV ' . $time,
                triageLevel: 'standard',
                isEmergency: false,
                isPriority: false,
                isUnidentified: false,
                isLong: false,
                waitLabel: '',
                location: 'planned',
                alerts: null === $appointment->animalId ? [] : ($alerts[$appointment->animalId] ?? []),
                amountLabel: null,
                slotLabel: $time,
                ownerId: $ownerId,
                lastWeight: null === $appointment->animalId ? null : ($desk['weights'][$appointment->animalId] ?? null),
                isSurgical: self::isSurgical($appointment->reason ?? ''),
                balanceLabel: $owed > 0 ? SheetText::money($owed, 'EUR') : null,
                settleInvoiceId: null === $ownerId ? null : ($desk['invoices'][$ownerId] ?? null),
            );

            if (null !== $appointment->animalId && !isset($byAnimal[$appointment->animalId])) {
                $byAnimal[$appointment->animalId] = [
                    'appointmentId' => $appointment->id,
                    'time'          => $time,
                    'motif'         => $motif,
                ];
            }
        }

        ksort($bySlot);

        $slots = [];
        foreach ($bySlot as $time => $patients) {
            $slots[] = new FluxSlot((string) $time, $patients);
        }

        return [$slots, $byAnimal];
    }

    /**
     * What the arrival window of a booking recalls: the animal's last
     * weighing, what its owner still owes and the one invoice to settle.
     *
     * @param list<AppointmentItem> $appointments
     *
     * @return array{weights: array<string, string>, balances: array<string, int>, invoices: array<string, string>}
     */
    private function desk(string $clinicId, array $appointments, \DateTimeImmutable $now): array
    {
        $weights = [];

        foreach ($appointments as $appointment) {
            $animalId = $appointment->animalId;

            if (null === $animalId || isset($weights[$animalId])) {
                continue;
            }

            $records = $this->queryBus->ask(new ListAnimalWeightRecords($clinicId, $animalId));
            \assert(\is_array($records));

            $last = PatientFactsReader::lastWeight(array_values(array_filter(
                $records,
                static fn (mixed $record): bool => $record instanceof WeightRecordView,
            )), $now);

            if (null !== $last) {
                $weights[$animalId] = $last;
            }
        }

        $owners = array_filter(array_map(static fn (AppointmentItem $a): ?string => $a->ownerId, $appointments));

        if ([] === $owners) {
            return ['weights' => $weights, 'balances' => [], 'invoices' => []];
        }

        $all = $this->queryBus->ask(new ListOutstandingBalances($clinicId));
        \assert(\is_array($all));

        $balances = [];
        $invoices = [];

        foreach (array_unique($owners) as $ownerId) {
            $owed = $all[$ownerId] ?? 0;

            if (!\is_int($owed) || $owed <= 0) {
                continue;
            }

            $balances[$ownerId] = $owed;
            $due                = $this->queryBus->ask(new SearchInvoices($clinicId, new SearchInvoicesCriteria(
                statuses: ['ISSUED', 'PARTIALLY_PAID'],
                payerClientId: $ownerId,
                perPage: 2,
            )));
            \assert($due instanceof InvoiceListItemView);

            // One invoice: « Encaisser maintenant » opens it; several: the register, filtered.
            if (1 === \count($due->items)) {
                $invoices[$ownerId] = $due->items[0]->id;
            }
        }

        return ['weights' => $weights, 'balances' => $balances, 'invoices' => $invoices];
    }

    /**
     * Today's passages: seen and gone, with what they paid and their report.
     *
     * @param list<WaitingRoomItemDto>  $closed
     * @param array<string, AnimalView> $animals
     * @param array<string, string>     $vets
     *
     * @return list<FluxPassage>
     */
    private function finished(
        string $clinicId,
        array $closed,
        array $animals,
        array $vets,
        \DateTimeImmutable $midnight,
        \DateTimeZone $timeZone,
    ): array {
        if ([] === $closed) {
            return [];
        }

        $paid = $this->queryBus->ask(new SearchInvoices($clinicId, new SearchInvoicesCriteria(
            perPage: 200,
            admissionIds: array_map(static fn (WaitingRoomItemDto $i): string => $i->admissionId, $closed),
        )));
        \assert($paid instanceof InvoiceListItemView);

        $amounts = [];
        foreach ($paid->items as $row) {
            if (null !== $row->admissionId && 'CANCELLED' !== $row->status) {
                $amounts[$row->admissionId] = SheetText::money($row->totalGrossMinorUnits, $row->currency);
            }
        }

        $reports = $this->queryBus->ask(new SearchConsultations(
            $clinicId,
            startedAfterUtc: $midnight,
            statuses: ['CLOSED'],
            sort: 'datetime',
            direction: 'desc',
            limit: 100,
        ));
        \assert(\is_array($reports) && \is_array($reports['items'] ?? null));

        $reportByPatient = [];
        foreach ($reports['items'] as $report) {
            \assert($report instanceof ConsultationListItemView);
            $reportByPatient[$report->patientId] ??= $report;
        }

        $passages = [];

        foreach ($closed as $item) {
            $animal = null === $item->knownAnimalId ? null : ($animals[$item->knownAnimalId] ?? null);
            $report = $reportByPatient[$item->patientId] ?? null;
            $vet    = null === $report ? $item->practitionerLabel : ($vets[$report->practitionerUserId] ?? $item->practitionerLabel);
            $motif  = $this->motif($item->triageNotes ?? $item->appointmentReason);

            $passages[] = new FluxPassage(
                appointmentTime: null === $item->appointmentStartsAt
                    ? null
                    : new \DateTimeImmutable($item->appointmentStartsAt)->setTimezone($timeZone)->format('H:i'),
                departureTime: new \DateTimeImmutable($item->closedAt ?? $item->openedAt)->setTimezone($timeZone)->format('H:i'),
                emoji: $this->emoji($animal?->species),
                name: $item->displayLabel,
                detail: null === $vet ? $motif : $motif . ' · ' . $vet,
                amountLabel: $amounts[$item->admissionId] ?? null,
                consultationId: $report?->consultationId,
                isNoShow: false,
            );
        }

        return $passages;
    }

    /**
     * @param list<AppointmentItem> $noShows
     * @param array<string, string> $vets
     *
     * @return list<FluxPassage>
     */
    private function noShows(array $noShows, array $vets, \DateTimeZone $timeZone): array
    {
        return array_map(function (AppointmentItem $appointment) use ($vets, $timeZone): FluxPassage {
            $vet   = $vets[$appointment->practitionerUserId] ?? $appointment->practitionerLabel;
            $motif = $this->motif($appointment->reason);

            return new FluxPassage(
                appointmentTime: new \DateTimeImmutable($appointment->startsAtUtc)->setTimezone($timeZone)->format('H:i'),
                departureTime: null,
                emoji: $this->emoji($appointment->animalSpecies),
                name: $appointment->animalLabel ?? 'Patient à préciser',
                detail: null === $vet ? $motif : $motif . ' · ' . $vet,
                amountLabel: null,
                consultationId: null,
                isNoShow: true,
            );
        }, $noShows);
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
     * Today's bookings still to arrive and the ones nobody came to, earliest first.
     *
     * @return array{list<AppointmentItem>, list<AppointmentItem>}
     */
    private function todaysBookings(string $clinicId, \DateTimeImmutable $now, \DateTimeZone $timeZone): array
    {
        $items = $this->queryBus->ask(GetAgendaForClinicDateRange::forDay($clinicId, $now, $timeZone));
        \assert(\is_array($items));

        $planned = [];
        $missed  = [];
        foreach ($items as $item) {
            \assert($item instanceof AppointmentItem);

            if ('PLANNED' === $item->status && null === $item->linkedAdmissionId) {
                $planned[] = $item;
            } elseif ('NO_SHOW' === $item->status) {
                $missed[] = $item;
            }
        }

        $byStart = static fn (AppointmentItem $a, AppointmentItem $b): int => $a->startsAtUtc <=> $b->startsAtUtc;
        usort($planned, $byStart);
        usort($missed, $byStart);

        return [$planned, $missed];
    }

    /**
     * @return array<string, string> display name by user id
     */
    private function veterinarians(string $clinicId): array
    {
        $items = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($items));

        $names = [];
        foreach ($items as $item) {
            \assert($item instanceof ClinicVeterinarianItem);
            // « Dr Lambert », as the cards of the mock-up name practitioners.
            $title                = ['DR' => 'Dr ', 'PR' => 'Pr '][$item->professionalTitle ?? ''] ?? '';
            $names[$item->userId] = $title . ($item->displayName ?? 'Praticien');
        }

        return $names;
    }

    /**
     * @return array<string, ConsultationListItemView> by patient id
     */
    private function openConsultationsByPatient(string $clinicId): array
    {
        $result = $this->queryBus->ask(
            new SearchConsultations($clinicId, statuses: ['OPEN'], sort: 'datetime', direction: 'desc', limit: 100),
        );
        \assert(\is_array($result) && \is_array($result['items'] ?? null));

        $byPatient = [];
        foreach ($result['items'] as $item) {
            \assert($item instanceof ConsultationListItemView);
            // Most recent first: the latest open consultation of a patient wins.
            $byPatient[$item->patientId] ??= $item;
        }

        return $byPatient;
    }

    /**
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
     * @param array<string, AnimalView> $animals
     *
     * @return array<string, ClientView> primary owner by animal id
     */
    private function owners(string $clinicId, array $animals): array
    {
        $clients = [];
        $owners  = [];

        foreach ($animals as $animalId => $animal) {
            $clientId = $animal->primaryOwnerClientId();

            if (null === $clientId) {
                continue;
            }

            if (!\array_key_exists($clientId, $clients)) {
                $client             = $this->queryBus->ask(new GetClientById($clinicId, $clientId));
                $clients[$clientId] = $client instanceof ClientView ? $client : null;
            }

            if (null !== $clients[$clientId]) {
                $owners[$animalId] = $clients[$clientId];
            }
        }

        return $owners;
    }

    /**
     * @param array<string, AnimalView> $animals
     *
     * @return array<string, list<string>> alert labels by animal id
     */
    private function alerts(string $clinicId, array $animals): array
    {
        $alerts = [];

        foreach (array_keys($animals) as $animalId) {
            $views = $this->queryBus->ask(new ListMedicalAlertsForAnimal($clinicId, $animalId));
            \assert(\is_array($views));

            $labels = [];
            foreach ($views as $view) {
                \assert($view instanceof MedicalAlertView);
                $labels[] = '⚠ ' . $view->label;
            }

            $alerts[$animalId] = $labels;
        }

        return $alerts;
    }

    private function emoji(?string $species): string
    {
        return self::SPECIES_EMOJI[strtolower($species ?? '')] ?? '🐾';
    }

    private function breed(?AnimalView $animal): string
    {
        if (null === $animal) {
            return '';
        }

        return $animal->breedName ?? match ($animal->species) {
            'dog'   => 'Chien',
            'cat'   => 'Chat',
            'nac'   => 'NAC',
            default => 'Animal',
        };
    }

    private function breedAndAge(?AnimalView $animal): string
    {
        $breed = $this->breed($animal);

        if (null === $animal || null === $animal->birthDate) {
            return $breed;
        }

        $diff = new \DateTimeImmutable($animal->birthDate)->diff($this->clock->now());
        $age  = $diff->y >= 1
            ? SheetText::plural($diff->y, 'an', 'ans')
            : SheetText::plural(max(1, $diff->m), 'mois', 'mois');

        return '' === $breed ? $age : $breed . ' · ' . $age;
    }

    /** « Sophie Marie Dupont » reads « Sophie Dupont » on a card. */
    private function shortName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];

        return \count($words) > 2 ? $words[0] . ' ' . $words[\count($words) - 1] : trim($name);
    }

    private function motif(?string $text): string
    {
        $text = trim((string) $text);

        if ('' === $text) {
            return 'À préciser';
        }

        // The motif is a chip: the first sentence of a longer note is enough.
        $first = preg_split('/(?<=[.!?])\s/u', $text)[0] ?? $text;

        return rtrim($first, '.');
    }

    private function phone(?ClientView $owner): ?string
    {
        return null === $owner ? null : PatientFactsReader::phone($owner);
    }

    private function duration(int $minutes): string
    {
        return $minutes < 60
            ? $minutes . ' min'
            : intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', \STR_PAD_LEFT);
    }
}
