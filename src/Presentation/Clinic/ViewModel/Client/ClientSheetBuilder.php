<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Client;

use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimals;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListItemView;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoices;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\ListClientCommunications\ClientCommunicationView;
use App\Context\Client\Application\Query\ListClientCommunications\ListClientCommunications;
use App\Context\Client\Application\Query\ListClientNotes\ClientNoteView;
use App\Context\Client\Application\Query\ListClientNotes\ListClientNotes;
use App\Context\Client\Domain\TaxProfile\TaxProfileWarning;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwner\ListAppointmentsForOwner;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwner\OwnerAppointmentItem;
use App\Presentation\Clinic\ViewModel\Shared\DossierVisitReader;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Gathers everything the client sheet (client-v-topbar mock-up) shows, in the
 * shape its page module renders: the household's animals, every visit —
 * consultations with their report, prescriptions and invoice, counter sales,
 * missed appointments, logged exchanges — the appointments to come, the
 * account, and the rail cards (preferences, authorized person, internal note).
 *
 * The sheet is a read-only crossroads of several contexts. Each one is asked
 * through its query bus; the facts no query exposes yet (which consultation
 * belongs to which animal, a consultation's motifs and diagnosis, an invoice's
 * lines, the prevention due dates) are read in one batched statement each, as
 * the client directory already does.
 */
