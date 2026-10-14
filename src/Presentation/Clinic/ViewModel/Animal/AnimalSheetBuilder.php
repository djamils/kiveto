<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Animal;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\ListAnimalPreventiveCare;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\PreventiveCareRecordView;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\ListAnimalWeightRecords;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimal;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Animal\Domain\ValueObject\BodyDisposal;
use App\Context\Animal\Domain\ValueObject\DeathCause;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListItemView;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoices;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal\AnimalAppointmentItem;
use App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal\ListAppointmentsForAnimal;
use App\Presentation\Clinic\ViewModel\Shared\DossierVisitReader;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Gathers everything the animal sheet (animal-v-topbar mock-up) shows, in the
 * shape its page module renders: identity, the medical synthesis (problems,
 * long-term treatments, allergies, behaviour), every visit with its report,
 * ordonnances and invoice, the appointments to come and the missed ones,
 * prevention, the weight / temperature / glycaemia curves and the documents.
 *
 * Each context is asked through its query bus; the facts no query exposes yet
 * (the animal's consultations through its patient files, a consultation's
 * glycaemia readings and plan) are read in one batched statement each, as the
 * client sheet does. The visits themselves are built by the reader both
 * dossiers share.
 */
final readonly class AnimalSheetBuilder
{
    private const int INVOICE_LIMIT = 200;

    /** Avatar of the mock-up's header, by species. */
    private const array EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇', 'other' => '🐾'];

    private const array SPECIES = ['dog' => 'Chien', 'cat' => 'Chat', 'nac' => 'NAC', 'other' => 'Autre'];

    /**
     * What a puppy or a kitten still has to receive when nothing is on file:
     * the usual primo-vaccination protocol, shown « à programmer ».
     */
    private const array FIRST_YEAR = [
        'dog' => [
            ['CHPPiL · primo-vaccination', 'À faire dès 8 semaines, rappel 4 semaines après'],
            ['Antiparasitaire et vermifuge', 'À démarrer à la première visite'],
            ['Rage', 'À partir de 12 semaines, obligatoire pour voyager'],
        ],
        'cat' => [
            ['Typhus et coryza · primo-vaccination', 'À faire dès 8 semaines, rappel 4 semaines après'],
            ['Antiparasitaire et vermifuge', 'À démarrer à la première visite'],
            ['Leucose féline (FeLV)', 'À discuter si le chat sort'],
        ],
    ];

    public function __construct(
        private QueryBusInterface $queryBus,
        private Connection $connection,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
        private DossierVisitReader $visits,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(AnimalView $animal, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $clinicId      = $animal->clinicId;
        $today         = $now->setTimezone($tz);
        $practitioners = $this->visits->practitioners($clinicId);
        $consultations = $this->consultationRows($clinicId, $animal);
        $invoices      = $this->invoiceRows($animal, $consultations);
        $lines         = $this->visits->invoiceLines($invoices);
        $payments      = $this->visits->lastPaymentMethods($invoices);
        $prescriptions = $this->visits->prescriptions($consultations);
        $details       = $this->visits->consultationDetails($consultations);
        $appointments  = $this->appointmentItems($clinicId, $animal->id);
        $open          = null;

        foreach ($consultations as $consultation) {
            if ('OPEN' === $consultation['status']) {
                $open = $consultation;

                break;
            }
        }

        return [
            'today'  => $today->format('Y-m-d'),
            'now'    => $today->format('H:i'),
            'animal' => $this->animalView($animal, $consultations, $open, $tz, $today),
            'visits' => [
                ...$this->visits->consultationVisits($consultations, $invoices, $lines, $payments, $prescriptions, $details, $practitioners, $tz),
                ...$this->appointmentVisits($animal, $appointments, $practitioners, $tz, $now),
            ],
            'extra'  => $this->prescriptionEntries($prescriptions, $tz),
            'ordos'  => $this->visits->ordosView($prescriptions, $tz),
            'prev'   => $this->preventionRows($animal, $tz, $today),
            'series' => $this->series($animal, $consultations, $tz),
            'docs'   => $this->documents($consultations, $details, $prescriptions, $tz),
            'plan'   => null === $open ? null : $this->plan($open['id']),
        ];
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
     *     observations: ?string,
     *     weightKg: ?string,
     *     temperature: ?string,
     *     animalId: string,
     *     animalName: string
     * }>
     */
    private function consultationRows(string $clinicId, AnimalView $animal): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(c.id) AS id, BIN_TO_UUID(c.admission_id) AS admission_id,
                    BIN_TO_UUID(c.practitioner_user_id) AS practitioner_user_id, c.status,
                    c.started_at_utc, c.chief_complaint, c.summary, c.objective_observations,
                    c.weight_kg, c.temperature_c
             FROM patient__patients p
             INNER JOIN consultation__consultations c ON c.patient_id = p.id
             WHERE p.animal_link_id = :animalId AND p.clinic_id = :clinicId AND c.clinic_id = :clinicId
               AND c.status IN ('OPEN', 'CLOSED')
             ORDER BY c.started_at_utc DESC",
            [
                'animalId' => Uuid::fromString($animal->id)->toBinary(),
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
                'observations'       => $this->text($row['objective_observations']),
                'weightKg'           => is_numeric($row['weight_kg']) ? (string) $row['weight_kg'] : null,
                'temperature'        => is_numeric($row['temperature_c']) ? (string) $row['temperature_c'] : null,
                'animalId'           => $animal->id,
                'animalName'         => $animal->name,
            ];
        }

        return $consultations;
    }

    /**
     * The invoices of the animal's visits, whoever paid them: an invoice
     * belongs to the visit, and a former owner may have settled it.
     *
     * @param list<array{admissionId: ?string, ...}> $consultations
     *
     * @return list<InvoiceListRow> newest first, cancelled ones left out
     */
    private function invoiceRows(AnimalView $animal, array $consultations): array
    {
        $admissions = [];

        foreach ($consultations as $consultation) {
            if (null !== $consultation['admissionId']) {
                $admissions[$consultation['admissionId']] = true;
            }
        }

        if ([] === $admissions) {
            return [];
        }

        $payers = [];

        foreach ($animal->ownerships as $ownership) {
            $payers[$ownership->clientId] = true;
        }

        $invoices = [];

        foreach (array_keys($payers) as $payerId) {
            $result = $this->queryBus->ask(new SearchInvoices($animal->clinicId, new SearchInvoicesCriteria(
                payerClientId: $payerId,
                perPage: self::INVOICE_LIMIT,
            )));
            \assert($result instanceof InvoiceListItemView);

            foreach ($result->items as $invoice) {
                if ('CANCELLED' !== $invoice->status && null !== $invoice->admissionId && isset($admissions[$invoice->admissionId])) {
                    $invoices[$invoice->id] = $invoice;
                }
            }
        }

        $invoices = array_values($invoices);
        usort($invoices, static fn (InvoiceListRow $a, InvoiceListRow $b): int => $b->createdAt <=> $a->createdAt);

        return $invoices;
    }

    /**
     * @return list<AnimalAppointmentItem> newest first
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
     * Appointments still to come, and the ones the animal missed.
     *
     * @param list<AnimalAppointmentItem> $appointments
     * @param array<string, string>       $practitioners
     *
     * @return list<array<string, mixed>>
     */
    private function appointmentVisits(AnimalView $animal, array $appointments, array $practitioners, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $utc    = new \DateTimeZone('UTC');
        $visits = [];

        foreach ($appointments as $appointment) {
            $startsAt = new \DateTimeImmutable($appointment->startsAtUtc, $utc);
            $upcoming = 'PLANNED' === $appointment->status && $startsAt >= $now;
            $missed   = 'NO_SHOW' === $appointment->status;

            if (!$upcoming && !$missed) {
                continue;
            }

            $local = $startsAt->setTimezone($tz);

            $visits[] = [
                'id'     => 'r-' . $appointment->id,
                'd'      => $local->format('Y-m-d'),
                'h'      => $local->format('H:i'),
                'a'      => $animal->id,
                'k'      => $this->visits->visitKind([], $appointment->reason),
                't'      => $appointment->reason ?? 'Consultation',
                'v'      => $practitioners[$appointment->practitionerUserId] ?? '—',
                'st'     => $upcoming ? 'avenir' : 'absent',
                'note'   => $appointment->notes ?? ($missed ? 'Non venu, sans prévenir' : null),
                'syn'    => null,
                'mes'    => [],
                'lignes' => null,
                'total'  => 0,
                'fac'    => null,
                'docs'   => [],
                'ordos'  => [],
                'href'   => null,
                'report' => null,
                'appt'   => [
                    'id'     => $appointment->id,
                    'cancel' => $upcoming
                        ? $this->urls->generate('clinic_scheduling_appointment_cancel', ['appointmentId' => $appointment->id])
                        : null,
                    'move' => $this->urls->generate('clinic_scheduling_agenda', ['view' => 'day', 'date' => $local->format('Y-m-d')]),
                    'book' => $this->urls->generate('clinic_scheduling_agenda', ['new' => 1, 'patient' => $animal->name]),
                ],
            ];
        }

        return $visits;
    }

    /**
     * @param list<array{id: string, status: string, startedAt: \DateTimeImmutable, ...}> $consultations
     * @param array{id: string, startedAt: \DateTimeImmutable, ...}|null                  $open
     *
     * @return array<string, mixed>
     */
    private function animalView(AnimalView $animal, array $consultations, ?array $open, \DateTimeZone $tz, \DateTimeImmutable $today): array
    {
        $species  = \array_key_exists($animal->species, self::SPECIES) ? $animal->species : 'other';
        $alive    = 'alive' === $animal->lifeCycle->lifeStatus;
        $active   = 'active' === $animal->status;
        $owner    = $this->owner($animal);
        $created  = (new \DateTimeImmutable($animal->createdAt))->setTimezone($tz);
        $updated  = (new \DateTimeImmutable($animal->updatedAt))->setTimezone($tz);
        $deceased = null;

        if ('deceased' === $animal->lifeCycle->lifeStatus && null !== $animal->lifeCycle->deceasedAt) {
            $cause    = null === $animal->lifeCycle->deathCause ? null : DeathCause::tryFrom($animal->lifeCycle->deathCause);
            $disposal = null === $animal->lifeCycle->bodyDisposal ? null : BodyDisposal::tryFrom($animal->lifeCycle->bodyDisposal);
            $deceased = [
                'd'     => (new \DateTimeImmutable($animal->lifeCycle->deceasedAt))->setTimezone($tz)->format('Y-m-d'),
                'c'     => $cause?->label(),
                'corps' => $disposal?->label(),
            ];
        }

        return [
            'id'      => $animal->id,
            'nom'     => $animal->name,
            'e'       => self::EMOJI[$species],
            'species' => $species,
            'esp'     => self::SPECIES[$species],
            'race'    => $animal->breedName ?? '',
            'robe'    => $animal->color ?? '',
            'sexe'    => match ($animal->sex) {
                'female' => 'F',
                'male'   => 'M',
                default  => 'U',
            },
            'ster'     => 'neutered' === $animal->reproductiveStatus,
            'naiss'    => null === $animal->birthDate ? null : substr($animal->birthDate, 0, 10),
            'puce'     => $animal->identification->microchipNumber ?? '',
            'tattoo'   => $animal->identification->tattooNumber ?? '',
            'passport' => $animal->identification->passportNumber ?? '',
            'assur'    => $animal->insurerName ?? '',
            'assurN'   => $animal->insurancePolicyNumber ?? '',
            'proprio'  => null === $owner ? null : [
                trim(Civility::tryFrom($owner->civility)?->honorific() . ' ' . $owner->fullName()),
                $this->urls->generate('clinic_clients_view', ['id' => $owner->id]),
            ],
            'dcd'      => $deceased,
            'archived' => !$active,
            'editable' => $active && $alive,
            'isNew'    => $active && $alive && [] === $consultations,
            'enCours'  => null === $open ? null : [
                'h'    => $open['startedAt']->setTimezone($tz)->format('H:i'),
                'href' => $this->urls->generate('clinic_consultation_details', ['id' => $open['id']]),
            ],
            ...$this->synthesis($animal),
            'meta' => [
                'id' => $updated->format('Y-m-d H:i') === $created->format('Y-m-d H:i')
                    ? null
                    : SheetText::date($updated, $updated->format('Y') === $today->format('Y') ? 'd MMM' : 'd MMM y') . ' à ' . $updated->format('H:i'),
            ],
            'urls' => [
                'book' => $this->urls->generate('clinic_scheduling_agenda', ['new' => 1, 'patient' => $animal->name]),
                'cons' => null === $open
                    ? $this->urls->generate('clinic_admission_queue', ['arrivee' => 1, 'patient' => $animal->name])
                    : $this->urls->generate('clinic_consultation_details', ['id' => $open['id']]),
            ],
        ];
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
     * The four columns of the medical synthesis, from the alerts on file.
     *
     * @return array{pb: list<array{id: string, l: string, d: ?string}>, trt: list<array{id: string, l: string, d: ?string}>, all: list<array{id: string, l: string, d: ?string}>, comp: list<array{id: string, l: string, d: ?string}>}
     */
    private function synthesis(AnimalView $animal): array
    {
        $views = $this->queryBus->ask(new ListMedicalAlertsForAnimal($animal->clinicId, $animal->id));
        \assert(\is_array($views));

        $columns = ['pb' => [], 'trt' => [], 'all' => [], 'comp' => []];

        foreach ($views as $view) {
            \assert($view instanceof MedicalAlertView);

            $column = match ($view->kind) {
                'ALLERGY'             => 'all',
                'LONG_TERM_TREATMENT' => 'trt',
                'BEHAVIOR'            => 'comp',
                default               => 'pb',
            };

            $columns[$column][] = ['id' => $view->id, 'l' => $view->label, 'd' => $view->note];
        }

        return $columns;
    }

    /**
     * Each ordonnance is also a row of its own in the history, as in the mock-up.
     *
     * @param array<string, list<array{id: string, number: string, issuedAt: \DateTimeImmutable, prescriber: ?string, lines: list<array{0: string, 1: string, 2: string}>, ...}>> $prescriptions
     *
     * @return list<array{id: string, d: string, k: string, t: string, s: string, v: string, ref: string}>
     */
    private function prescriptionEntries(array $prescriptions, \DateTimeZone $tz): array
    {
        $entries = [];

        foreach ($prescriptions as $list) {
            foreach ($list as $prescription) {
                $labels = array_map(static fn (array $line): string => $line[0], $prescription['lines']);

                $entries[] = [
                    'id'  => 'o-' . $prescription['id'],
                    'd'   => $prescription['issuedAt']->setTimezone($tz)->format('Y-m-d'),
                    'k'   => 'ordo',
                    't'   => 'Ordonnance ' . $prescription['number'],
                    's'   => [] === $labels ? 'Aucune ligne' : implode(', ', \array_slice($labels, 0, 2)) . (\count($labels) > 2 ? \sprintf(' + %d', \count($labels) - 2) : ''),
                    'v'   => $prescription['prescriber'] ?? '—',
                    'ref' => $prescription['id'],
                ];
            }
        }

        return $entries;
    }

    /**
     * The latest act of each protocol; a puppy or a kitten with nothing on
     * file gets its first-year protocol to schedule.
     *
     * @return list<array{l: string, p: ?string, der: ?string, proch: ?string, a: ?string}>
     */
    private function preventionRows(AnimalView $animal, \DateTimeZone $tz, \DateTimeImmutable $today): array
    {
        $records = $this->queryBus->ask(new ListAnimalPreventiveCare($animal->clinicId, $animal->id));
        \assert(\is_array($records));

        $latest = [];

        // Records come newest first: the first one seen for a protocol is its latest act.
        foreach ($records as $record) {
            \assert($record instanceof PreventiveCareRecordView);

            $latest[$record->kind . '|' . mb_strtolower($record->label)] ??= $record;
        }

        $order = array_flip(array_map(static fn (PreventiveCareKind $kind): string => $kind->value, PreventiveCareKind::cases()));
        uasort($latest, static fn (PreventiveCareRecordView $a, PreventiveCareRecordView $b): int => [$order[$a->kind] ?? 9, mb_strtolower($a->label)] <=> [$order[$b->kind] ?? 9, mb_strtolower($b->label)]);

        $rows = [];

        foreach ($latest as $record) {
            $kind = PreventiveCareKind::tryFrom($record->kind);

            $rows[] = [
                'l'     => $record->label,
                'p'     => implode(' · ', array_filter([$kind?->label(), null === $record->batchNumber ? null : 'lot ' . $record->batchNumber])),
                'der'   => $record->administeredOn->format('Y-m-d'),
                'proch' => $record->nextDueOn?->format('Y-m-d'),
                'a'     => null,
            ];
        }

        $young = null !== $animal->birthDate
            && new \DateTimeImmutable(substr($animal->birthDate, 0, 10), $tz) > $today->modify('-1 year');

        if ([] === $rows && $young && 'alive' === $animal->lifeCycle->lifeStatus && isset(self::FIRST_YEAR[$animal->species])) {
            foreach (self::FIRST_YEAR[$animal->species] as [$label, $advice]) {
                $rows[] = ['l' => $label, 'p' => null, 'der' => null, 'proch' => null, 'a' => $advice];
            }
        }

        return $rows;
    }

    /**
     * Weights (taken in consultation or on their own), temperatures and
     * glycaemia readings, oldest first, as [Y-m-d, value] points.
     *
     * @param list<array{id: string, startedAt: \DateTimeImmutable, weightKg: ?string, temperature: ?string, ...}> $consultations
     *
     * @return array{poids: list<array{0: string, 1: float}>, temp: list<array{0: string, 1: float}>, gly: list<array{0: string, 1: float}>}
     */
    private function series(AnimalView $animal, array $consultations, \DateTimeZone $tz): array
    {
        $weights = [];
        $temps   = [];
        $dates   = [];

        foreach ($consultations as $consultation) {
            $day                        = $consultation['startedAt']->setTimezone($tz)->format('Y-m-d');
            $dates[$consultation['id']] = $day;

            if (null !== $consultation['weightKg'] && (float) $consultation['weightKg'] > 0) {
                $weights[] = [$day, round((float) $consultation['weightKg'], 2)];
            }

            if (null !== $consultation['temperature']) {
                $temps[] = [$day, round((float) $consultation['temperature'], 1)];
            }
        }

        $records = $this->queryBus->ask(new ListAnimalWeightRecords($animal->clinicId, $animal->id));
        \assert(\is_array($records));

        foreach ($records as $record) {
            \assert($record instanceof WeightRecordView);

            $weights[] = [$record->measuredOn->format('Y-m-d'), round($record->weightGrams / 1000, 2)];
        }

        $glycaemia = [];

        if ([] !== $dates) {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT BIN_TO_UUID(consultation_id) AS consultation_id, value FROM consultation__typed_vitals
                 WHERE consultation_id IN (?) AND type = 'GLYCEMIA' ORDER BY recorded_at_utc ASC, position ASC",
                [array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), array_keys($dates))],
                [ArrayParameterType::BINARY],
            );

            foreach ($rows as $row) {
                \assert(\is_string($row['consultation_id']));

                if (is_numeric($row['value']) && isset($dates[$row['consultation_id']])) {
                    $glycaemia[] = [$dates[$row['consultation_id']], round((float) $row['value'], 1)];
                }
            }
        }

        $byDate = static fn (array $a, array $b): int => $a[0] <=> $b[0];
        usort($weights, $byDate);
        usort($temps, $byDate);
        usort($glycaemia, $byDate);

        return ['poids' => $weights, 'temp' => $temps, 'gly' => $glycaemia];
    }

    /**
     * Reports of the closed consultations and the ordonnances, newest first.
     *
     * @param list<array{id: string, status: string, startedAt: \DateTimeImmutable, reason: ?string, ...}>              $consultations
     * @param array<string, array{motifs: list<string>, diagnosis: ?string, vitals: list<array{0: string, 1: string}>}> $details
     * @param array<string, list<array{id: string, number: string, issuedAt: \DateTimeImmutable, ...}>>                 $prescriptions
     *
     * @return list<array{t: string, d: string, k: string, c: string, fg: string, href: ?string, ordo: ?string}>
     */
    private function documents(array $consultations, array $details, array $prescriptions, \DateTimeZone $tz): array
    {
        $documents = [];

        foreach ($consultations as $consultation) {
            if ('CLOSED' !== $consultation['status']) {
                continue;
            }

            $documents[] = [
                't'    => 'Compte rendu · ' . mb_strtolower($this->visits->visitTitle($details[$consultation['id']]['motifs'] ?? [], $consultation['reason'])),
                'd'    => $consultation['startedAt']->setTimezone($tz)->format('Y-m-d'),
                'k'    => 'doc',
                'c'    => '#eef2ff',
                'fg'   => '#4338ca',
                'href' => $this->urls->generate('clinic_consultation_report_pdf', ['consultationId' => $consultation['id']]),
                'ordo' => null,
            ];

            foreach ($prescriptions[$consultation['id']] ?? [] as $prescription) {
                $documents[] = [
                    't'    => 'Ordonnance ' . $prescription['number'],
                    'd'    => $prescription['issuedAt']->setTimezone($tz)->format('Y-m-d'),
                    'k'    => 'ordo',
                    'c'    => '#f0fdf4',
                    'fg'   => '#15803d',
                    'href' => null,
                    'ordo' => $prescription['id'],
                ];
            }
        }

        usort($documents, static fn (array $a, array $b): int => $b['d'] <=> $a['d']);

        return $documents;
    }

    /**
     * The consultation in progress takes a preventive act into its plan.
     *
     * @return array{url: string, token: string, done: list<string>}
     */
    private function plan(string $consultationId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT description FROM consultation__plan_actions WHERE consultation_id = :id',
            ['id' => Uuid::fromString($consultationId)->toBinary()],
        );

        return [
            'url'   => $this->urls->generate('clinic_consultation_add_plan_action', ['id' => $consultationId]),
            'token' => $this->csrf->getToken('consultation_edit')->getValue(),
            'done'  => array_map(static fn (mixed $d): string => mb_strtolower(\is_string($d) ? $d : ''), $rows),
        ];
    }

    private function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
