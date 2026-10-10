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
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwner\ListAppointmentsForOwner;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwner\OwnerAppointmentItem;
use App\Shared\Application\Bus\QueryBusInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Gathers everything the client sheet shows, section by section.
 *
 * The sheet is a read-only crossroads of six contexts. Each one is asked
 * through its query bus; the few facts no query exposes yet (which consultation
 * belongs to which animal, the prescriptions of a consultation list) are read
 * in one batched statement each, as the client directory already does.
 */
final readonly class ClientSheetBuilder
{
    private const int ANIMAL_LIMIT  = 100;
    private const int INVOICE_LIMIT = 200;

    public function __construct(
        private QueryBusInterface $queryBus,
        private Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(ClientView $client, \DateTimeZone $clinicTz, \DateTimeImmutable $now): array
    {
        $clinicId      = $client->clinicId;
        $practitioners = $this->practitioners($clinicId);
        $animalItems   = $this->animalItems($clinicId, $client->id);
        $appointments  = $this->appointmentItems($clinicId, $client->id);
        $consultations = $this->consultationRows($clinicId, $client->id);
        $invoices      = $this->invoiceRows($clinicId, $client->id);
        $prescriptions = $this->prescriptionRows($consultations);
        $notes         = $this->queryBus->ask(new ListClientNotes($clinicId, $client->id));
        $exchanges     = $this->queryBus->ask(new ListClientCommunications($clinicId, $client->id));
        \assert(\is_array($notes) && \is_array($exchanges));

        $contact      = $this->contactView($client);
        $rdv          = $this->appointmentsView($appointments, $practitioners, $clinicTz, $now);
        $billing      = $this->billingView($invoices, $consultations, $clinicTz, $now);
        $consultView  = $this->consultationsView($consultations, $invoices, $prescriptions, $practitioners, $clinicTz);
        $consentsView = $this->consentsView($client, $clinicTz);
        $animals      = $this->animalsView($animalItems, $consultations, $appointments, $clinicTz, $now);
        $activeCount  = \count(array_filter($animals, static fn (array $a): bool => !$a['archived']));

        return [
            'contact'          => $contact,
            'since'            => $this->sinceLabel($client, $clinicTz, $now),
            'openedOn'         => (new \DateTimeImmutable($client->createdAt))->setTimezone($clinicTz)->format('d/m/Y'),
            'animals'          => $animals,
            'practitioners'    => $practitioners,
            'appointments'     => $rdv,
            'authorizedPerson' => $this->authorizedPersonView($client, $clinicTz),
            'consents'         => $consentsView,
            'consultations'    => $consultView,
            'billing'          => $billing,
            'communications'   => $this->communicationsView($exchanges, $clinicTz),
            'notes'            => $this->notesView($notes, $clinicTz),
            'documents'        => $this->documentsView($consultations, $invoices, $prescriptions, $clinicTz),
            'checklist'        => $this->checklist($client, $contact, $consentsView, $animals),
            'safety'           => $this->safety($client, $billing, $consentsView, $activeCount, $consultations),
            'choices'          => $this->choices(),
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
     *     closedAt: ?\DateTimeImmutable,
     *     reason: ?string,
     *     weightKg: ?string,
     *     animalId: string,
     *     animalName: string
     * }>
     */
    private function consultationRows(string $clinicId, string $clientId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(c.id) AS id, BIN_TO_UUID(c.admission_id) AS admission_id,
                    BIN_TO_UUID(c.practitioner_user_id) AS practitioner_user_id, c.status,
                    c.started_at_utc, c.closed_at_utc, c.chief_complaint, c.weight_kg,
                    BIN_TO_UUID(a.id) AS animal_id, a.name AS animal_name
             FROM animal__ownerships o
             INNER JOIN animal__animals a ON a.id = o.animal_id
             INNER JOIN patient__patients p ON p.animal_link_id = a.id
             INNER JOIN consultation__consultations c ON c.patient_id = p.id
             WHERE o.client_id = :clientId AND o.status = 'active' AND c.clinic_id = :clinicId
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

            $weight = $row['weight_kg'];

            $consultations[] = [
                'id'                 => $row['id'],
                'admissionId'        => \is_string($row['admission_id']) ? $row['admission_id'] : null,
                'practitionerUserId' => $row['practitioner_user_id'],
                'status'             => $row['status'],
                'startedAt'          => new \DateTimeImmutable($row['started_at_utc'], $utc),
                'closedAt'           => \is_string($row['closed_at_utc'])
                    ? new \DateTimeImmutable($row['closed_at_utc'], $utc)
                    : null,
                'reason' => \is_string($row['chief_complaint']) && '' !== trim($row['chief_complaint'])
                    ? $row['chief_complaint']
                    : null,
                'weightKg'   => \is_string($weight) || \is_float($weight) || \is_int($weight) ? (string) $weight : null,
                'animalId'   => $row['animal_id'],
                'animalName' => $row['animal_name'],
            ];
        }

        return $consultations;
    }

    /**
     * @return list<InvoiceListRow>
     */
    private function invoiceRows(string $clinicId, string $clientId): array
    {
        $result = $this->queryBus->ask(new SearchInvoices($clinicId, new SearchInvoicesCriteria(
            payerClientId: $clientId,
            perPage: self::INVOICE_LIMIT,
        )));
        \assert($result instanceof InvoiceListItemView);

        return $result->items;
    }

    /**
     * Issued prescriptions of the listed consultations, in one query.
     *
     * @param list<array{id: string, ...}> $consultations
     *
     * @return array<string, list<array{id: string, number: string, issuedAt: \DateTimeImmutable, lineCount: int}>>
     */
    private function prescriptionRows(array $consultations): array
    {
        if ([] === $consultations) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(pr.id) AS id, BIN_TO_UUID(pr.consultation_id) AS consultation_id,
                    pr.number, pr.issued_at,
                    (SELECT COUNT(*) FROM prescription__prescription_lines l WHERE l.prescription_id = pr.id) AS line_count
             FROM prescription__prescriptions pr
             WHERE pr.consultation_id IN (?) AND pr.status = 'ISSUED' AND pr.number IS NOT NULL
             ORDER BY pr.issued_at DESC",
            [array_map(static fn (array $c): string => Uuid::fromString($c['id'])->toBinary(), $consultations)],
            [ArrayParameterType::BINARY],
        );

        $prescriptions = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']) && \is_string($row['consultation_id']));
            \assert(\is_string($row['number']) && \is_string($row['issued_at']));

            $prescriptions[$row['consultation_id']][] = [
                'id'        => $row['id'],
                'number'    => $row['number'],
                'issuedAt'  => new \DateTimeImmutable($row['issued_at']),
                'lineCount' => is_numeric($row['line_count']) ? (int) $row['line_count'] : 0,
            ];
        }

        return $prescriptions;
    }

    /**
     * @return array{
     *     mobile: ?string,
     *     landline: ?string,
     *     email: ?string,
     *     address: ?string,
     *     street: string,
     *     postalCode: string,
     *     city: string
     * }
     */
    private function contactView(ClientView $client): array
    {
        $slots   = ClientContactSlots::fromContactMethods($client->contactMethods);
        $address = $client->postalAddress;

        return [
            'mobile'   => $slots->mobile?->value,
            'landline' => $slots->landline?->value,
            'email'    => $slots->email?->value,
            'address'  => null === $address
                ? null
                : trim(\sprintf('%s, %s %s', $address->streetLine1, $address->postalCode ?? '', $address->city)),
            'street'     => $address->streetLine1 ?? '',
            'postalCode' => $address->postalCode ?? '',
            'city'       => $address->city ?? '',
        ];
    }

    private function sinceLabel(ClientView $client, \DateTimeZone $tz, \DateTimeImmutable $now): string
    {
        $createdAt = (new \DateTimeImmutable($client->createdAt))->setTimezone($tz);

        if ($createdAt->format('Y-m-d') === $now->setTimezone($tz)->format('Y-m-d')) {
            return 'Aujourd’hui';
        }

        return mb_convert_case($this->format($createdAt, 'MMMM y'), \MB_CASE_TITLE);
    }

    /**
     * @param list<AnimalListItemView>                                                                             $items
     * @param list<array{animalId: string, startedAt: \DateTimeImmutable, status: string, weightKg: ?string, ...}> $consultations
     * @param list<OwnerAppointmentItem>                                                                           $appointments
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     kind: string,
     *     meta: string,
     *     label: string,
     *     archived: bool,
     *     deceased: bool,
     *     alerts: list<array{kind: string, label: string}>,
     *     lastVisit: ?string,
     *     inConsultation: bool,
     *     nextAppointment: ?string,
     *     microchip: ?string
     * }>
     */
    private function animalsView(
        array $items,
        array $consultations,
        array $appointments,
        \DateTimeZone $tz,
        \DateTimeImmutable $now,
    ): array {
        $alerts       = $this->alertsPerAnimal($items);
        $reproductive = $this->reproductiveStatusPerAnimal($items);
        $animals      = [];

        foreach ($items as $item) {
            $lastVisit      = null;
            $weight         = null;
            $inConsultation = false;

            // Consultations come newest first: the first hit is the latest.
            foreach ($consultations as $consultation) {
                if ($consultation['animalId'] !== $item->id) {
                    continue;
                }

                $lastVisit ??= $consultation['startedAt'];
                $weight ??= $consultation['weightKg'];
                $inConsultation = $inConsultation || 'OPEN' === $consultation['status'];
            }

            $next = null;

            foreach ($appointments as $appointment) {
                $startsAt = new \DateTimeImmutable($appointment->startsAtUtc, new \DateTimeZone('UTC'));

                if ($appointment->animalId === $item->id && 'PLANNED' === $appointment->status && $startsAt >= $now) {
                    // Appointments come newest first: keep overwriting down to the soonest.
                    $next = $startsAt;
                }
            }

            $sex  = $this->sexLabel($item->sex, $reproductive[$item->id] ?? 'unknown');
            $meta = array_filter([
                $item->breedName,
                $sex,
                $item->ageLabel($now),
                null === $weight ? null : $this->formatWeight($weight),
            ], static fn (?string $part): bool => null !== $part && '' !== $part);

            $animals[] = [
                'id'    => $item->id,
                'name'  => $item->name,
                'kind'  => \in_array($item->species, ['dog', 'cat'], true) ? $item->species : 'nac',
                'meta'  => implode(' · ', $meta),
                'label' => implode(' — ', array_filter([
                    $item->name,
                    implode(', ', array_filter([$item->breedName, $item->ageLabel($now)])),
                ])),
                'archived'        => 'active' !== $item->status,
                'deceased'        => 'deceased' === $item->lifeStatus,
                'alerts'          => $alerts[$item->id] ?? [],
                'lastVisit'       => null === $lastVisit ? null : $this->format($lastVisit->setTimezone($tz), 'd MMM y'),
                'inConsultation'  => $inConsultation,
                'nextAppointment' => null === $next ? null : $this->format($next->setTimezone($tz), 'd MMM y'),
                'microchip'       => $item->microchipNumber,
            ];
        }

        // Living, active animals first; the order inside each group stays alphabetical.
        usort($animals, static fn (array $a, array $b): int => [$a['archived'], $a['deceased']] <=> [$b['archived'], $b['deceased']]);

        return $animals;
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
             ORDER BY kind ASC, label ASC',
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
     * @return array<string, string>
     */
    private function reproductiveStatusPerAnimal(array $animals): array
    {
        if ([] === $animals) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS id, reproductive_status FROM animal__animals WHERE id IN (?)',
            [$this->binaryIds($animals)],
            [ArrayParameterType::BINARY],
        );

        $statuses = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']) && \is_string($row['reproductive_status']));

            $statuses[$row['id']] = $row['reproductive_status'];
        }

        return $statuses;
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

    private function sexLabel(string $sex, string $reproductiveStatus): ?string
    {
        $neutered = 'neutered' === $reproductiveStatus;

        return match ($sex) {
            'female' => $neutered ? 'femelle stérilisée' : 'femelle',
            'male'   => $neutered ? 'mâle castré' : 'mâle',
            default  => null,
        };
    }

    private function formatWeight(string $raw): string
    {
        return rtrim(rtrim(number_format((float) $raw, 3, ',', ''), '0'), ',') . ' kg';
    }

    /**
     * @param list<OwnerAppointmentItem> $appointments
     * @param array<string, string>      $practitioners
     *
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     upcomingCount: int,
     *     next: array{when: string, detail: string}|null,
     *     footer: ?string
     * }
     */
    private function appointmentsView(
        array $appointments,
        array $practitioners,
        \DateTimeZone $tz,
        \DateTimeImmutable $now,
    ): array {
        $utc       = new \DateTimeZone('UTC');
        $upcoming  = [];
        $cancelled = [];
        $honoured  = 0;
        $noShows   = 0;
        $past      = 0;

        foreach ($appointments as $appointment) {
            $startsAt = (new \DateTimeImmutable($appointment->startsAtUtc, $utc))->setTimezone($tz);
            $isFuture = $startsAt >= $now;
            $subject  = trim(($appointment->reason ?? 'Rendez-vous') . (null === $appointment->animalLabel ? '' : ' — ' . $appointment->animalLabel));

            if ('PLANNED' === $appointment->status && $isFuture) {
                $upcoming[] = [
                    'id'       => $appointment->id,
                    'day'      => $startsAt->format('d'),
                    'title'    => $subject,
                    'subtitle' => $this->sentence(implode(' · ', array_filter([
                        $this->format($startsAt, "EEEE d MMMM, HH 'h' mm"),
                        $practitioners[$appointment->practitionerUserId] ?? null,
                        $appointment->durationMinutes . ' min',
                    ]))),
                    'badge'      => 'brand',
                    'badgeLabel' => 'Planifié',
                    'action'     => 'Déplacer',
                    'date'       => $startsAt->format('Y-m-d'),
                    'cancelled'  => false,
                    'startsAt'   => $startsAt,
                    'reason'     => $appointment->reason,
                    'animal'     => $appointment->animalLabel,
                ];

                continue;
            }

            if ('CANCELLED' === $appointment->status && $isFuture) {
                $cancelled[] = [
                    'id'         => $appointment->id,
                    'day'        => $startsAt->format('d'),
                    'title'      => $subject,
                    'subtitle'   => \sprintf('Prévu le %s · à reprogrammer', $this->format($startsAt, 'd MMMM')),
                    'badge'      => 'crit',
                    'badgeLabel' => 'Annulé',
                    'action'     => 'Reprogrammer',
                    'date'       => $startsAt->format('Y-m-d'),
                    'cancelled'  => true,
                    'startsAt'   => $startsAt,
                    'reason'     => $appointment->reason,
                    'animal'     => $appointment->animalLabel,
                ];

                continue;
            }

            if ($isFuture || 'CANCELLED' === $appointment->status) {
                continue;
            }

            // A past appointment still marked as planned was never settled either way: it is left out.
            if ('NO_SHOW' === $appointment->status) {
                ++$past;
                ++$noShows;
            } elseif ('COMPLETED' === $appointment->status) {
                ++$past;
                ++$honoured;
            }
        }

        // The list arrives newest first: the soonest upcoming one must lead.
        $upcoming = array_reverse($upcoming);
        $next     = $upcoming[0] ?? null;

        $footer = null;

        if ($past > 0) {
            $footer = \sprintf(
                '%d rendez-vous honoré%s sur %d%s.',
                $honoured,
                $honoured > 1 ? 's' : '',
                $past,
                0 === $noShows ? '' : \sprintf(' · %d absence%s non prévenue%s', $noShows, $noShows > 1 ? 's' : '', $noShows > 1 ? 's' : ''),
            );
        }

        return [
            'rows'          => [...$upcoming, ...array_reverse($cancelled)],
            'upcomingCount' => \count($upcoming),
            'next'          => null === $next ? null : [
                'when'   => $this->format($next['startsAt'], "EEEE d MMMM, HH 'h' mm"),
                'detail' => implode(', ', array_filter([$next['animal'], $next['reason']])),
            ],
            'footer' => $footer,
        ];
    }

    /**
     * @return array{
     *     initials: string,
     *     name: string,
     *     subtitle: string,
     *     phoneNumber: ?string,
     *     authorizedAt: string,
     *     form: array{civility: string, firstName: string, lastName: string, relationship: string, phoneNumber: string}
     * }|null
     */
    private function authorizedPersonView(ClientView $client, \DateTimeZone $tz): ?array
    {
        $person = $client->authorizedPerson;

        if (null === $person) {
            return null;
        }

        $honorific = Civility::from($person->civility)->honorific();

        return [
            'initials'     => mb_strtoupper(mb_substr($person->firstName, 0, 1) . mb_substr($person->lastName, 0, 1)),
            'name'         => trim($honorific . ' ' . $person->fullName()),
            'subtitle'     => AuthorizedPersonRelationship::from($person->relationship)->label(),
            'phoneNumber'  => $person->phoneNumber,
            'authorizedAt' => $this->format((new \DateTimeImmutable($person->authorizedAt))->setTimezone($tz), 'd MMMM y'),
            'form'         => [
                'civility'     => $person->civility,
                'firstName'    => $person->firstName,
                'lastName'     => $person->lastName,
                'relationship' => $person->relationship,
                'phoneNumber'  => $person->phoneNumber ?? '',
            ],
        ];
    }

    /**
     * @return array{
     *     rows: list<array{kind: string, label: string, active: bool, detail: string, channels: list<string>}>,
     *     missing: int,
     *     remindersGranted: bool,
     *     marketingGranted: bool,
     *     preferredChannel: ?string,
     *     preferredChannelLabel: ?string
     * }
     */
    private function consentsView(ClientView $client, \DateTimeZone $tz): array
    {
        $rows    = [];
        $missing = 0;

        foreach (ConsentKind::cases() as $kind) {
            $consent = $client->consentFor($kind->value);

            if (null === $consent) {
                ++$missing;

                $rows[] = [
                    'kind'   => $kind->value,
                    'label'  => $kind->label(),
                    'active' => false,
                    'detail' => ConsentKind::MARKETING === $kind
                        ? 'Jamais recueilli · aucun envoi commercial autorisé'
                        : 'Non recueilli — à demander au comptoir',
                    'channels' => [],
                ];

                continue;
            }

            $channels = array_map(
                static fn (string $channel): string => ContactChannel::from($channel)->label(),
                $consent->channels,
            );

            $rows[] = [
                'kind'   => $kind->value,
                'label'  => $kind->label(),
                'active' => true,
                'detail' => \sprintf(
                    'Recueilli le %s · %s',
                    $this->format((new \DateTimeImmutable($consent->grantedAt))->setTimezone($tz), 'd MMMM y'),
                    implode(' et ', $channels),
                ),
                'channels' => $consent->channels,
            ];
        }

        $preferred = null === $client->preferredChannel ? null : ContactChannel::from($client->preferredChannel);

        return [
            'rows'                  => $rows,
            'missing'               => $missing,
            'remindersGranted'      => null !== $client->consentFor(ConsentKind::APPOINTMENT_REMINDERS->value),
            'marketingGranted'      => null !== $client->consentFor(ConsentKind::MARKETING->value),
            'preferredChannel'      => $preferred?->value,
            'preferredChannelLabel' => $preferred?->label(),
        ];
    }

    /**
     * @param list<array{id: string, admissionId: ?string, practitionerUserId: string, status: string, startedAt: \DateTimeImmutable, closedAt: ?\DateTimeImmutable, reason: ?string, animalName: string, ...}> $consultations
     * @param list<InvoiceListRow>                                                                                                                                                                              $invoices
     * @param array<string, list<array{id: string, number: string, ...}>>                                                                                                                                       $prescriptions
     * @param array<string, string>                                                                                                                                                                             $practitioners
     *
     * @return array{open: list<array<string, mixed>>, closed: list<array<string, mixed>>, total: int, firstVisit: ?string}
     */
    private function consultationsView(
        array $consultations,
        array $invoices,
        array $prescriptions,
        array $practitioners,
        \DateTimeZone $tz,
    ): array {
        $invoiceByAdmission = [];

        foreach ($invoices as $invoice) {
            // Invoices come newest first: the latest one still standing wins over a cancelled one.
            if (null !== $invoice->admissionId && null !== $invoice->number && 'CANCELLED' !== $invoice->status) {
                $invoiceByAdmission[$invoice->admissionId] ??= $invoice;
            }
        }

        $open   = [];
        $closed = [];

        foreach ($consultations as $consultation) {
            $startedAt    = $consultation['startedAt']->setTimezone($tz);
            $practitioner = $practitioners[$consultation['practitionerUserId']] ?? '—';
            $reason       = $consultation['reason'] ?? 'Consultation';

            if ('OPEN' === $consultation['status']) {
                $open[] = [
                    'id'       => $consultation['id'],
                    'day'      => $startedAt->format('d'),
                    'title'    => \sprintf('%s — %s', $consultation['animalName'], $reason),
                    'subtitle' => \sprintf(
                        '%s · %s · consultation ouverte',
                        $this->sentence($this->format($startedAt, "d MMMM, HH 'h' mm")),
                        $practitioner,
                    ),
                ];

                continue;
            }

            $invoice = null === $consultation['admissionId']
                ? null
                : $invoiceByAdmission[$consultation['admissionId']] ?? null;

            $closed[] = [
                'id'            => $consultation['id'],
                'date'          => $startedAt->format('d/m/Y'),
                'animalName'    => $consultation['animalName'],
                'reason'        => $reason,
                'practitioner'  => $practitioner,
                'amount'        => $invoice?->totalGrossMinorUnits,
                'currency'      => $invoice->currency ?? 'EUR',
                'prescriptions' => $prescriptions[$consultation['id']] ?? [],
                'invoiceId'     => $invoice?->id,
            ];
        }

        $first = [] === $consultations ? null : $consultations[array_key_last($consultations)]['startedAt'];

        return [
            'open'       => $open,
            'closed'     => $closed,
            'total'      => \count($consultations),
            'firstVisit' => null === $first ? null : $this->format($first->setTimezone($tz), 'd MMMM y'),
        ];
    }

    /**
     * @param list<InvoiceListRow>                                                        $invoices
     * @param list<array{admissionId: ?string, animalName: string, reason: ?string, ...}> $consultations
     *
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     outstanding: list<array{id: string, number: string, balance: int, balanceInput: string}>,
     *     balanceDue: int,
     *     currency: string,
     *     paidLastYear: int,
     *     issuedCount: int,
     *     firstYear: ?string,
     *     oldestOutstandingDays: ?int,
     *     outstandingCount: int
     * }
     */
    private function billingView(array $invoices, array $consultations, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $visitByAdmission = [];

        foreach ($consultations as $consultation) {
            if (null !== $consultation['admissionId']) {
                $visitByAdmission[$consultation['admissionId']] ??= $consultation;
            }
        }

        $rows         = [];
        $outstanding  = [];
        $balanceDue   = 0;
        $paidLastYear = 0;
        $issuedCount  = 0;
        $currency     = 'EUR';
        $firstIssued  = null;
        $oldestDays   = null;
        $yearAgo      = $now->modify('-12 months');

        foreach ($invoices as $invoice) {
            // Drafts and cancelled invoices are not owed and are not part of the history shown here.
            if (null === $invoice->number || null === $invoice->issuedAt || 'CANCELLED' === $invoice->status) {
                continue;
            }

            ++$issuedCount;
            $currency    = $invoice->currency;
            $firstIssued = null === $firstIssued || $invoice->issuedAt < $firstIssued ? $invoice->issuedAt : $firstIssued;
            $visit       = null === $invoice->admissionId ? null : $visitByAdmission[$invoice->admissionId] ?? null;
            $days        = max(0, (int) $invoice->issuedAt->diff($now)->days);
            $isDue       = $invoice->balanceDueMinorUnits > 0;

            if ($invoice->issuedAt >= $yearAgo) {
                $paidLastYear += $invoice->amountPaidMinorUnits;
            }

            if ($isDue) {
                $balanceDue += $invoice->balanceDueMinorUnits;
                $oldestDays = null === $oldestDays ? $days : max($oldestDays, $days);

                $outstanding[] = [
                    'id'           => $invoice->id,
                    'number'       => $invoice->number,
                    'balance'      => $invoice->balanceDueMinorUnits,
                    'balanceInput' => number_format($invoice->balanceDueMinorUnits / 100, 2, ',', ''),
                ];
            }

            $rows[] = [
                'id'         => $invoice->id,
                'number'     => $invoice->number,
                'date'       => $invoice->issuedAt->setTimezone($tz)->format('d/m/Y'),
                'animalName' => $visit['animalName'] ?? '—',
                'subject'    => $visit['reason'] ?? '—',
                'amount'     => $invoice->totalGrossMinorUnits,
                'currency'   => $invoice->currency,
                'due'        => $isDue,
                'badge'      => $isDue ? 'crit' : 'ok',
                'badgeLabel' => match (true) {
                    !$isDue                               => 'Acquittée',
                    'PARTIALLY_PAID' === $invoice->status => \sprintf('Partielle · %d j', $days),
                    default                               => \sprintf('Impayée · %d j', $days),
                },
            ];
        }

        // Unpaid invoices lead the table, as the mock-up puts what is owed first.
        usort($rows, static fn (array $a, array $b): int => $b['due'] <=> $a['due']);

        return [
            'rows'                  => $rows,
            'outstanding'           => $outstanding,
            'balanceDue'            => $balanceDue,
            'currency'              => $currency,
            'paidLastYear'          => $paidLastYear,
            'issuedCount'           => $issuedCount,
            'firstYear'             => $firstIssued?->setTimezone($tz)->format('Y'),
            'oldestOutstandingDays' => $oldestDays,
            'outstandingCount'      => \count($outstanding),
        ];
    }

    /**
     * @param array<mixed> $exchanges
     *
     * @return list<array{when: string, title: string, body: ?string, author: string, direction: string}>
     */
    private function communicationsView(array $exchanges, \DateTimeZone $tz): array
    {
        $rows = [];

        foreach ($exchanges as $exchange) {
            \assert($exchange instanceof ClientCommunicationView);

            $rows[] = [
                'when' => \sprintf(
                    '%s · %s',
                    $this->format($exchange->occurredAt->setTimezone($tz), "d MMMM y · HH 'h' mm"),
                    mb_strtolower(ContactChannel::from($exchange->channel)->label()),
                ),
                'title'     => $exchange->subject,
                'body'      => $exchange->body,
                'author'    => $exchange->authorName,
                'direction' => CommunicationDirection::from($exchange->direction)->label(),
            ];
        }

        return $rows;
    }

    /**
     * @param array<mixed> $notes
     *
     * @return list<array{attention: bool, heading: string, content: string}>
     */
    private function notesView(array $notes, \DateTimeZone $tz): array
    {
        $rows = [];

        foreach ($notes as $note) {
            \assert($note instanceof ClientNoteView);

            $rows[] = [
                'attention' => ClientNoteKind::ATTENTION->value === $note->kind,
                'heading'   => \sprintf('%s — %s.', $note->createdAt->setTimezone($tz)->format('d/m/Y'), $note->authorName),
                'content'   => $note->content,
            ];
        }

        return $rows;
    }

    /**
     * Reports, prescriptions and invoices are generated on demand: the sheet
     * lists what can be produced for this client, newest first.
     *
     * @param list<array{id: string, admissionId: ?string, status: string, startedAt: \DateTimeImmutable, animalName: string, ...}> $consultations
     * @param list<InvoiceListRow>                                                                                                  $invoices
     * @param array<string, list<array{id: string, number: string, issuedAt: \DateTimeImmutable, lineCount: int}>>                  $prescriptions
     *
     * @return list<array{type: string, title: string, reference: string, date: string, animalName: string, id: string}>
     */
    private function documentsView(array $consultations, array $invoices, array $prescriptions, \DateTimeZone $tz): array
    {
        $documents      = [];
        $animalByVisit  = [];
        $animalByAdmiss = [];

        foreach ($consultations as $consultation) {
            $animalByVisit[$consultation['id']] = $consultation['animalName'];

            if (null !== $consultation['admissionId']) {
                $animalByAdmiss[$consultation['admissionId']] = $consultation['animalName'];
            }

            if ('CLOSED' !== $consultation['status']) {
                continue;
            }

            $documents[] = [
                'type'       => 'report',
                'title'      => 'Compte rendu de consultation',
                'reference'  => 'CR-' . mb_strtoupper(mb_substr($consultation['id'], -8)),
                'sortKey'    => $consultation['startedAt'],
                'animalName' => $consultation['animalName'],
                'id'         => $consultation['id'],
            ];
        }

        foreach ($prescriptions as $consultationId => $list) {
            foreach ($list as $prescription) {
                $documents[] = [
                    'type'  => 'prescription',
                    'title' => \sprintf(
                        'Ordonnance — %d médicament%s',
                        $prescription['lineCount'],
                        $prescription['lineCount'] > 1 ? 's' : '',
                    ),
                    'reference'  => $prescription['number'],
                    'sortKey'    => $prescription['issuedAt'],
                    'animalName' => $animalByVisit[$consultationId] ?? '—',
                    'id'         => $prescription['id'],
                ];
            }
        }

        foreach ($invoices as $invoice) {
            if (null === $invoice->number || null === $invoice->issuedAt || 'CANCELLED' === $invoice->status) {
                continue;
            }

            $documents[] = [
                'type'  => 'invoice',
                'title' => \sprintf(
                    'Facture%s — %s €',
                    $invoice->balanceDueMinorUnits > 0 ? '' : ' acquittée',
                    number_format($invoice->totalGrossMinorUnits / 100, 2, ',', ' '),
                ),
                'reference'  => $invoice->number,
                'sortKey'    => $invoice->issuedAt,
                'animalName' => null === $invoice->admissionId ? '—' : $animalByAdmiss[$invoice->admissionId] ?? '—',
                'id'         => $invoice->id,
            ];
        }

        usort($documents, static fn (array $a, array $b): int => $b['sortKey'] <=> $a['sortKey']);

        return array_map(static fn (array $document): array => [
            'type'       => $document['type'],
            'title'      => $document['title'],
            'reference'  => $document['reference'],
            'date'       => $document['sortKey']->setTimezone($tz)->format('d/m/Y'),
            'animalName' => $document['animalName'],
            'id'         => $document['id'],
        ], $documents);
    }

    /**
     * What still has to be collected for the file to be usable day to day.
     *
     * @param array{mobile: ?string, landline: ?string, email: ?string, address: ?string, ...} $contact
     * @param array{remindersGranted: bool, ...}                                               $consents
     * @param list<array{name: string, archived: bool, ...}>                                   $animals
     *
     * @return array{rows: list<array{done: bool, level: string, title: string, detail: string, action: ?string, modal: ?string}>, done: int, total: int, missing: int}
     */
    private function checklist(ClientView $client, array $contact, array $consents, array $animals): array
    {
        $hasPhone  = null !== $contact['mobile'] || null !== $contact['landline'];
        $firstName = [] === $animals ? null : $animals[0]['name'];

        $rows = [
            [
                'done'   => $consents['remindersGranted'],
                'level'  => 'crit',
                'title'  => $consents['remindersGranted'] ? 'Consentement aux rappels recueilli' : 'Consentements non recueillis',
                'detail' => $consents['remindersGranted']
                    ? 'Les rappels de rendez-vous peuvent être envoyés'
                    : 'Sans accord, aucun rappel de rendez-vous ni de vaccination ne peut être envoyé.',
                'action' => 'Recueillir',
                'modal'  => 'fc-m-comm',
            ],
            [
                'done'   => null !== $contact['email'],
                'level'  => 'warn',
                'title'  => null !== $contact['email'] ? 'Adresse e-mail renseignée' : 'Adresse e-mail manquante',
                'detail' => null !== $contact['email']
                    ? $contact['email']
                    : 'Le compte rendu et la facture ne pourront être remis qu’en main propre.',
                'action' => 'Renseigner',
                'modal'  => 'fc-m-edit',
            ],
            [
                'done'   => null !== $client->authorizedPerson,
                'level'  => 'warn',
                'title'  => null !== $client->authorizedPerson ? 'Personne autorisée désignée' : 'Aucune personne autorisée désignée',
                'detail' => null !== $client->authorizedPerson
                    ? $client->authorizedPerson->fullName()
                    : \sprintf('Personne d’autre que %s ne pourra récupérer ses animaux.', $client->fullName()),
                'action' => 'Désigner',
                'modal'  => 'fc-m-edit',
            ],
            [
                'done'  => $hasPhone && null !== $contact['address'],
                'level' => 'warn',
                'title' => $hasPhone && null !== $contact['address']
                    ? 'Coordonnées principales renseignées'
                    : 'Coordonnées principales incomplètes',
                'detail' => $hasPhone && null !== $contact['address']
                    ? 'Téléphone et adresse postale'
                    : ($hasPhone ? 'Adresse postale manquante' : 'Aucun numéro de téléphone'),
                'action' => 'Compléter',
                'modal'  => 'fc-m-edit',
            ],
            [
                'done'   => null !== $firstName,
                'level'  => 'warn',
                'title'  => null !== $firstName ? 'Premier animal rattaché' : 'Aucun animal rattaché',
                'detail' => $firstName ?? 'Le dossier ne contient encore aucun animal.',
                'action' => 'Ajouter',
                'modal'  => 'fc-m-pet',
            ],
        ];

        // What remains to be done leads; what is settled sinks to the bottom, as in the mock-up.
        usort($rows, static fn (array $a, array $b): int => $a['done'] <=> $b['done']);

        $done = \count(array_filter($rows, static fn (array $row): bool => $row['done']));

        return ['rows' => $rows, 'done' => $done, 'total' => \count($rows), 'missing' => \count($rows) - $done];
    }

    /**
     * The sticky strip: what must be known before speaking to this client.
     *
     * @param array{balanceDue: int, oldestOutstandingDays: ?int, currency: string, ...} $billing
     * @param array{remindersGranted: bool, marketingGranted: bool, ...}                 $consents
     * @param list<array{startedAt: \DateTimeImmutable, ...}>                            $consultations
     *
     * @return array{pills: list<array{level: string, label: string}>, meta: string}
     */
    private function safety(ClientView $client, array $billing, array $consents, int $animalCount, array $consultations): array
    {
        $pills = [];

        if ('archived' === $client->status) {
            $pills[] = ['level' => 'info', 'label' => 'Fiche archivée'];
        }

        if ($billing['balanceDue'] > 0) {
            $pills[] = [
                'level' => 'crit',
                'label' => \sprintf(
                    '%s € impayés depuis %d jour%s',
                    number_format($billing['balanceDue'] / 100, 2, ',', ' '),
                    $billing['oldestOutstandingDays'] ?? 0,
                    ($billing['oldestOutstandingDays'] ?? 0) > 1 ? 's' : '',
                ),
            ];
        }

        if (!$consents['remindersGranted']) {
            $pills[] = ['level' => 'warn', 'label' => 'Consentements non recueillis — aucun rappel ne peut être envoyé'];
        } elseif (!$consents['marketingGranted']) {
            $pills[] = ['level' => 'warn', 'label' => 'Consentement commercial non recueilli'];
        }

        $visits = \count($consultations);
        $meta   = \sprintf('%d anima%s', $animalCount, $animalCount > 1 ? 'ux' : 'l');

        if ($visits > 0) {
            $first = $consultations[array_key_last($consultations)]['startedAt'];
            $meta .= 1 === $visits
                ? ' · première visite'
                : \sprintf(' · %d visites depuis %s', $visits, $first->format('Y'));
        }

        return ['pills' => $pills, 'meta' => $meta];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function choices(): array
    {
        $civilities    = [];
        $relationships = [];
        $channels      = [];
        $consentKinds  = [];
        $noteKinds     = [];
        $directions    = [];

        foreach (Civility::cases() as $case) {
            $civilities[$case->value] = $case->label();
        }

        foreach (AuthorizedPersonRelationship::cases() as $case) {
            $relationships[$case->value] = $case->label();
        }

        foreach (ContactChannel::cases() as $case) {
            $channels[$case->value] = $case->label();
        }

        foreach (ConsentKind::cases() as $case) {
            $consentKinds[$case->value] = $case->label();
        }

        foreach (ClientNoteKind::cases() as $case) {
            $noteKinds[$case->value] = $case->label();
        }

        foreach (CommunicationDirection::cases() as $case) {
            $directions[$case->value] = $case->label();
        }

        return [
            'civilities'    => $civilities,
            'relationships' => $relationships,
            'channels'      => $channels,
            'consentKinds'  => $consentKinds,
            'noteKinds'     => $noteKinds,
            'directions'    => $directions,
        ];
    }

    private function format(\DateTimeImmutable $date, string $pattern): string
    {
        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            $date->getTimezone(),
            \IntlDateFormatter::GREGORIAN,
            $pattern,
        );

        $formatted = $formatter->format($date);

        return false === $formatted ? $date->format('d/m/Y') : $formatted;
    }

    private function sentence(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}