final readonly class ClientSheetBuilder
{
    private const int ANIMAL_LIMIT  = 100;
    private const int INVOICE_LIMIT = 200;

    /** Emoji of the mock-up's animal cards and history rows, by species. */
    private const array EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private Connection $connection,
        private UrlGeneratorInterface $urls,
        private DossierVisitReader $visits,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(ClientView $client, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $clinicId      = $client->clinicId;
        $today         = $now->setTimezone($tz);
        $practitioners = $this->visits->practitioners($clinicId);
        $animalItems   = $this->animalItems($clinicId, $client->id);
        $appointments  = $this->appointmentItems($clinicId, $client->id);
        $consultations = $this->consultationRows($clinicId, $client->id);
        $invoices      = $this->invoiceRows($clinicId, $client->id);
        $notes         = $this->queryBus->ask(new ListClientNotes($clinicId, $client->id));
        $exchanges     = $this->queryBus->ask(new ListClientCommunications($clinicId, $client->id));
        \assert(\is_array($notes) && \is_array($exchanges));

        $invoiceLines  = $this->visits->invoiceLines($invoices);
        $payments      = $this->visits->lastPaymentMethods($invoices);
        $prescriptions = $this->visits->prescriptions($consultations);
        $details       = $this->visits->consultationDetails($consultations);

        $ordos  = $this->visits->ordosView($prescriptions, $tz);
        $visits = [
            ...$this->visits->consultationVisits($consultations, $invoices, $invoiceLines, $payments, $prescriptions, $details, $practitioners, $tz),
            ...$this->counterSaleVisits($consultations, $invoices, $invoiceLines, $payments, $tz),
            ...$this->appointmentVisits($client, $appointments, $practitioners, $tz, $now),
            ...$this->exchangeVisits($exchanges, $tz),
        ];

        $hasHistory = [] !== $consultations || [] !== array_filter($invoices, static fn (InvoiceListRow $i): bool => null !== $i->number);
        $createdAt  = (new \DateTimeImmutable($client->createdAt))->setTimezone($tz);

        return [
            'today'   => $today->format('Y-m-d'),
            'now'     => $today->format('H:i'),
            'client'  => $this->clientView($client, $tz, $today, $createdAt, $hasHistory),
            'animals' => $this->animalsView($animalItems, $consultations, $appointments, $tz, $now),
            'visits'  => $visits,
            'ordos'   => $ordos,
            'account' => $this->accountView($invoices, $consultations, $tz, $now),
            'prefs'   => $this->prefsView($client),
            'pers'    => $this->personsView($client, $tz, $today),
            'fisc'    => $this->taxProfileView($client, $tz),
            'note'    => $this->noteView($notes, $tz, $today),
            'choices' => $this->choices(),
        ];
    }

    /**
     * @return list<AnimalListItemView>
     */
    private function animalItems(string $clinicId, string $clientId): array
    {
        $result = $this->queryBus->ask(new SearchAnimals(
            clinicId: $clinicId,
            ownerClientId: $clientId,
            limit: self::ANIMAL_LIMIT,
        ));
        \assert(\is_array($result) && \is_array($result['items'] ?? null));

        $items = [];

        foreach ($result['items'] as $item) {
            \assert($item instanceof AnimalListItemView);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return list<OwnerAppointmentItem>
     */
    private function appointmentItems(string $clinicId, string $clientId): array
    {
        $items = $this->queryBus->ask(new ListAppointmentsForOwner($clinicId, $clientId));
        \assert(\is_array($items));

        $appointments = [];

        foreach ($items as $item) {
            \assert($item instanceof OwnerAppointmentItem);
            $appointments[] = $item;
        }

        return $appointments;
    }

    /**
     * Every consultation of the client's animals, newest first.
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
    private function consultationRows(string $clinicId, string $clientId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(c.id) AS id, BIN_TO_UUID(c.admission_id) AS admission_id,
                    BIN_TO_UUID(c.practitioner_user_id) AS practitioner_user_id, c.status,
                    c.started_at_utc, c.chief_complaint, c.summary, c.objective_observations,
                    c.weight_kg, c.temperature_c,
                    BIN_TO_UUID(a.id) AS animal_id, a.name AS animal_name
             FROM animal__ownerships o
             INNER JOIN animal__animals a ON a.id = o.animal_id
             INNER JOIN patient__patients p ON p.animal_link_id = a.id
             INNER JOIN consultation__consultations c ON c.patient_id = p.id
             WHERE o.client_id = :clientId AND o.status = 'active' AND c.clinic_id = :clinicId
               AND c.status IN ('OPEN', 'CLOSED')
             ORDER BY c.started_at_utc DESC",
            [
                'clientId' => Uuid::fromString($clientId)->toBinary(),
                'clinicId' => Uuid::fromString($clinicId)->toBinary(),
            ],
        );

        $utc           = new \DateTimeZone('UTC');
        $consultations = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']) && \is_string($row['practitioner_user_id']));
            \assert(\is_string($row['status']) && \is_string($row['started_at_utc']));
            \assert(\is_string($row['animal_id']) && \is_string($row['animal_name']));

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
                'animalId'           => $row['animal_id'],
                'animalName'         => $row['animal_name'],
            ];
        }

        return $consultations;
    }

    /**
     * @return list<InvoiceListRow> newest first, cancelled ones left out
     */
    private function invoiceRows(string $clinicId, string $clientId): array
    {
        $result = $this->queryBus->ask(new SearchInvoices($clinicId, new SearchInvoicesCriteria(
            payerClientId: $clientId,
            perPage: self::INVOICE_LIMIT,
        )));
        \assert($result instanceof InvoiceListItemView);

        return array_values(array_filter(
            $result->items,
            static fn (InvoiceListRow $invoice): bool => 'CANCELLED' !== $invoice->status,
        ));
    }

    /**
     * Invoices that belong to no consultation of the file: sales at the desk.
     *
     * @param list<array{admissionId: ?string, ...}>                    $consultations
     * @param list<InvoiceListRow>                                      $invoices
     * @param array<string, list<array{0: string, 1: float, 2: float}>> $lines
     * @param array<string, string>                                     $payments
     *
     * @return list<array<string, mixed>>
     */
    private function counterSaleVisits(array $consultations, array $invoices, array $lines, array $payments, \DateTimeZone $tz): array
    {
        $visited = [];

        foreach ($consultations as $consultation) {
            if (null !== $consultation['admissionId']) {
                $visited[$consultation['admissionId']] = true;
            }
        }

        $visits = [];

        foreach ($invoices as $invoice) {
            if (null === $invoice->number || null === $invoice->issuedAt) {
                continue;
            }

            if (null !== $invoice->admissionId && isset($visited[$invoice->admissionId])) {
                continue;
            }

            $issuedAt = $invoice->issuedAt->setTimezone($tz);

            $visits[] = [
                'id'     => 'f-' . $invoice->id,
                'd'      => $issuedAt->format('Y-m-d'),
                'h'      => $issuedAt->format('H:i'),
                'a'      => null,
                'k'      => 'vente',
                't'      => 'Achat au comptoir',
                'v'      => null,
                'st'     => 'venu',
                'syn'    => null,
                'mes'    => [],
                'lignes' => $lines[$invoice->id] ?? [],
                'total'  => $invoice->totalGrossMinorUnits / 100,
                'fac'    => $this->visits->facView($invoice, $payments, $tz),
                'docs'   => [],
                'ordos'  => [],
                'href'   => null,
                'report' => null,
            ];
        }

        return $visits;
    }

    /**
     * Appointments still to come, and the ones the client missed.
     *
     * @param list<OwnerAppointmentItem> $appointments
     * @param array<string, string>      $practitioners
     *
     * @return list<array<string, mixed>>
     */
    private function appointmentVisits(ClientView $client, array $appointments, array $practitioners, \DateTimeZone $tz, \DateTimeImmutable $now): array
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
                'a'      => $appointment->animalId,
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
                    'book' => $this->urls->generate('clinic_scheduling_agenda', ['new' => 1, 'patient' => $client->lastName]),
                ],
            ];
        }

        return $visits;
    }

    /**
     * Calls, messages and letters logged by the team.
     *
     * @param array<mixed> $exchanges
     *
     * @return list<array<string, mixed>>
     */
    private function exchangeVisits(array $exchanges, \DateTimeZone $tz): array
    {
        $visits = [];

        foreach ($exchanges as $exchange) {
            \assert($exchange instanceof ClientCommunicationView);

            $at = $exchange->occurredAt->setTimezone($tz);

            $visits[] = [
                'id'     => 'm-' . $exchange->id,
                'd'      => $at->format('Y-m-d'),
                'h'      => $at->format('H:i'),
                'a'      => null,
                'k'      => 'comm',
                't'      => $exchange->subject,
                'v'      => $exchange->authorName,
                'st'     => 'venu',
                'note'   => $exchange->body,
                'canal'  => ContactChannel::from($exchange->channel)->label(),
                'sens'   => CommunicationDirection::from($exchange->direction)->label(),
                'syn'    => null,
                'mes'    => [],
                'lignes' => null,
                'total'  => 0,
                'fac'    => null,
                'docs'   => [],
                'ordos'  => [],
                'href'   => null,
                'report' => null,
            ];
        }

        return $visits;
    }

    /**
     * @return array<string, mixed>
     */
    private function clientView(ClientView $client, \DateTimeZone $tz, \DateTimeImmutable $today, \DateTimeImmutable $createdAt, bool $hasHistory): array
    {
        $slots   = ClientContactSlots::fromContactMethods($client->contactMethods);
        $address = $client->postalAddress;
        $civ     = Civility::tryFrom($client->civility) ?? Civility::UNSPECIFIED;
        $updated = (new \DateTimeImmutable($client->updatedAt))->setTimezone($tz);

        return [
            'id'           => $client->id,
            'civ'          => $civ->honorific(),
            'civility'     => $civ->value,
            'prenom'       => $client->firstName,
            'nom'          => $client->lastName,
            'rs'           => $client->legalName,
            'archived'     => 'archived' === $client->status,
            'isNew'        => !$hasHistory && $createdAt > $today->modify('-30 days'),
            'createdToday' => $createdAt->format('Y-m-d') === $today->format('Y-m-d'),
            'depuis'       => $createdAt->format('Y-m-d'),
            'adr1'         => $address->streetLine1 ?? '',
            'adr2'         => $address->streetLine2 ?? '',
            'cp'           => $address->postalCode ?? '',
            'ville'        => $address->city ?? '',
            'mob'          => $this->nationalPhone($slots->mobile?->value),
            'fixe'         => $this->nationalPhone($slots->landline?->value),
            'mail'         => $slots->email->value ?? '',
            'metaCo'       => $updated === $createdAt ? null : $this->when($updated, $today) . ' à ' . $updated->format('H:i'),
            'book'         => $this->urls->generate('clinic_scheduling_agenda', ['new' => 1, 'patient' => $client->lastName]),
        ];
    }

    /**
     * The « Profil fiscal » card: the shared block's values (`fisc`, `rs`,
     * `siren`, `tva`), their display (« 523 456 788 », « FR 31 523456788 »)
     * and the card's status (`t`: part|ent|pub|etr, `ass`: liable to VAT).
     *
     * The other keys are what the card shows once the server knows them:
     * the statuses it can store (`statuts`), the establishment (`siret`),
     * the country and service of a foreign client or public body (`pays`,
     * `svc`), the directory record (`ent`) with its e-invoicing address
     * (`ae`) and the establishments already in file (`clientsSiret`), the
     * VIES answer (`vies`: true valid, false invalid, null otherwise) and
     * when the registry was last read (`verif`, « 12/11 à 08:12 »), who
     * changed the profile and when (`meta`), whether changes go into the
     * history (`trace`), whether e-invoicing is live (`efacture`), the
     * registry search (`annuaireUrl`), « Revérifier » (`verifyUrl`) and the
     * verification itself (`verification`: outcomes, official name, pending,
     * warnings with their French label). What the server does not know yet
     * stays empty and the card shows those parts as coming soon.
     *
     * @return array{t: string, ass: bool, fisc: string, rs: string, siren: string, tva: string, sirenAff: ?string, tvaAff: ?string, dblUrl: string, statuts: list<string>, siret: null, pays: null, svc: null, ae: null, ent: null, clientsSiret: array<string, array{n: string, url: string}>, vies: ?bool, verif: ?string, meta: null, trace: bool, efacture: bool, annuaireUrl: string, verifyUrl: ?string, verification: ?array{company: string, vat: string, registryName: ?string, pending: bool, warnings: list<array{code: string, label: string}>}}
     */
    private function taxProfileView(ClientView $client, \DateTimeZone $tz): array
    {
        $pro = 'french_business' === $client->taxStatus;

        return [
            't'            => $pro || null !== $client->legalName ? 'ent' : 'part',
            'ass'          => $pro,
            'fisc'         => $pro ? TaxProfileInput::PROFESSIONAL : TaxProfileInput::INDIVIDUAL,
            'rs'           => $client->legalName ?? '',
            'siren'        => $client->siren ?? '',
            'tva'          => $client->vatNumber ?? '',
            'sirenAff'     => null !== $client->siren ? implode(' ', str_split($client->siren, 3)) : null,
            'tvaAff'       => null !== $client->vatNumber ? \sprintf('%s %s %s', substr($client->vatNumber, 0, 2), substr($client->vatNumber, 2, 2), substr($client->vatNumber, 4)) : null,
            'dblUrl'       => $this->urls->generate('clinic_clients_by_siren'),
            'statuts'      => ['part', 'ent'],
            'siret'        => null,
            'pays'         => null,
            'svc'          => null,
            'ae'           => null,
            'ent'          => null,
            'clientsSiret' => [],
            'vies'         => match ($client->taxVatCheck) {
                VatCheckOutcome::VALID->value   => true,
                VatCheckOutcome::INVALID->value => false,
                default                         => null,
            },
            'verif' => null === $client->taxCompanyCheckedAt
                ? null
                : (new \DateTimeImmutable($client->taxCompanyCheckedAt))->setTimezone($tz)->format('d/m à H:i'),
            'meta'         => null,
            'trace'        => false,
            'efacture'     => false,
            'annuaireUrl'  => $this->urls->generate('clinic_clients_company_registry_search'),
            'verifyUrl'    => $pro ? $this->urls->generate('clinic_clients_tax_profile_verify', ['id' => $client->id]) : null,
            'verification' => $pro ? [
                'company'      => $client->taxCompanyCheck,
                'vat'          => $client->taxVatCheck,
                'registryName' => $client->taxRegistryName,
                'pending'      => $client->taxVerificationPending,
                'warnings'     => array_values(array_filter(array_map(
                    static fn (string $code): ?array => null === ($warning = TaxProfileWarning::tryFrom($code)) ? null : ['code' => $code, 'label' => $warning->label()],
                    $client->taxWarnings,
                ))),
            ] : null,
        ];
    }

    /**
     * @param list<AnimalListItemView>                                                                                $items
     * @param list<array{animalId: string, startedAt: \DateTimeImmutable, status: string, admissionId: ?string, ...}> $consultations
     * @param list<OwnerAppointmentItem>                                                                              $appointments
     *
     * @return list<array<string, mixed>>
     */
    private function animalsView(array $items, array $consultations, array $appointments, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $alerts     = $this->alertsPerAnimal($items);
        $facts      = $this->animalFacts($items);
        $prevention = $this->preventionPerAnimal($items);
        $today      = $now->setTimezone($tz);
        $utc        = new \DateTimeZone('UTC');
        $animals    = [];

        foreach ($items as $item) {
            $kind     = \in_array($item->species, ['dog', 'cat'], true) ? $item->species : 'nac';
            $deceased = 'deceased' === $item->lifeStatus;
            $fact     = $facts[$item->id] ?? ['reproductive' => 'unknown', 'deceasedAt' => null];

            $lastVisit = null;
            $open      = false;

            foreach ($consultations as $consultation) {
                if ($consultation['animalId'] !== $item->id) {
                    continue;
                }

                // Newest first: the first hit is the latest visit.
                $lastVisit ??= $consultation['startedAt']->setTimezone($tz);
                $open = $open || 'OPEN' === $consultation['status'];
            }

            $missedOn = null;

            foreach ($appointments as $appointment) {
                if ($appointment->animalId !== $item->id) {
                    continue;
                }

                $startsAt = new \DateTimeImmutable($appointment->startsAtUtc, $utc);

                // Newest first: only the latest settled appointment tells a missed visit.
                if ($startsAt < $now && \in_array($appointment->status, ['COMPLETED', 'NO_SHOW'], true)) {
                    $missedOn = 'NO_SHOW' === $appointment->status ? $startsAt->setTimezone($tz) : null;

                    break;
                }
            }

            // A visit since then settles the missed one.
            if (null !== $missedOn && null !== $lastVisit && $lastVisit > $missedOn) {
                $missedOn = null;
            }

            if ($deceased) {
                $animals[] = [
                    'id'  => $item->id,
                    'e'   => self::EMOJI[$kind],
                    'nom' => $item->name,
                    'd'   => implode(' · ', array_filter([
                        $this->speciesNoun($kind, $item->sex, $item->breedName),
                        null === $fact['deceasedAt'] ? 'décédé' : 'décédé le ' . SheetText::date($fact['deceasedAt']->setTimezone($tz), 'd MMM y'),
                    ])),
                    'dcd'      => true,
                    'archived' => 'active' !== $item->status,
                    'tags'     => [],
                    'kv'       => [],
                    'href'     => $this->urls->generate('clinic_animals_view', ['id' => $item->id]),
                ];

                continue;
            }

            $tags = [];

            if ($open) {
                $tags[] = ['vig b', 'En consultation'];
            }

            // The card names the allergies and the first problem on file; treatments and
            // behaviour belong to the animal's own synthesis.
            $problem = false;

            foreach ($alerts[$item->id] ?? [] as $alert) {
                if ('ALLERGY' === $alert['kind']) {
                    $tags[] = ['vig r', 'Allergie : ' . $alert['label']];
                } elseif ('CHRONIC_CONDITION' === $alert['kind'] && !$problem) {
                    $tags[]  = ['vig r', $alert['label']];
                    $problem = true;
                }
            }

            if ('active' !== $item->status) {
                $tags[] = ['vig g', 'Archivé'];
            } elseif (null === $lastVisit && null === $missedOn) {
                $tags[] = ['vig g', 'Nouveau patient'];
            } elseif (null !== $missedOn) {
                $tags[] = ['vig g', 'Non venu le ' . $this->when($missedOn, $today)];
            }

            $next = $prevention[$item->id] ?? null;

            $animals[] = [
                'id'  => $item->id,
                'e'   => self::EMOJI[$kind],
                'nom' => $item->name,
                'd'   => implode(' · ', array_filter([
                    // For a NAC the breed names the animal (« Lapin bélier »).
                    'nac' === $kind ? $this->speciesNoun($kind, $item->sex, $item->breedName) : $this->speciesNoun($kind, $item->sex, null),
                    'nac' === $kind || null === $item->breedName ? null : SheetText::sentence(mb_strtolower($item->breedName)),
                    $this->age($item->birthDate, $today),
                ])),
                'dcd'      => false,
                'archived' => 'active' !== $item->status,
                'tags'     => $tags,
                'kv'       => [
                    ['Dernière visite', match (true) {
                        $open               => 'Aujourd’hui',
                        null === $lastVisit => 'female' === $item->sex ? 'Jamais venue' : 'Jamais venu',
                        default             => $lastVisit->format('Y-m-d') === $today->format('Y-m-d') ? 'Aujourd’hui' : $this->when($lastVisit, $today),
                    }, false],
                    null === $next
                        ? $this->noPrevention($kind, $item->birthDate, $today)
                        : ['Prévention', $next['due'] < $today->format('Y-m-d')
                            ? $next['noun'] . ' en retard'
                            : $next['noun'] . ' le ' . $this->when(new \DateTimeImmutable($next['due'], $tz), $today), $next['due'] < $today->format('Y-m-d')],
                ],
                'href' => $this->urls->generate('clinic_animals_view', ['id' => $item->id]),
            ];
        }

        // The one in consultation leads, then the living, active animals, then
        // archived ones, the departed last; alphabetical inside each group.
        usort($animals, static fn (array $a, array $b): int => [$a['dcd'], $a['archived'], 'En consultation' !== ($a['tags'][0][1] ?? null)]
            <=> [$b['dcd'], $b['archived'], 'En consultation' !== ($b['tags'][0][1] ?? null)]);

        return $animals;
    }

    /**
     * The age as the mock-up words it: weeks under three months, months under
     * a year, « 1 an et 4 mois » in the second year, whole years after.
     */
    private function age(?string $birthDate, \DateTimeImmutable $today): ?string
    {
        if (null === $birthDate) {
            return null;
        }

        $birth  = new \DateTimeImmutable(substr($birthDate, 0, 10), $today->getTimezone());
        $diff   = $birth->diff($today);
        $months = $diff->y * 12 + $diff->m;

        return match (true) {
            $months < 3   => SheetText::plural(intdiv((int) $diff->days, 7), 'semaine', 'semaines'),
            $months >= 24 => $diff->y . ' ans',
            $months >= 12 => '1 an' . (0 === $diff->m ? '' : \sprintf(' et %d mois', $diff->m)),
            default       => $months . ' mois',
        };
    }

    /**
     * Nothing due on file: a dog or a cat in its first year has its first
     * vaccinations to come; otherwise nothing is planned.
     *
     * @return array{0: string, 1: string, 2: bool}
     */
    private function noPrevention(string $kind, ?string $birthDate, \DateTimeImmutable $today): array
    {
        $young = null !== $birthDate
            && new \DateTimeImmutable(substr($birthDate, 0, 10), $today->getTimezone()) > $today->modify('-1 year');

        return 'nac' !== $kind && $young
            ? ['Prévention', 'Primo-vaccination à faire', true]
            : ['Prévention', 'Aucun rappel prévu', false];
    }

    private function speciesNoun(string $kind, string $sex, ?string $breed): string
    {
        $female = 'female' === $sex;

        return match ($kind) {
            'dog'   => ($female ? 'Chienne' : 'Chien') . (null === $breed ? '' : ' ' . mb_strtolower($breed)),
            'cat'   => ($female ? 'Chatte' : 'Chat') . (null === $breed ? '' : ' ' . mb_strtolower($breed)),
            default => null === $breed ? 'NAC' : SheetText::sentence(mb_strtolower($breed)),
        };
    }

    /**
     * @param list<AnimalListItemView> $animals
     *
     * @return array<string, list<array{kind: string, label: string}>>
     */
    private function alertsPerAnimal(array $animals): array
    {
        if ([] === $animals) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(animal_id) AS animal_id, kind, label
             FROM animal__medical_alerts
             WHERE animal_id IN (?)
             ORDER BY kind ASC, id ASC',
            [$this->binaryIds($animals)],
            [ArrayParameterType::BINARY],
        );

        $alerts = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['animal_id']) && \is_string($row['kind']) && \is_string($row['label']));

            $alerts[$row['animal_id']][] = ['kind' => $row['kind'], 'label' => $row['label']];
        }

        return $alerts;
    }

    /**
     * @param list<AnimalListItemView> $animals
     *
     * @return array<string, array{reproductive: string, deceasedAt: ?\DateTimeImmutable}>
     */
    private function animalFacts(array $animals): array
    {
        if ([] === $animals) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS id, reproductive_status, deceased_at FROM animal__animals WHERE id IN (?)',
            [$this->binaryIds($animals)],
            [ArrayParameterType::BINARY],
        );

        $facts = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']) && \is_string($row['reproductive_status']));

            $facts[$row['id']] = [
                'reproductive' => $row['reproductive_status'],
                'deceasedAt'   => \is_string($row['deceased_at']) ? new \DateTimeImmutable($row['deceased_at']) : null,
            ];
        }

        return $facts;
    }

    /**
     * The next preventive care due per animal: the soonest due date among the
     * latest record of each care (a renewed vaccine replaces the older one).
     *
     * @param list<AnimalListItemView> $animals
     *
     * @return array<string, array{noun: string, due: string}>
     */
    private function preventionPerAnimal(array $animals): array
    {
        if ([] === $animals) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(animal_id) AS animal_id, kind, label, next_due_on
             FROM animal__preventive_care_records
             WHERE animal_id IN (?)
             ORDER BY administered_on DESC, recorded_at DESC',
            [$this->binaryIds($animals)],
            [ArrayParameterType::BINARY],
        );

        $seen = [];
        $next = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['animal_id']) && \is_string($row['kind']) && \is_string($row['label']));

            $key = $row['animal_id'] . '|' . $row['kind'] . '|' . mb_strtolower($row['label']);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (!\is_string($row['next_due_on'])) {
                continue;
            }

            $due  = substr($row['next_due_on'], 0, 10);
            $noun = match ($row['kind']) {
                'vaccination'       => 'Vaccin',
                'internal_parasite' => 'Vermifuge',
                'external_parasite' => 'Antiparasitaire',
                default             => SheetText::sentence($row['label']),
            };

            if (!isset($next[$row['animal_id']]) || $due < $next[$row['animal_id']]['due']) {
                $next[$row['animal_id']] = ['noun' => $noun, 'due' => $due];
            }
        }

        return $next;
    }

    /**
     * @param list<AnimalListItemView> $animals
     *
     * @return list<string>
     */
    private function binaryIds(array $animals): array
    {
        return array_map(
            static fn (AnimalListItemView $animal): string => Uuid::fromString($animal->id)->toBinary(),
            $animals,
        );
    }

    /**
     * The « Compte » card: what is owed, what is being billed, what was paid this year.
     *
     * @param list<InvoiceListRow>                                                         $invoices
     * @param list<array{admissionId: ?string, animalName: string, animalId: string, ...}> $consultations
     *
     * @return array{due: float, dueCount: int, current: float, year: float, unpaid: list<array<string, mixed>>}
     */
    private function accountView(array $invoices, array $consultations, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $animalByAdmission = [];

        foreach ($consultations as $consultation) {
            if (null !== $consultation['admissionId']) {
                $animalByAdmission[$consultation['admissionId']] ??= $consultation;
            }
        }

        $due     = 0;
        $current = 0;
        $year    = 0;
        $unpaid  = [];
        $yearAgo = $now->modify('-12 months');

        foreach ($invoices as $invoice) {
            if (null === $invoice->number || null === $invoice->issuedAt) {
                $current += $invoice->totalGrossMinorUnits;

                continue;
            }

            if ($invoice->issuedAt >= $yearAgo) {
                $year += $invoice->amountPaidMinorUnits;
            }

            if ($invoice->balanceDueMinorUnits <= 0) {
                continue;
            }

            $due += $invoice->balanceDueMinorUnits;
            $visit = null === $invoice->admissionId ? null : $animalByAdmission[$invoice->admissionId] ?? null;

            $unpaid[] = [
                'visit'        => null === $visit ? 'f-' . $invoice->id : null,
                'invoiceId'    => $invoice->id,
                'num'          => $invoice->number,
                'nom'          => $visit['animalName'] ?? 'Comptoir',
                'amount'       => $invoice->balanceDueMinorUnits / 100,
                'balanceInput' => number_format($invoice->balanceDueMinorUnits / 100, 2, ',', ''),
                'd'            => $invoice->issuedAt->setTimezone($tz)->format('Y-m-d'),
                'detail'       => $invoice->amountPaidMinorUnits > 0
                    ? \sprintf('Réglée en partie (%s)', SheetText::money($invoice->amountPaidMinorUnits, $invoice->currency))
                    : \sprintf('Impayée depuis %d j', max(0, (int) $invoice->issuedAt->diff($now)->days)),
                'label' => \sprintf('%s · %s', $invoice->number, $visit['animalName'] ?? 'comptoir'),
            ];
        }

        // Oldest debt first, as the mock-up lists what is owed.
        usort($unpaid, static fn (array $a, array $b): int => $a['d'] <=> $b['d']);

        return [
            'due'      => $due / 100,
            'dueCount' => \count($unpaid),
            'current'  => $current / 100,
            'year'     => $year / 100,
            'unpaid'   => $unpaid,
        ];
    }

    /**
     * The three switches of the « Préférences » card read the consents on file:
     * SMS appointment reminders, documents by e-mail, payment reminders.
     *
     * @return array{sms: bool, mailFac: bool, relance: bool, consents: array<string, list<string>>, preferred: ?string}
     */
    private function prefsView(ClientView $client): array
    {
        $consents = [];

        foreach (ConsentKind::cases() as $kind) {
            $consent = $client->consentFor($kind->value);

            if (null !== $consent) {
                $consents[$kind->value] = $consent->channels;
            }
        }

        return [
            'sms'       => \in_array('sms', $consents[ConsentKind::APPOINTMENT_REMINDERS->value] ?? [], true),
            'mailFac'   => \in_array('email', $consents[ConsentKind::CLINICAL_DOCUMENTS->value] ?? [], true),
            'relance'   => [] !== ($consents[ConsentKind::BILLING->value] ?? []),
            'consents'  => $consents,
            'preferred' => $client->preferredChannel,
        ];
    }

    /**
     * @return array{list: list<array<string, string>>, meta: ?string}
     */
    private function personsView(ClientView $client, \DateTimeZone $tz, \DateTimeImmutable $today): array
    {
        $person = $client->authorizedPerson;

        if (null === $person) {
            return ['list' => [], 'meta' => null];
        }

        $civility = Civility::tryFrom($person->civility) ?? Civility::UNSPECIFIED;

        return [
            'list' => [[
                'n'        => trim($civility->honorific() . ' ' . $person->firstName . ' ' . $person->lastName),
                'ini'      => mb_strtoupper(mb_substr($person->firstName, 0, 1) . mb_substr($person->lastName, 0, 1)),
                'l'        => (AuthorizedPersonRelationship::tryFrom($person->relationship) ?? AuthorizedPersonRelationship::OTHER)->label(),
                'tel'      => $this->nationalPhone($person->phoneNumber),
                'civility' => $civility->value,
                'prenom'   => $person->firstName,
                'nomP'     => $person->lastName,
                'lien'     => $person->relationship,
            ]],
            'meta' => $this->when((new \DateTimeImmutable($person->authorizedAt))->setTimezone($tz), $today),
        ];
    }

    /**
     * The card shows the latest internal note; saving writes a new one.
     *
     * @param array<mixed> $notes newest first
     *
     * @return array{text: string, meta: ?string}
     */
    private function noteView(array $notes, \DateTimeZone $tz, \DateTimeImmutable $today): array
    {
        $latest = $notes[0] ?? null;

        if (!$latest instanceof ClientNoteView) {
            return ['text' => '', 'meta' => null];
        }

        return [
            'text' => $latest->content,
            'meta' => $this->when($latest->createdAt->setTimezone($tz), $today) . ' · ' . $latest->authorName,
        ];
    }

    /**
     * @return array{relationships: list<array{v: string, l: string}>}
     */
    private function choices(): array
    {
        $relationships = [];

        foreach (AuthorizedPersonRelationship::cases() as $case) {
            $relationships[] = ['v' => $case->value, 'l' => $case->label()];
        }

        return ['relationships' => $relationships];
    }

    /** « 12 mars » this year, « 12 mars 2025 » before. */
    private function when(\DateTimeImmutable $date, \DateTimeImmutable $today): string
    {
        return SheetText::date($date, $date->format('Y') === $today->format('Y') ? 'd MMM' : 'd MMM y');
    }

    /** « +33612345678 » → « 06 12 34 56 78 »; foreign numbers stay as they are. */
    private function nationalPhone(?string $value): string
    {
        if (null === $value) {
            return '';
        }

        $compact = preg_replace('/[^\d+]/', '', $value) ?? $value;

        if (1 === preg_match('/^\+33([1-9]\d{8})$/', $compact, $m)) {
            return trim(chunk_split('0' . $m[1], 2, ' '));
        }

        return $value;
    }

    private function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
