<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Flux;

use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Admission\Application\Query\ListActiveAdmissions\ListActiveAdmissions;
use App\Context\Admission\Application\Query\ListAdmissionsClosedSince\ListAdmissionsClosedSince;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimal;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
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
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;

/**
 * Builds the ambulatory flow board (« Flux ambulatoire ») from the contexts
 * that own each piece: Scheduling (today's bookings not arrived yet),
 * Admission (who is on site, where, since when, who left today), Animal and
 * Client (species, breed, age, alerts, owner and phone), Consultation (the
 * open consultation of a patient taken in charge, what a patient at the desk
 * owes) and Clinic (time zone, practitioners).
 */
final readonly class FluxBoardViewBuilder
{
    /** A patient waiting longer than this gets the red chrono. */
    private const int LONG_WAIT_MINUTES = 15;

    private const array EMERGENCY_TRIAGE = ['emergency', 'vital_emergency'];

    private const array SPECIES_EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇'];

    private const array CHANNEL_LABEL = [
        'walk_in'                    => 'Sans RDV',
        'emergency'                  => 'Urgence',
        'emergency_by_third_party'   => 'Urgence · tiers',
        'emergency_by_authority'     => 'Urgence · autorités',
        'emergency_by_association'   => 'Urgence · association',
        'emergency_by_municipality'  => 'Urgence · mairie',
        'transfer_from_other_clinic' => 'Transfert',
    ];

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
        $closed = $this->admissions(new ListAdmissionsClosedSince($clinicId, $midnight));

        $appointments = $this->plannedAppointments($clinicId, $now, $timeZone);
        $vets         = $this->veterinarians($clinicId);
        $consultation = $this->openConsultationsByPatient($clinicId);
        $totals       = $this->totals($clinicId, $active);

        $animalIds = array_merge(
            array_map(static fn (WaitingRoomItemDto $i): ?string => $i->knownAnimalId, $active),
            array_map(static fn (AppointmentItem $a): ?string => $a->animalId, $appointments),
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
        usort($urgent, $bySince);
        usort($priority, $bySince);
        usort($standard, $bySince);
        usort($inCharge, $bySince);
        usort($checkout, $bySince);

        $waiting = [...$urgent, ...$priority, ...$standard];
        $average = [] === $waiting ? 0 : (int) round(array_sum(array_map(
            static fn (FluxPatient $p): int => intdiv(max(0, $now->getTimestamp() - $p->sinceEpoch), 60),
            $waiting,
        )) / \count($waiting));

        [$slots, $byAnimal] = $this->planned($appointments, $animals, $alerts, $vets, $timeZone);

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
            departed: array_reverse(array_map(
                static fn (WaitingRoomItemDto $item): FluxDeparture => new FluxDeparture(
                    name: $item->displayLabel,
                    time: new \DateTimeImmutable($item->closedAt ?? $item->openedAt)
                        ->setTimezone($timeZone)
                        ->format('H:i'),
                ),
                $closed,
            )),
            onSiteCount: \count($active),
            averageWaitMinutes: $average,
            freePractitioners: max(0, \count($vets) - \count(array_intersect_key($busy, $vets))),
            appointmentsByAnimal: $byAnimal,
        );
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

        $isUnidentified = null === $animalId;
        $ownerName      = null !== $owner ? $owner->fullName() : ($isUnidentified ? 'À régulariser' : '');

        return new FluxPatient(
            key: 'a-' . $item->admissionId,
            column: $column,
            admissionId: $item->admissionId,
            appointmentId: null,
            animalId: $animalId,
            consultationId: $open?->consultationId,
            emoji: $this->emoji($animal?->species),
            name: $item->displayLabel,
            breed: $this->breed($animal, $isUnidentified),
            breedAndAge: $this->breedAndAge($animal, $isUnidentified),
            owner: $ownerName,
            ownerShort: $this->shortName($ownerName),
            phone: $this->phone($owner),
            motif: $this->motif($item->triageNotes ?? $item->appointmentReason),
            practitioner: $practitioner,
            sinceEpoch: $since->getTimestamp(),
            sinceLabel: new \DateTimeImmutable($item->openedAt)->setTimezone($now->getTimezone())->format('H:i'),
            channel: 'appointment_booked' === $item->intakeChannel
                ? 'RDV'
                : (self::CHANNEL_LABEL[$item->intakeChannel] ?? 'Sans RDV'),
            triageLevel: $item->triageLevel,
            isEmergency: \in_array($item->triageLevel, self::EMERGENCY_TRIAGE, true),
            isPriority: 'priority' === $item->triageLevel,
            isUnidentified: $isUnidentified,
            isLong: 'attente' === $column && $minutes > self::LONG_WAIT_MINUTES,
            waitLabel: $this->duration($minutes),
            location: $item->locationStatus,
            alerts: null === $animalId ? [] : ($alerts[$animalId] ?? []),
            amountLabel: null === $total ? null : SheetText::money($total->totalTtcMinorUnits, $total->currency),
            slotLabel: null,
        );
    }

    /**
     * The « Planifié » column grouped by start time, and today's booking of
     * each animal so the check-in modal can offer to honour it.
     *
     * @param list<AppointmentItem>       $appointments
     * @param array<string, AnimalView>   $animals
     * @param array<string, list<string>> $alerts
     * @param array<string, string>       $vets         display name by user id
     *
     * @return array{list<FluxSlot>, array<string, array{appointmentId: string, time: string, motif: string}>}
     */
    private function planned(
        array $appointments,
        array $animals,
        array $alerts,
        array $vets,
        \DateTimeZone $timeZone,
    ): array {
        $bySlot   = [];
        $byAnimal = [];

        foreach ($appointments as $appointment) {
            $start  = new \DateTimeImmutable($appointment->startsAtUtc)->setTimezone($timeZone);
            $time   = $start->format('H:i');
            $animal = null === $appointment->animalId ? null : ($animals[$appointment->animalId] ?? null);
            $owner  = $appointment->ownerLabel ?? '';
            $motif  = $this->motif($appointment->reason);

            $bySlot[$time][] = new FluxPatient(
                key: 'r-' . $appointment->id,
                column: 'planifie',
                admissionId: null,
                appointmentId: $appointment->id,
                animalId: $appointment->animalId,
                consultationId: null,
                emoji: $this->emoji($animal->species ?? $appointment->animalSpecies),
                name: $appointment->animalLabel ?? 'Patient à préciser',
                breed: $this->breed($animal, false),
                breedAndAge: $this->breedAndAge($animal, false),
                owner: $owner,
                ownerShort: $this->shortName($owner),
                phone: null === $appointment->ownerPhone ? null : $this->formatPhone($appointment->ownerPhone),
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
     * @return list<WaitingRoomItemDto>
     */
    private function admissions(ListActiveAdmissions|ListAdmissionsClosedSince $query): array
    {
        $items = $this->queryBus->ask($query);
        \assert(\is_array($items));

        return array_values(array_filter($items, static fn (mixed $item): bool => $item instanceof WaitingRoomItemDto));
    }

    /**
     * Today's bookings still to arrive, earliest first.
     *
     * @return list<AppointmentItem>
     */
    private function plannedAppointments(string $clinicId, \DateTimeImmutable $now, \DateTimeZone $timeZone): array
    {
        $items = $this->queryBus->ask(GetAgendaForClinicDateRange::forDay($clinicId, $now, $timeZone));
        \assert(\is_array($items));

        $planned = [];
        foreach ($items as $item) {
            \assert($item instanceof AppointmentItem);

            if ('PLANNED' === $item->status && null === $item->linkedAdmissionId) {
                $planned[] = $item;
            }
        }

        usort($planned, static fn (AppointmentItem $a, AppointmentItem $b): int => $a->startsAtUtc <=> $b->startsAtUtc);

        return $planned;
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

    private function breed(?AnimalView $animal, bool $isUnidentified): string
    {
        if (null === $animal) {
            return $isUnidentified ? 'Espèce présumée' : '';
        }

        return $animal->breedName ?? match ($animal->species) {
            'dog'   => 'Chien',
            'cat'   => 'Chat',
            'nac'   => 'NAC',
            default => 'Animal',
        };
    }

    private function breedAndAge(?AnimalView $animal, bool $isUnidentified): string
    {
        $breed = $this->breed($animal, $isUnidentified);

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
        if (null === $owner) {
            return null;
        }

        $fallback = null;
        foreach ($owner->contactMethods as $method) {
            if ('phone' !== $method->type) {
                continue;
            }

            if ($method->isPrimary) {
                return $this->formatPhone($method->value);
            }

            $fallback ??= $method->value;
        }

        return null === $fallback ? null : $this->formatPhone($fallback);
    }

    /** « +33612345678 » reads « 06 12 34 56 78 »; anything else is kept as typed. */
    private function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '33') && 11 === \strlen($digits)) {
            $digits = '0' . substr($digits, 2);
        }

        if (10 !== \strlen($digits)) {
            return $phone;
        }

        return implode(' ', str_split($digits, 2));
    }

    private function duration(int $minutes): string
    {
        return $minutes < 60
            ? $minutes . ' min'
            : intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', \STR_PAD_LEFT);
    }
}
