<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Animal;

use App\Context\Animal\Application\Query\CountAnimals\CountAnimals;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\ListAnimalPreventiveCare;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\PreventiveCareRecordView;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\ListAnimalWeightRecords;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimal;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\FindInvoiceForAdmission;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListItemView;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoices;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal\AnimalAppointmentItem;
use App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal\ListAppointmentsForAnimal;
use App\Presentation\Clinic\ViewModel\Client\ClientContactSlots;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Gathers everything the animal sheet shows, section by section.
 *
 * Like the client sheet, it asks each context through its query bus and reads
 * in one batched statement each the few facts no query exposes yet: the
 * consultations of an animal (through its patient files) and the lines of its
 * prescriptions.
 */
final readonly class AnimalSheetBuilder
{
    private const int INVOICE_LIMIT    = 200;
    private const int INVOICE_LOOKUPS  = 60;
    private const int PAST_APPOINTMENT = 3;

    public function __construct(
        private QueryBusInterface $queryBus,
        private Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(AnimalView $animal, \DateTimeZone $clinicTz, \DateTimeImmutable $now): array
    {
        $clinicId      = $animal->clinicId;
        $practitioners = $this->practitioners($clinicId);
        $owner         = $this->owner($animal);
        $consultations = $this->consultationRows($clinicId, $animal->id);
        $prescriptions = $this->prescriptionRows($consultations);
        $invoices      = $this->invoicesByAdmission($animal, $consultations);
        $appointments  = $this->appointmentItems($clinicId, $animal->id);
        $weights       = $this->weightPoints($clinicId, $animal->id, $consultations, $clinicTz);
        $alerts        = $this->alerts($clinicId, $animal->id);
        $prevention    = $this->preventionView($clinicId, $animal->id, $now->setTimezone($clinicTz));
        $rdv           = $this->appointmentsView($appointments, $practitioners, $clinicTz, $now);
        $treatments    = $this->treatmentsView($prescriptions, $now->setTimezone($clinicTz));
        $lastWeight    = [] === $weights ? null : $weights[array_key_last($weights)];
        $lastVisit     = $consultations[0]['startedAt'] ?? null;
        $openVisit     = null;

        foreach ($consultations as $consultation) {
            if ('OPEN' === $consultation['status']) {
                $openVisit = $consultation;

                break;
            }
        }

        return [
            'kind'          => \in_array($animal->species, ['dog', 'cat'], true) ? $animal->species : 'nac',
            'subtitle'      => $this->subtitle($animal, $lastWeight, $now),
            'editable'      => 'active' === $animal->status && 'alive' === $animal->lifeCycle->lifeStatus,
            'archived'      => 'active' !== $animal->status,
            'deceased'      => 'deceased' === $animal->lifeCycle->lifeStatus,
            'owner'         => $this->ownerView($owner, $clinicId, $clinicTz),
            'identity'      => $this->identityView($animal, $clinicTz),
            'alerts'        => $alerts,
            'treatments'    => $treatments,
            'weight'        => $this->weightView($weights, $clinicTz),
            'appointments'  => $rdv,
            'practitioners' => $practitioners,
            'openVisit'     => null === $openVisit ? null : [
                'id'    => $openVisit['id'],
                'label' => \sprintf(
                    'En consultation depuis %s',
                    SheetText::date($openVisit['startedAt']->setTimezone($clinicTz), "HH 'h' mm"),
                ),
                'detail' => $practitioners[$openVisit['practitionerUserId']] ?? null,
            ],
            'prescriptions' => $this->prescriptionsView($prescriptions, $clinicTz),
            'history'       => $this->historyView($consultations, $invoices, $prescriptions, $practitioners, $clinicTz),
            'prevention'    => $prevention,
            'documents'     => $this->documentsView($consultations, $invoices, $prescriptions, $practitioners, $clinicTz),
            'safety'        => $this->safety($animal, $alerts, $treatments, $lastVisit, $rdv['next'], $clinicTz),
        ];
    }

    /**
     * @return array<string, string> user id => display name
     */
    private function practitioners(string $clinicId): array
    {
        $veterinarians = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($veterinarians));

        $names = [];

        foreach ($veterinarians as $vet) {
            \assert($vet instanceof ClinicVeterinarianItem);

            if (null !== $vet->displayName) {
                $names[$vet->userId] = $vet->displayName;
            }
        }

        return $names;
    }

    private function owner(AnimalView $animal): ?ClientView
    {
        $ownerId = $animal->primaryOwnerClientId();

        if (null === $ownerId) {
            return null;
        }

        $owner = $this->queryBus->ask(new GetClientById($animal->clinicId, $ownerId));

        return $owner instanceof ClientView ? $owner : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ownerView(?ClientView $owner, string $clinicId, \DateTimeZone $tz): ?array
    {
        if (null === $owner) {
            return null;
        }

        $slots      = ClientContactSlots::fromContactMethods($owner->contactMethods);
        $balanceDue = 0;
        $currency   = 'EUR';

        foreach ($this->invoiceRows($clinicId, $owner->id) as $invoice) {
            if (null !== $invoice->number && 'CANCELLED' !== $invoice->status) {
                $balanceDue += $invoice->balanceDueMinorUnits;
                $currency = $invoice->currency;
            }
        }

        $animalCount = $this->queryBus->ask(new CountAnimals(clinicId: $clinicId, ownerClientId: $owner->id));
        $person      = $owner->authorizedPerson;

        return [
            'id'          => $owner->id,
            'name'        => $owner->fullName(),
            'initials'    => mb_strtoupper(mb_substr($owner->firstName, 0, 1) . mb_substr($owner->lastName, 0, 1)),
            'phone'       => $slots->mobile?->value,
            'email'       => $slots->email?->value,
            'since'       => (new \DateTimeImmutable($owner->createdAt))->setTimezone($tz)->format('Y'),
            'archived'    => 'archived' === $owner->status,
            'balanceDue'  => $balanceDue,
            'currency'    => $currency,
            'animalCount' => \is_int($animalCount) ? $animalCount : 0,
            'authorized'  => null === $person ? null : [
                'name'         => $person->fullName(),
                'relationship' => mb_strtolower(AuthorizedPersonRelationship::from($person->relationship)->label()),
                'phone'        => $person->phoneNumber,
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: ?string, mono: bool}>
     */
    private function identityView(AnimalView $animal, \DateTimeZone $tz): array
    {
        $birthDate = null === $animal->birthDate
            ? null
            : SheetText::date(new \DateTimeImmutable($animal->birthDate, $tz), 'd MMMM y');

        return [
            ['label' => 'Transpondeur', 'value' => $animal->identification->microchipNumber, 'mono' => true],
            ['label' => 'Naissance', 'value' => $birthDate, 'mono' => false],
            ['label' => 'Robe', 'value' => $animal->color, 'mono' => false],
            ['label' => 'Passeport', 'value' => $animal->identification->passportNumber, 'mono' => true],
            ['label' => 'Tatouage', 'value' => $animal->identification->tattooNumber, 'mono' => true],
        ];
    }

    /**
     * @param array{grams: int, ...}|null $lastWeight
     */
    private function subtitle(AnimalView $animal, ?array $lastWeight, \DateTimeImmutable $now): string
    {
        $neutered = 'neutered' === $animal->reproductiveStatus;
        $sex      = match ($animal->sex) {
            'female' => $neutered ? 'femelle stérilisée' : 'femelle',
            'male'   => $neutered ? 'mâle castré' : 'mâle',
            default  => null,
        };

        $age = null;

        if (null !== $animal->birthDate) {
            $diff = (new \DateTimeImmutable($animal->birthDate))->diff($now);
            $age  = $diff->y >= 1
                ? SheetText::plural($diff->y, 'an', 'ans')
                : $diff->m . ' mois';
        }

        return implode(' · ', array_filter([
            $animal->breedName,
            $sex,
            $age,
            null === $lastWeight ? null : SheetText::weight($lastWeight['grams']),
        ]));
    }

    /**
     * @return list<array{id: string, allergy: bool, kindLabel: string, label: string, note: ?string}>
     */
    private function alerts(string $clinicId, string $animalId): array
    {
        $views = $this->queryBus->ask(new ListMedicalAlertsForAnimal($clinicId, $animalId));
        \assert(\is_array($views));

        $alerts = [];

        foreach ($views as $view) {
            \assert($view instanceof MedicalAlertView);

            $allergy  = 'ALLERGY' === $view->kind;
            $alerts[] = [
                'id'        => $view->id,
                'allergy'   => $allergy,
                'kindLabel' => $allergy ? 'Allergie' : 'Suivi',
                'label'     => $view->label,
                'note'      => $view->note,
            ];
        }

        // Allergies block a prescription: they lead the list.
        usort($alerts, static fn (array $a, array $b): int => $b['allergy'] <=> $a['allergy']);

        return $alerts;
    }

    /**
     * Every consultation of the animal, through its patient files, newest first.
     *
     * @return list<array{
     *     id: string,
     *     admissionId: ?string,
     *     practitionerUserId: string,
     *     status: string,
     *     startedAt: \DateTimeImmutable,
     *     reason: ?string,
     *     summary: ?string,
     *     weightKg: ?string,
     *     temperatureC: ?string
     * }>
     */
    private function consultationRows(string $clinicId, string $animalId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(c.id) AS id, BIN_TO_UUID(c.admission_id) AS admission_id,
                    BIN_TO_UUID(c.practitioner_user_id) AS practitioner_user_id, c.status,
                    c.started_at_utc, c.chief_complaint, c.summary, c.weight_kg, c.temperature_c
             FROM patient__patients p
             INNER JOIN consultation__consultations c ON c.patient_id = p.id
             WHERE p.animal_link_id = :animalId AND p.clinic_id = :clinicId AND c.clinic_id = :clinicId
             ORDER BY c.started_at_utc DESC',
            [
                'animalId' => Uuid::fromString($animalId)->toBinary(),
                'clinicId' => Uuid::fromString($clinicId)->toBinary(),
            ],
        );

        $utc           = new \DateTimeZone('UTC');
        $consultations = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']) && \is_string($row['practitioner_user_id']));
            \assert(\is_string($row['status']) && \is_string($row['started_at_utc']));

            $consultations[] = [
                'id'                 => $row['id'],
                'admissionId'        => \is_string($row['admission_id']) ? $row['admission_id'] : null,
                'practitionerUserId' => $row['practitioner_user_id'],
                'status'             => $row['status'],
                'startedAt'          => new \DateTimeImmutable($row['started_at_utc'], $utc),
                'reason'             => $this->text($row['chief_complaint']),
                'summary'            => $this->text($row['summary']),
                'weightKg'           => $this->number($row['weight_kg']),
                'temperatureC'       => $this->number($row['temperature_c']),
            ];
        }

        return $consultations;
    }

    private function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    private function number(mixed $value): ?string
    {
        return \is_string($value) || \is_float($value) || \is_int($value) ? (string) $value : null;
    }

    /**
     * Issued prescriptions of the listed consultations with their lines, newest first.
     *
     * @param list<array{id: string, ...}> $consultations
     *
     * @return list<array{
     *     id: string,
     *     consultationId: string,
     *     number: string,
     *     issuedAt: \DateTimeImmutable,
     *     prescriber: string,
     *     lines: list<array{label: string, dose: ?string, frequency: ?string, durationDays: ?int, route: ?string}>
     * }>
     */
    private function prescriptionRows(array $consultations): array
    {
        if ([] === $consultations) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(pr.id) AS id, BIN_TO_UUID(pr.consultation_id) AS consultation_id, pr.number,
                    pr.issued_at, pr.prescriber_display_name,
                    l.label, l.dose, l.frequency, l.duration_days, l.route
             FROM prescription__prescriptions pr
             LEFT JOIN prescription__prescription_lines l ON l.prescription_id = pr.id
             WHERE pr.consultation_id IN (?) AND pr.status = 'ISSUED' AND pr.number IS NOT NULL
             ORDER BY pr.issued_at DESC, pr.id ASC, l.position ASC",
            [array_map(static fn (array $c): string => Uuid::fromString($c['id'])->toBinary(), $consultations)],
            [ArrayParameterType::BINARY],
        );

        $prescriptions = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']) && \is_string($row['consultation_id']));
            \assert(\is_string($row['number']) && \is_string($row['issued_at']));

            $prescriptions[$row['id']] ??= [
                'id'             => $row['id'],
                'consultationId' => $row['consultation_id'],
                'number'         => $row['number'],
                'issuedAt'       => new \DateTimeImmutable($row['issued_at'], new \DateTimeZone('UTC')),
                'prescriber'     => $this->text($row['prescriber_display_name']) ?? '—',
                'lines'          => [],
            ];

            $label = $this->text($row['label']);

            if (null !== $label) {
                $prescriptions[$row['id']]['lines'][] = [
                    'label'        => $label,
                    'dose'         => $this->text($row['dose']),
                    'frequency'    => $this->text($row['frequency']),
                    'durationDays' => is_numeric($row['duration_days']) ? (int) $row['duration_days'] : null,
                    'route'        => $this->text($row['route']),
                ];
            }
        }

        return array_values($prescriptions);
    }

    /**
     * @return list<InvoiceListRow>
     */
    private function invoiceRows(string $clinicId, string $ownerId): array
    {
        $result = $this->queryBus->ask(new SearchInvoices($clinicId, new SearchInvoicesCriteria(
            payerClientId: $ownerId,
            perPage: self::INVOICE_LIMIT,
        )));
        \assert($result instanceof InvoiceListItemView);

        return $result->items;
    }

    /**
     * @return list<AnimalAppointmentItem>
     */
    private function appointmentItems(string $clinicId, string $animalId): array
    {
        $items = $this->queryBus->ask(new ListAppointmentsForAnimal($clinicId, $animalId));
        \assert(\is_array($items));

        $appointments = [];

        foreach ($items as $item) {
            \assert($item instanceof AnimalAppointmentItem);
            $appointments[] = $item;
        }

        return $appointments;
    }

    /**
     * Weights measured in consultation and weighings taken on their own, oldest first.
     *
     * @param list<array{startedAt: \DateTimeImmutable, weightKg: ?string, ...}> $consultations
     *
     * @return list<array{date: \DateTimeImmutable, grams: int, bodyConditionScore: ?int}>
     */
    private function weightPoints(string $clinicId, string $animalId, array $consultations, \DateTimeZone $tz): array
    {
        $points = [];

        foreach ($consultations as $consultation) {
            if (null !== $consultation['weightKg'] && (float) $consultation['weightKg'] > 0) {
                $points[] = [
                    'date'               => $consultation['startedAt']->setTimezone($tz),
                    'grams'              => (int) round((float) $consultation['weightKg'] * 1000),
                    'bodyConditionScore' => null,
                ];
            }
        }

        $records = $this->queryBus->ask(new ListAnimalWeightRecords($clinicId, $animalId));
        \assert(\is_array($records));

        // Records come newest first. Walking them backwards keeps, for one same day, the order they
        // were entered in — the sort below is stable — so a corrected weighing ends up last.
        foreach (array_reverse($records) as $record) {
            \assert($record instanceof WeightRecordView);

            $points[] = [
                // A weighing has a day, no hour: it is placed after that day's consultations.
                'date'               => new \DateTimeImmutable($record->measuredOn->format('Y-m-d') . ' 23:59:59', $tz),
                'grams'              => $record->weightGrams,
                'bodyConditionScore' => $record->bodyConditionScore,
            ];
        }

        usort($points, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $points;
    }

    /**
     * The weight curve, already projected onto the 560 × 140 drawing of the mock-up.
     *
     * @param list<array{date: \DateTimeImmutable, grams: int, bodyConditionScore: ?int}> $points
     *
     * @return array<string, mixed>|null
     */
    private function weightView(array $points, \DateTimeZone $tz): ?array
    {
        if ([] === $points) {
            return null;
        }

        $last  = $points[array_key_last($points)];
        $first = $points[0];
        $grams = array_map(static fn (array $point): int => $point['grams'], $points);
        $min   = min($grams);
        $max   = max($grams);

        // Some air around the curve, proportional so that a 40 g hamster and a 40 kg dog both read well.
        $margin = max(($max - $min) * 0.15, $max * 0.02, 1);
        $low    = max(0, $min - $margin);
        $high   = $max + $margin;

        $left   = 30.0;
        $right  = 548.0;
        $top    = 18.0;
        $bottom = 118.0;
        $span   = max(1, $last['date']->getTimestamp() - $first['date']->getTimestamp());

        $coordinates = [];

        foreach ($points as $point) {
            $x = 1 === \count($points)
                ? $right
                : $left + ($point['date']->getTimestamp() - $first['date']->getTimestamp()) / $span * ($right - $left);
            $y = $bottom - ($point['grams'] - $low) / ($high - $low) * ($bottom - $top);

            $coordinates[] = \sprintf('%.1F,%.1F', $x, $y);
        }

        $ticks = [];

        foreach ([0.0, 1 / 3, 2 / 3, 1.0] as $ratio) {
            $ticks[] = [
                'y'     => \sprintf('%.1F', $bottom - $ratio * ($bottom - $top)),
                'label' => number_format(($low + $ratio * ($high - $low)) / 1000, 1, ',', ''),
            ];
        }

        // The trend compares the last weight with the latest one at least three months older.
        $reference = null;
        $threshold = $last['date']->modify('-3 months');

        foreach ($points as $point) {
            if ($point['date'] <= $threshold) {
                $reference = $point;
            }
        }

        $trend = null;

        if (null !== $reference) {
            $delta  = $last['grams'] - $reference['grams'];
            $ratio  = abs($delta) / $reference['grams'];
            $recent = $reference['date'] >= $last['date']->modify('-6 months');
            $trend  = [
                'label' => \sprintf(
                    '%s depuis %s',
                    0 === $delta ? 'Stable' : ($delta > 0 ? '+' : '−') . SheetText::weight(abs($delta)),
                    SheetText::date($reference['date'], 'MMM y'),
                ),
                // Only a change over a few months says something; over years it is just growth or age.
                'badge' => !$recent ? 'grey' : ($ratio >= 0.05 ? 'crit' : ($ratio >= 0.02 ? 'warn' : 'ok')),
            ];
        }

        $score = null;

        foreach (array_reverse($points) as $point) {
            if (null !== $point['bodyConditionScore']) {
                $score = $point['bodyConditionScore'];

                break;
            }
        }

        $lastXY = explode(',', $coordinates[array_key_last($coordinates)]);

        return [
            'polyline'   => implode(' ', $coordinates),
            'polygon'    => \sprintf('%s,%s %s %s,%s', explode(',', $coordinates[0])[0], $bottom, implode(' ', $coordinates), $lastXY[0], $bottom),
            'ticks'      => $ticks,
            'lastX'      => $lastXY[0],
            'lastY'      => $lastXY[1],
            'lastLabel'  => SheetText::weight($last['grams']),
            'lastDate'   => SheetText::date($last['date'], 'd MMM y'),
            'firstMonth' => SheetText::date($first['date'], 'MMM y'),
            'lastMonth'  => SheetText::date($last['date'], 'MMM y'),
            'count'      => \count($points),
            'trend'      => $trend,
            'score'      => $score,
            'scoreTone'  => null === $score ? '' : ($score >= 4 && $score <= 5 ? 'ok' : 'warn'),
            'currentKg'  => number_format($last['grams'] / 1000, 1, ',', ''),
        ];
    }

    /**
     * Prescription lines whose duration has not run out yet.
     *
     * @param list<array{number: string, issuedAt: \DateTimeImmutable, prescriber: string, id: string, lines: list<array{label: string, dose: ?string, frequency: ?string, durationDays: ?int, route: ?string}>, ...}> $prescriptions
     *
     * @return list<array{title: string, subtitle: string, day: int, days: int, percent: int, prescriptionId: string}>
     */
    private function treatmentsView(array $prescriptions, \DateTimeImmutable $today): array
    {
        $treatments = [];

        foreach ($prescriptions as $prescription) {
            $issuedOn = $prescription['issuedAt']->setTimezone($today->getTimezone())->setTime(0, 0);
            $day      = (int) $issuedOn->diff($today->setTime(0, 0))->days + 1;

            foreach ($prescription['lines'] as $line) {
                if (null === $line['durationDays'] || $line['durationDays'] < 1 || $day > $line['durationDays']) {
                    continue;
                }

                $treatments[] = [
                    'title' => implode(' — ', array_filter([
                        $line['label'],
                        implode(' ', array_filter([$line['dose'], $line['frequency']])),
                    ])),
                    'subtitle' => implode(' · ', array_filter([
                        $line['route'],
                        $prescription['prescriber'],
                        $prescription['number'],
                    ])),
                    'day'            => $day,
                    'days'           => $line['durationDays'],
                    'percent'        => (int) round($day / $line['durationDays'] * 100),
                    'prescriptionId' => $prescription['id'],
                ];
            }
        }

        return $treatments;
    }

    /**
     * @param list<AnimalAppointmentItem> $appointments
     * @param array<string, string>       $practitioners
     *
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     upcomingCount: int,
     *     olderCount: int,
     *     next: array{when: string, detail: string, date: string}|null
     * }
     */
    private function appointmentsView(
        array $appointments,
        array $practitioners,
        \DateTimeZone $tz,
        \DateTimeImmutable $now,
    ): array {
        $utc      = new \DateTimeZone('UTC');
        $upcoming = [];
        $past     = [];

        foreach ($appointments as $appointment) {
            $startsAt     = (new \DateTimeImmutable($appointment->startsAtUtc, $utc))->setTimezone($tz);
            $practitioner = $practitioners[$appointment->practitionerUserId] ?? null;
            $title        = $appointment->reason ?? 'Rendez-vous';

            if ('CANCELLED' === $appointment->status) {
                continue;
            }

            if ('PLANNED' === $appointment->status && $startsAt >= $now) {
                $upcoming[] = [
                    'day'      => $startsAt->format('d'),
                    'title'    => $title,
                    'subtitle' => SheetText::sentence(implode(' · ', array_filter([
                        SheetText::date($startsAt, "EEEE d MMMM, HH 'h' mm"),
                        $practitioner,
                        $appointment->durationMinutes . ' min',
                    ]))),
                    'upcoming'   => true,
                    'badge'      => 'brand',
                    'badgeLabel' => 'Planifié',
                    'date'       => $startsAt->format('Y-m-d'),
                    'when'       => SheetText::date($startsAt, "EEEE d MMMM, HH 'h' mm"),
                    'detail'     => implode(', ', array_filter([$appointment->reason, $practitioner])),
                ];

                continue;
            }

            if ($startsAt < $now) {
                $past[] = [
                    'day'      => $startsAt->format('d'),
                    'title'    => $title,
                    'subtitle' => implode(' · ', array_filter([
                        SheetText::date($startsAt, 'd MMMM y'),
                        $practitioner,
                        match ($appointment->status) {
                            'COMPLETED' => 'honoré',
                            'NO_SHOW'   => 'absence non prévenue',
                            default     => null,
                        },
                    ])),
                    'upcoming'   => false,
                    'badge'      => 'NO_SHOW' === $appointment->status ? 'crit' : null,
                    'badgeLabel' => 'NO_SHOW' === $appointment->status ? 'Absent' : null,
                    'date'       => $startsAt->format('Y-m-d'),
                ];
            }
        }

        // The list arrives newest first: the soonest upcoming appointment must lead.
        $upcoming = array_reverse($upcoming);
        $next     = $upcoming[0] ?? null;

        return [
            'rows'          => [...$upcoming, ...\array_slice($past, 0, self::PAST_APPOINTMENT)],
            'upcomingCount' => \count($upcoming),
            'olderCount'    => max(0, \count($past) - self::PAST_APPOINTMENT),
            'next'          => null === $next
                ? null
                : ['when' => $next['when'], 'detail' => $next['detail'], 'date' => $next['date']],
        ];
    }

    /**
     * @param list<array{id: string, number: string, issuedAt: \DateTimeImmutable, prescriber: string, lines: list<array{label: string, ...}>, ...}> $prescriptions
     *
     * @return list<array{id: string, number: string, date: string, prescriber: string, summary: string, lineCount: int}>
     */
    private function prescriptionsView(array $prescriptions, \DateTimeZone $tz): array
    {
        return array_map(static fn (array $prescription): array => [
            'id'         => $prescription['id'],
            'number'     => $prescription['number'],
            'date'       => SheetText::date($prescription['issuedAt']->setTimezone($tz), 'd MMMM y'),
            'prescriber' => $prescription['prescriber'],
            'summary'    => implode(', ', array_map(static fn (array $line): string => $line['label'], $prescription['lines'])),
            'lineCount'  => \count($prescription['lines']),
        ], $prescriptions);
    }

    /**
     * @param list<array{id: string, admissionId: ?string, practitionerUserId: string, status: string, startedAt: \DateTimeImmutable, reason: ?string, summary: ?string, weightKg: ?string, temperatureC: ?string}> $consultations
     * @param array<string, array{id: string, number: string, total: int, currency: string, paid: bool}>                                                                                                            $invoices
     * @param list<array{id: string, consultationId: string, ...}>                                                                                                                                                  $prescriptions
     * @param array<string, string>                                                                                                                                                                                 $practitioners
     *
     * @return array{rows: list<array<string, mixed>>, total: int, firstVisit: ?string}
     */
    private function historyView(
        array $consultations,
        array $invoices,
        array $prescriptions,
        array $practitioners,
        \DateTimeZone $tz,
    ): array {
        $invoiceByAdmission = $invoices;
        $rows               = [];

        foreach ($consultations as $consultation) {
            $startedAt = $consultation['startedAt']->setTimezone($tz);
            $invoice   = null === $consultation['admissionId']
                ? null
                : $invoiceByAdmission[$consultation['admissionId']] ?? null;
            $open = 'OPEN' === $consultation['status'];

            $vitals = [];

            if (null !== $consultation['temperatureC']) {
                $vitals[] = [
                    'label' => 'Température',
                    'value' => number_format((float) $consultation['temperatureC'], 1, ',', '') . ' °C',
                ];
            }

            if (null !== $consultation['weightKg']) {
                $vitals[] = [
                    'label' => 'Poids',
                    'value' => SheetText::weight((int) round((float) $consultation['weightKg'] * 1000)),
                ];
            }

            $rows[] = [
                'id'       => $consultation['id'],
                'day'      => $startedAt->format('d'),
                'title'    => $consultation['reason'] ?? 'Consultation',
                'subtitle' => implode(' · ', array_filter([
                    SheetText::date($startedAt, 'd MMMM y'),
                    $practitioners[$consultation['practitionerUserId']] ?? null,
                    null === $invoice ? null : SheetText::money($invoice['total'], $invoice['currency']),
                ])),
                'open'          => $open,
                'summary'       => $consultation['summary'],
                'vitals'        => $vitals,
                'prescriptions' => array_values(array_filter(
                    $prescriptions,
                    static fn (array $prescription): bool => $prescription['consultationId'] === $consultation['id'],
                )),
                'invoiceId' => $invoice['id'] ?? null,
            ];
        }

        $first = [] === $consultations ? null : $consultations[array_key_last($consultations)]['startedAt'];

        return [
            'rows'       => $rows,
            'total'      => \count($consultations),
            'firstVisit' => null === $first ? null : SheetText::date($first->setTimezone($tz), 'd MMMM y'),
        ];
    }

    /**
     * The invoice of each visit, keyed by admission id.
     *
     * The owners' invoice lists answer most visits in one query each. A visit
     * they do not cover — paid by a former owner, say — is then asked from
     * Billing on its own: an invoice belongs to the visit, whoever owns the
     * animal today.
     *
     * @param list<array{admissionId: ?string, ...}> $consultations
     *
     * @return array<string, array{id: string, number: string, total: int, currency: string, paid: bool}>
     */
    private function invoicesByAdmission(AnimalView $animal, array $consultations): array
    {
        $byAdmission = [];
        $ownerIds    = array_filter([$animal->primaryOwnerClientId(), ...$animal->secondaryOwnerClientIds()]);

        foreach ($ownerIds as $ownerId) {
            // Invoices come newest first: the latest one still standing wins over a cancelled one.
            foreach ($this->invoiceRows($animal->clinicId, $ownerId) as $invoice) {
                if (null === $invoice->admissionId || null === $invoice->number || 'CANCELLED' === $invoice->status) {
                    continue;
                }

                $byAdmission[$invoice->admissionId] ??= [
                    'id'       => $invoice->id,
                    'number'   => $invoice->number,
                    'total'    => $invoice->totalGrossMinorUnits,
                    'currency' => $invoice->currency,
                    'paid'     => 0 === $invoice->balanceDueMinorUnits,
                ];
            }
        }

        foreach (\array_slice($consultations, 0, self::INVOICE_LOOKUPS) as $consultation) {
            $admissionId = $consultation['admissionId'];

            if (null === $admissionId || isset($byAdmission[$admissionId])) {
                continue;
            }

            $invoice = $this->queryBus->ask(new FindInvoiceForAdmission($animal->clinicId, $admissionId));

            if ($invoice instanceof AdmissionInvoiceRow && null !== $invoice->number && $invoice->isPrintable()) {
                $byAdmission[$admissionId] = [
                    'id'       => $invoice->id,
                    'number'   => $invoice->number,
                    'total'    => $invoice->totalGrossMinorUnits,
                    'currency' => $invoice->currency,
                    'paid'     => 'PAID' === $invoice->status,
                ];
            }
        }

        return $byAdmission;
    }

    /**
     * The latest act of each protocol, with where its next dose stands today.
     *
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     due: list<array<string, mixed>>,
     *     lateCount: int
     * }
     */
    private function preventionView(string $clinicId, string $animalId, \DateTimeImmutable $today): array
    {
        $records = $this->queryBus->ask(new ListAnimalPreventiveCare($clinicId, $animalId));
        \assert(\is_array($records));

        $today  = $today->setTime(0, 0);
        $latest = [];

        // Records come newest first: the first one seen for a protocol is its latest act.
        foreach ($records as $record) {
            \assert($record instanceof PreventiveCareRecordView);

            $latest[$record->kind . '|' . mb_strtolower($record->label)] ??= $record;
        }

        $rows      = [];
        $due       = [];
        $lateCount = 0;

        foreach ($latest as $record) {
            // Dates carry no time zone: read them as calendar days in the clinic's zone.
            $nextDue = null === $record->nextDueOn
                ? null
                : new \DateTimeImmutable($record->nextDueOn->format('Y-m-d'), $today->getTimezone());
            $late = null !== $nextDue && $nextDue < $today;
            $days = null === $nextDue ? null : (int) $nextDue->diff($today)->days;
            $soon = null !== $nextDue && !$late && $days <= 30;

            if ($late) {
                ++$lateCount;
            }

            $rows[] = [
                'label'        => $record->label,
                'kindLabel'    => PreventiveCareKind::from($record->kind)->label(),
                'administered' => SheetText::date($record->administeredOn, 'd MMMM y'),
                'author'       => $record->authorName,
                'batch'        => $record->batchNumber,
                'nextDue'      => null === $nextDue ? null : SheetText::date($nextDue, 'd MMMM y'),
                'late'         => $late,
                'lateDays'     => $late ? $days : null,
                'badge'        => $late ? 'crit' : ($soon ? 'warn' : 'ok'),
                'badgeLabel'   => $late ? 'En retard' : ($soon ? 'À prévoir' : 'À jour'),
                'sortKey'      => $nextDue?->getTimestamp() ?? \PHP_INT_MAX,
            ];

            if (null !== $nextDue && ($late || $soon)) {
                $due[] = [
                    'label'  => $record->label,
                    'late'   => $late,
                    'day'    => $nextDue->format('d'),
                    'detail' => $late
                        ? \sprintf('Échéance dépassée de %s', SheetText::plural((int) $days, 'jour', 'jours'))
                        : \sprintf('Échéance le %s', SheetText::date($nextDue, 'd MMMM y')),
                    'sortKey' => $nextDue->getTimestamp(),
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => $a['sortKey'] <=> $b['sortKey']);
        usort($due, static fn (array $a, array $b): int => $a['sortKey'] <=> $b['sortKey']);

        return ['rows' => $rows, 'due' => $due, 'lateCount' => $lateCount];
    }

    /**
     * Reports, prescriptions and invoices produced for this animal, newest first.
     *
     * @param list<array{id: string, admissionId: ?string, practitionerUserId: string, status: string, startedAt: \DateTimeImmutable, ...}>     $consultations
     * @param array<string, array{id: string, number: string, total: int, currency: string, paid: bool}>                                        $invoices
     * @param list<array{id: string, number: string, issuedAt: \DateTimeImmutable, prescriber: string, lines: list<array<string, mixed>>, ...}> $prescriptions
     * @param array<string, string>                                                                                                             $practitioners
     *
     * @return list<array{type: string, title: string, reference: string, date: string, author: string, id: string}>
     */
    private function documentsView(
        array $consultations,
        array $invoices,
        array $prescriptions,
        array $practitioners,
        \DateTimeZone $tz,
    ): array {
        $documents          = [];
        $invoiceByAdmission = $invoices;

        foreach ($consultations as $consultation) {
            $author = $practitioners[$consultation['practitionerUserId']] ?? '—';

            if ('CLOSED' === $consultation['status']) {
                $documents[] = [
                    'type'      => 'report',
                    'title'     => 'Compte rendu de consultation',
                    'reference' => 'CR-' . mb_strtoupper(mb_substr($consultation['id'], -8)),
                    'sortKey'   => $consultation['startedAt'],
                    'author'    => $author,
                    'id'        => $consultation['id'],
                ];
            }

            $invoice = null === $consultation['admissionId']
                ? null
                : $invoiceByAdmission[$consultation['admissionId']] ?? null;

            if (null !== $invoice) {
                $documents[] = [
                    'type'  => 'invoice',
                    'title' => \sprintf(
                        'Facture%s — %s',
                        $invoice['paid'] ? ' acquittée' : '',
                        SheetText::money($invoice['total'], $invoice['currency']),
                    ),
                    'reference' => $invoice['number'],
                    // Listed with its visit, right after the report it settles.
                    'sortKey' => $consultation['startedAt'],
                    'author'  => 'Facturation',
                    'id'      => $invoice['id'],
                ];
            }
        }

        foreach ($prescriptions as $prescription) {
            $count = \count($prescription['lines']);

            $documents[] = [
                'type'      => 'prescription',
                'title'     => \sprintf('Ordonnance — %s', SheetText::plural($count, 'médicament', 'médicaments')),
                'reference' => $prescription['number'],
                'sortKey'   => $prescription['issuedAt'],
                'author'    => $prescription['prescriber'],
                'id'        => $prescription['id'],
            ];
        }

        usort($documents, static fn (array $a, array $b): int => $b['sortKey'] <=> $a['sortKey']);

        return array_map(static fn (array $document): array => [
            'type'      => $document['type'],
            'title'     => $document['title'],
            'reference' => $document['reference'],
            'date'      => $document['sortKey']->setTimezone($tz)->format('d/m/Y'),
            'author'    => $document['author'],
            'id'        => $document['id'],
        ], $documents);
    }

    /**
     * The sticky strip: what must be known before touching this animal.
     *
     * @param list<array{allergy: bool, label: string, ...}> $alerts
     * @param list<array<string, mixed>>                     $treatments
     * @param array{when: string, date: string, ...}|null    $nextAppointment
     *
     * @return array{pills: list<array{level: string, label: string}>, meta: string}
     */
    private function safety(
        AnimalView $animal,
        array $alerts,
        array $treatments,
        ?\DateTimeImmutable $lastVisit,
        ?array $nextAppointment,
        \DateTimeZone $tz,
    ): array {
        $pills = [];

        if ('deceased' === $animal->lifeCycle->lifeStatus) {
            $pills[] = ['level' => 'info', 'label' => 'Animal décédé — dossier clos'];
        } elseif ('missing' === $animal->lifeCycle->lifeStatus) {
            $pills[] = ['level' => 'warn', 'label' => 'Animal déclaré disparu'];
        }

        if ('active' !== $animal->status) {
            $pills[] = ['level' => 'info', 'label' => 'Fiche archivée'];
        }

        foreach ($alerts as $alert) {
            $pills[] = $alert['allergy']
                ? ['level' => 'crit', 'label' => \sprintf('Allergie %s — à vérifier avant toute prescription', $alert['label'])]
                : ['level' => 'warn', 'label' => $alert['label']];
        }

        if ([] !== $treatments) {
            $pills[] = [
                'level' => 'info',
                'label' => SheetText::plural(\count($treatments), 'traitement en cours', 'traitements en cours'),
            ];
        }

        $meta = array_filter([
            null === $lastVisit
                ? 'Aucune visite'
                : 'Dernière visite le ' . SheetText::date($lastVisit->setTimezone($tz), 'd MMMM y'),
            null === $nextAppointment
                ? null
                : 'prochain rendez-vous le ' . SheetText::date(new \DateTimeImmutable($nextAppointment['date'], $tz), 'd MMMM'),
        ]);

        return ['pills' => $pills, 'meta' => implode(' · ', $meta)];
    }
}
