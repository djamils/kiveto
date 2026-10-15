<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Admission\Application\Query\ListAdmissionsInWaitingRoom\ListAdmissionsInWaitingRoom;
use App\Context\Animal\Application\Query\ListAnimalIdsMatching\ListAnimalIdsMatching;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListItemView;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoices;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterCriteria;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterItem;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterPage;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ListConsultationRegister;
use App\Presentation\Clinic\ViewModel\Prescription\PrescriptionRegisterViewBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The register of consultations (consultations-v-topbar): one page of rows as
 * the page module draws them, with the animal and its owner, the practitioner
 * and their agenda colour, the amount and whether it has been collected.
 *
 * The rows come from the Consultation context's register; the animal facts
 * are read in one batched query (the batching the other registers use), the
 * payment state from the Billing context's invoices of the page's visits.
 */
final readonly class ConsultationRegisterViewBuilder
{
    /** Stage of the Consultation context → status of the mock-up. */
    public const array STATUS = [
        ConsultationRegisterCriteria::STAGE_IN_PROGRESS => 'cours',
        ConsultationRegisterCriteria::STAGE_TO_CLOSE    => 'aclot',
        ConsultationRegisterCriteria::STAGE_CLOSED      => 'clot',
    ];

    /** Invoice statuses still waiting for money. */
    private const array UNSETTLED = ['DRAFT', 'ISSUED', 'PARTIALLY_PAID'];

    /** More unsettled invoices than a clinic carries. */
    private const int MAX_UNSETTLED = 1000;

    public function __construct(
        private QueryBusInterface $queryBus,
        private Connection $connection,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private PrescriptionRegisterViewBuilder $prescriptions,
    ) {
    }

    /**
     * Practitioners of the clinic: « Dr Lambert », initials and agenda colour.
     *
     * @return array<string, array{id: string, n: string, ini: string, c: string}>
     */
    public function team(string $clinicId): array
    {
        return $this->prescriptions->team($clinicId);
    }

    /**
     * One page of the register for the filters, plus the header's counters.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pp: int, counts: array{cours: int, aclot: int, today: int, recorded: int}}
     */
    public function register(string $clinicId, ConsultationRegisterFilters $filters, \DateTimeZone $tz): array
    {
        $today = $this->today($tz);
        $query = $this->query($clinicId, $filters, $today);
        $page  = $this->ask($query);

        // A page past the end (the list shrank under a filter) falls back on the last one.
        if ([] === $page->items && $page->total > 0 && $filters->page > $page->pageCount()) {
            $page = $this->ask($this->query($clinicId, $filters, $today, $page->pageCount()));
        }

        return [
            'rows'   => $this->rows($clinicId, $page->items, $tz),
            'total'  => $page->total,
            'page'   => $page->page,
            'pp'     => $page->perPage,
            'counts' => ['cours' => $page->inProgress, 'aclot' => $page->toClose, 'today' => $page->today, 'recorded' => $page->recorded],
        ];
    }

    /**
     * The row of one consultation of the clinic, null when it is not this clinic's.
     *
     * @return ?array<string, mixed>
     */
    public function row(string $clinicId, string $consultationId, \DateTimeZone $tz): ?array
    {
        if (!Uuid::isValid($consultationId)) {
            return null;
        }

        $page = $this->ask(new ListConsultationRegister(
            clinicId: $clinicId,
            dayStartUtc: $this->today($tz)->setTimezone(new \DateTimeZone('UTC')),
            consultationIds: [$consultationId],
            limit: 1,
        ));

        return $this->rows($clinicId, $page->items, $tz)[0] ?? null;
    }

    /**
     * Who is waiting in the flow board's waiting room, the first way into a
     * new consultation: taking them in charge opens it.
     *
     * @return list<array{h: string, e: string, a: string, m: string, prop: string, urg: bool, url: string}>
     */
    public function waitingRoom(string $clinicId): array
    {
        $waiting = $this->queryBus->ask(new ListAdmissionsInWaitingRoom($clinicId));
        $items   = [];

        foreach (\is_array($waiting) ? $waiting : [] as $item) {
            if ($item instanceof WaitingRoomItemDto) {
                $items[] = $item;
            }
        }

        $patients = $this->patients(array_map(static fn (WaitingRoomItemDto $i): string => $i->patientId, $items));
        $now      = $this->clock->now()->getTimestamp();
        $list     = [];

        foreach ($items as $item) {
            $patient = $patients[$item->patientId] ?? null;
            $since   = strtotime($item->locationSince ?? $item->openedAt);

            $list[] = [
                'h'    => \sprintf('%d min', max(0, intdiv($now - (false !== $since ? $since : $now), 60))),
                'e'    => PrescriptionRegisterViewBuilder::emoji($patient['species'] ?? null),
                'a'    => $patient['label'] ?? $item->displayLabel,
                'm'    => $item->appointmentReason ?? $item->triageNotes ?? 'Consultation',
                'prop' => $patient['owner'] ?? 'Propriétaire inconnu',
                'urg'  => \in_array($item->triageLevel, ['emergency', 'vital_emergency'], true),
                'url'  => $this->urls->generate('clinic_consultation_start_from_admission', ['admissionId' => $item->admissionId]),
            ];
        }

        // Emergencies first, then the longest wait.
        usort($list, static fn (array $a, array $b): int => [$b['urg'], (int) $b['h']] <=> [$a['urg'], (int) $a['h']]);

        return $list;
    }

    public function today(\DateTimeZone $tz): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($tz)->setTime(0, 0);
    }

    private function query(string $clinicId, ConsultationRegisterFilters $filters, \DateTimeImmutable $today, ?int $page = null): ListConsultationRegister
    {
        return new ListConsultationRegister(
            clinicId: $clinicId,
            dayStartUtc: $today->setTimezone(new \DateTimeZone('UTC')),
            searchTerm: '' !== $filters->q ? $filters->q : null,
            textMatchAnimalIds: '' !== $filters->q ? $this->animalIds($clinicId, $filters->q) : [],
            startedAfterUtc: $filters->startedAfter($today),
            stage: $filters->stage(),
            practitionerUserId: '' !== $filters->vet ? $filters->vet : null,
            kind: $filters->kind(),
            restrictToAdmissionIds: 'du' === $filters->type ? $this->unsettledAdmissionIds($clinicId) : null,
            page: $page ?? $filters->page,
            limit: $filters->pp,
        );
    }

    private function ask(ListConsultationRegister $query): ConsultationRegisterPage
    {
        $page = $this->queryBus->ask($query);
        \assert($page instanceof ConsultationRegisterPage);

        return $page;
    }

    /**
     * @param list<ConsultationRegisterItem> $items
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $clinicId, array $items, \DateTimeZone $tz): array
    {
        $patients = $this->patients(array_map(static fn (ConsultationRegisterItem $i): string => $i->patientId, $items));
        $invoices = $this->invoices($clinicId, array_map(static fn (ConsultationRegisterItem $i): string => $i->admissionId, $items));
        $team     = $this->team($clinicId);
        $rows     = [];

        foreach ($items as $item) {
            $patient = $patients[$item->patientId] ?? null;
            $invoice = $invoices[$item->admissionId] ?? null;
            $at      = new \DateTimeImmutable($item->startedAtUtc, new \DateTimeZone('UTC'))->setTimezone($tz);
            $vet     = $team[$item->practitionerUserId] ?? ['id' => $item->practitionerUserId, 'n' => 'Praticien', 'ini' => 'PR', 'c' => '#64748b'];

            $rows[] = [
                'id'      => $item->consultationId,
                'd'       => $at->format('Y-m-d'),
                'h'       => $at->format('H:i'),
                'started' => $at->format(\DateTimeInterface::ATOM),
                'dur'     => $item->durationMinutes,
                'st'      => self::STATUS[$item->stage],
                'a'       => [
                    'n'    => $patient['label'] ?? '—',
                    'e'    => PrescriptionRegisterViewBuilder::emoji($patient['species'] ?? null),
                    'prop' => $patient['owner'] ?? 'Propriétaire inconnu',
                    'r'    => $patient['identity'] ?? '',
                    'url'  => null !== ($patient['animalId'] ?? null) ? $this->urls->generate('clinic_animals_view', ['id' => $patient['animalId']]) : null,
                ],
                'motif' => $item->motif(),
                'diag'  => $item->primaryDiagnosis,
                'flags' => [
                    'urg'  => $item->isUrgent,
                    'aid'  => null !== $patient && null === $patient['animalId'],
                    'chir' => $item->hasSurgery,
                    'lab'  => $item->labAnalyses,
                ],
                'vet'     => $vet,
                'ttc'     => $item->totalTtcMinorUnits,
                'cur'     => $item->currency,
                'pay'     => null === $invoice ? 'none' : (\in_array($invoice->status, self::UNSETTLED, true) ? 'du' : 'paye'),
                'invoice' => null === $invoice ? null : [
                    'num' => $invoice->number,
                    'url' => $this->urls->generate('clinic_invoice_details', ['invoiceId' => $invoice->id]),
                ],
                'url' => $this->urls->generate('clinic_consultation_details', ['id' => $item->consultationId]),
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function animalIds(string $clinicId, string $term): array
    {
        $ids = $this->queryBus->ask(new ListAnimalIdsMatching($clinicId, $term, null));

        return array_values(array_filter(\is_array($ids) ? $ids : [], 'is_string'));
    }

    /**
     * Visits whose invoice still waits for money, for the « Reste à encaisser » filter.
     *
     * @return list<string>
     */
    private function unsettledAdmissionIds(string $clinicId): array
    {
        $ids = [];

        foreach ($this->searchInvoices($clinicId, new SearchInvoicesCriteria(statuses: self::UNSETTLED, perPage: self::MAX_UNSETTLED)) as $invoice) {
            if (null !== $invoice->admissionId) {
                $ids[$invoice->admissionId] = true;
            }
        }

        return array_map('strval', array_keys($ids));
    }

    /**
     * The invoice of each visit of the page, a cancelled one only when there is no other.
     *
     * @param list<string> $admissionIds
     *
     * @return array<string, InvoiceListRow>
     */
    private function invoices(string $clinicId, array $admissionIds): array
    {
        if ([] === $admissionIds) {
            return [];
        }

        $invoices = [];

        foreach ($this->searchInvoices($clinicId, new SearchInvoicesCriteria(perPage: 500, admissionIds: array_values(array_unique($admissionIds)))) as $invoice) {
            if (null === $invoice->admissionId || 'CANCELLED' === $invoice->status) {
                continue;
            }

            $invoices[$invoice->admissionId] ??= $invoice;
        }

        return $invoices;
    }

    /**
     * @return list<InvoiceListRow>
     */
    private function searchInvoices(string $clinicId, SearchInvoicesCriteria $criteria): array
    {
        $view = $this->queryBus->ask(new SearchInvoices($clinicId, $criteria));

        return $view instanceof InvoiceListItemView ? $view->items : [];
    }

    /**
     * Name, species, breed and age, owner and animal of the patients, in one query.
     *
     * @param list<string> $patientIds
     *
     * @return array<string, array{label: string, species: ?string, owner: ?string, identity: string, animalId: ?string}>
     */
    private function patients(array $patientIds): array
    {
        if ([] === $patientIds) {
            return [];
        }

        $ids = [];

        foreach ($patientIds as $id) {
            $ids[$id] = Uuid::fromString($id)->toBinary();
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(p.id) AS patient_id, p.display_label_value, BIN_TO_UUID(p.animal_link_id) AS animal_id,
                    se.species, se.breed_name, se.search_owner_name, a.birth_date
             FROM patient__patients p
             LEFT JOIN animal__search_entries se ON se.id = p.animal_link_id
             LEFT JOIN animal__animals a ON a.id = p.animal_link_id
             WHERE p.id IN (?)',
            [array_values($ids)],
            [ArrayParameterType::BINARY],
        );

        $now      = $this->clock->now();
        $patients = [];

        foreach ($rows as $row) {
            if (!\is_string($row['patient_id'])) {
                continue;
            }

            $species  = \is_string($row['species']) ? $row['species'] : null;
            $identity = array_filter([
                \is_string($row['breed_name']) ? $row['breed_name'] : (['dog' => 'Chien', 'cat' => 'Chat', 'nac' => 'NAC'][$species ?? ''] ?? null),
                \is_string($row['birth_date']) ? self::age($row['birth_date'], $now) : null,
            ]);

            $patients[$row['patient_id']] = [
                'label'    => \is_string($row['display_label_value']) ? $row['display_label_value'] : '—',
                'species'  => $species,
                'owner'    => \is_string($row['search_owner_name']) ? $row['search_owner_name'] : null,
                'identity' => implode(' · ', $identity),
                'animalId' => \is_string($row['animal_id']) ? $row['animal_id'] : null,
            ];
        }

        return $patients;
    }

    private static function age(string $birthDate, \DateTimeImmutable $now): ?string
    {
        try {
            $interval = new \DateTimeImmutable($birthDate)->diff($now);
        } catch (\Exception) {
            return null;
        }

        return match (true) {
            $interval->y > 0 => \sprintf('%d an%s', $interval->y, $interval->y > 1 ? 's' : ''),
            $interval->m > 0 => \sprintf('%d mois', $interval->m),
            default          => \sprintf('%d jour%s', $interval->d, $interval->d > 1 ? 's' : ''),
        };
    }
}
