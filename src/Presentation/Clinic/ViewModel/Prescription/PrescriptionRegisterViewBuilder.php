<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Prescription;

use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListItemView;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultations;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListItemView;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListRow;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptions;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsCriteria;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The register of ordonnances (ordonnances-v-topbar_3): one page of rows as
 * the page module draws them, the prescribers with their agenda colour, and
 * the consultations of the day the « Depuis une consultation » window lists.
 *
 * Rows only carry what the Prescription context knows: the number, the
 * medicines and their class, who signed, for which animal and owner, the
 * status and the validity. Nothing about the animal is looked up again —
 * the ordonnance keeps the snapshot it was signed with.
 */
final readonly class PrescriptionRegisterViewBuilder
{
    /** Species of the snapshot → the emoji of the mock-up's « .pat .e ». */
    public const array SPECIES_EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇'];

    private const string FALLBACK_COLOR = '#64748b';

    public function __construct(
        private QueryBusInterface $queryBus,
        private Connection $connection,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
    ) {
    }

    public static function emoji(?string $species): string
    {
        return self::SPECIES_EMOJI[$species ?? ''] ?? '🐾';
    }

    /**
     * One page of the register for the filters, plus the totals of the header.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pp: int, drafts: int}
     */
    public function register(string $clinicId, PrescriptionRegisterFilters $filters, \DateTimeZone $tz): array
    {
        $today = $this->clock->now()->setTimezone($tz)->setTime(0, 0);
        $view  = $this->search($clinicId, $filters->criteria($today));

        // A page past the end (the list shrank under a filter) falls back on the last one.
        if ([] === $view->items && $view->total > 0 && $filters->page > $view->pageCount()) {
            $view = $this->search($clinicId, $filters->criteria($today, $view->pageCount()));
        }

        $drafts = $this->search($clinicId, new SearchPrescriptionsCriteria(statuses: ['DRAFT'], perPage: 1));
        $team   = $this->team($clinicId);
        $now    = $this->clock->now();

        return [
            'rows'   => array_map(fn (PrescriptionListRow $row): array => $this->row($row, $team, $tz, $now), $view->items),
            'total'  => $view->total,
            'page'   => $view->page,
            'pp'     => $view->perPage,
            'drafts' => $drafts->total,
        ];
    }

    /**
     * Practitioners of the clinic: « Dr Lambert », initials and agenda colour.
     *
     * @return array<string, array{id: string, n: string, ini: string, c: string}>
     */
    public function team(string $clinicId): array
    {
        $members = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        $team    = [];

        foreach (\is_array($members) ? $members : [] as $member) {
            if (!$member instanceof ClinicVeterinarianItem) {
                continue;
            }

            $name  = $member->displayName ?? 'Praticien';
            $title = ['DR' => 'Dr ', 'PR' => 'Pr '][$member->professionalTitle ?? ''] ?? '';

            $team[$member->userId] = [
                'id'  => $member->userId,
                'n'   => $title . $name,
                'ini' => self::initials($name),
                'c'   => $member->agendaColor ?? self::FALLBACK_COLOR,
            ];
        }

        return $team;
    }

    /**
     * Today's consultations, the way into a new ordonnance: it is written in
     * the consultation's plan, so the window only points there.
     *
     * @return list<array{h: string, e: string, a: string, m: string, prop: string, v: string, st: string, url: string}>
     */
    public function consultationsToday(string $clinicId, \DateTimeZone $tz): array
    {
        $midnight = $this->clock->now()->setTimezone($tz)->setTime(0, 0)->setTimezone(new \DateTimeZone('UTC'));
        $result   = $this->queryBus->ask(new SearchConsultations(
            clinicId: $clinicId,
            startedAfterUtc: $midnight,
            sort: 'datetime',
            direction: 'asc',
            limit: 100,
        ));

        $items = [];

        foreach (\is_array($result) && \is_array($result['items'] ?? null) ? $result['items'] : [] as $item) {
            if ($item instanceof ConsultationListItemView) {
                $items[] = $item;
            }
        }

        $patients = $this->patients($items);
        $team     = $this->team($clinicId);
        $list     = [];

        foreach ($items as $item) {
            $patient = $patients[$item->patientId] ?? ['label' => '—', 'species' => null, 'owner' => null];
            $motif   = $item->motifs[0] ?? $item->chiefComplaint ?? 'Consultation';

            $list[] = [
                'h'    => new \DateTimeImmutable($item->startedAtUtc, new \DateTimeZone('UTC'))->setTimezone($tz)->format('H:i'),
                'e'    => self::emoji($patient['species']),
                'a'    => $patient['label'],
                'm'    => $motif,
                'prop' => $patient['owner'] ?? '',
                'v'    => $team[$item->practitionerUserId]['n'] ?? 'Praticien',
                'st'   => 'CLOSED' === $item->status ? 'Clôturée' : 'En cours',
                'url'  => $this->urls->generate('clinic_consultation_details', ['id' => $item->consultationId]),
            ];
        }

        // The consultations still in progress first: that is where an ordonnance is written.
        usort($list, static fn (array $a, array $b): int => ['Clôturée' === $a['st'], $a['h']] <=> ['Clôturée' === $b['st'], $b['h']]);

        return $list;
    }

    /**
     * Status of the mock-up: the document's life cycle, an expired one apart.
     */
    public static function status(string $status, bool $expired): string
    {
        return match ($status) {
            'DRAFT'     => 'brou',
            'CANCELLED' => 'ann',
            default     => $expired ? 'exp' : 'emise',
        };
    }

    /**
     * The row's warning chips. The register only knows how tightly each
     * medicine is controlled: a narcotic outranks a controlled substance.
     *
     * @param list<string> $classes
     *
     * @return list<array{c: string, l: string}>
     */
    public static function flags(array $classes): array
    {
        if (\in_array('RX_NARCOTIC', $classes, true)) {
            return [['c' => 'cl-stup', 'l' => 'Stupéfiant']];
        }

        if (\in_array('RX_CONTROLLED', $classes, true)) {
            return [['c' => 'aic', 'l' => 'Substance contrôlée']];
        }

        return [];
    }

    public static function initials(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/[\s.\-_]+/u', $name, -1, \PREG_SPLIT_NO_EMPTY) ?: [],
            static fn (string $word): bool => !\in_array(mb_strtolower($word), ['dr', 'pr'], true),
        ));

        if (\count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[\count($words) - 1], 0, 1));
        }

        return mb_strtoupper(mb_substr($words[0] ?? $name, 0, 2));
    }

    /**
     * @param array<string, array{id: string, n: string, ini: string, c: string}> $team
     *
     * @return array<string, mixed>
     */
    private function row(PrescriptionListRow $row, array $team, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $at  = ($row->issuedAt ?? $row->createdAt)->setTimezone($tz);
        $vet = $team[$row->prescriberId] ?? [
            'id'  => $row->prescriberId,
            'n'   => $row->prescriberName,
            'ini' => self::initials($row->prescriberName),
            'c'   => self::FALLBACK_COLOR,
        ];

        return [
            'id'    => $row->id,
            'num'   => $row->number,
            'd'     => $at->format('Y-m-d'),
            'h'     => $at->format('H:i'),
            'a'     => ['n' => $row->patientName, 'e' => self::emoji($row->patientSpecies), 'prop' => $row->ownerName],
            'meds'  => array_map(static fn ($line): string => $line->label, $row->lines),
            'flags' => self::flags(array_map(static fn ($line): string => $line->prescriptionClass, $row->lines)),
            'vet'   => $vet,
            'st'    => self::status($row->status, $row->isExpiredAt($now)),
            'valid' => $row->validUntil?->setTimezone($tz)->format('Y-m-d'),
            'url'   => $this->urls->generate('clinic_prescription_details', ['prescriptionId' => $row->id]),
        ];
    }

    private function search(string $clinicId, SearchPrescriptionsCriteria $criteria): PrescriptionListItemView
    {
        $view = $this->queryBus->ask(new SearchPrescriptions($clinicId, $criteria));
        \assert($view instanceof PrescriptionListItemView);

        return $view;
    }

    /**
     * Animal name, species and owner of the day's patients, in one query —
     * the batching the consultations list uses.
     *
     * @param list<ConsultationListItemView> $items
     *
     * @return array<string, array{label: string, species: ?string, owner: ?string}>
     */
    private function patients(array $items): array
    {
        if ([] === $items) {
            return [];
        }

        $ids = [];

        foreach ($items as $item) {
            $ids[$item->patientId] = Uuid::fromString($item->patientId)->toBinary();
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(p.id) AS patient_id, p.display_label_value, se.species, se.search_owner_name
             FROM patient__patients p
             LEFT JOIN animal__search_entries se ON se.id = p.animal_link_id
             WHERE p.id IN (?)',
            [array_values($ids)],
            [ArrayParameterType::BINARY],
        );

        $patients = [];

        foreach ($rows as $row) {
            if (!\is_string($row['patient_id'])) {
                continue;
            }

            $patients[$row['patient_id']] = [
                'label'   => \is_string($row['display_label_value']) ? $row['display_label_value'] : '—',
                'species' => \is_string($row['species']) ? $row['species'] : null,
                'owner'   => \is_string($row['search_owner_name']) ? $row['search_owner_name'] : null,
            ];
        }

        return $patients;
    }
}
