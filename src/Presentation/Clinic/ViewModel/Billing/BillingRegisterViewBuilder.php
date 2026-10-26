<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListItemView;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoices;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Presentation\Clinic\ViewModel\Prescription\PrescriptionRegisterViewBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The register of invoices and credit notes (facturation-v-topbar_3): one page
 * of rows as the page module draws them.
 *
 * A row is a document of the Billing context — its number, payer, amount and
 * what is left to pay — dressed with what its visit says: the animal, the
 * practitioner, the consultation's motifs.
 */
final readonly class BillingRegisterViewBuilder
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private BillingVisitReader $visits,
        private PrescriptionRegisterViewBuilder $prescriptions,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
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
     * One page of the register for the filters.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pp: int}
     */
    public function register(string $clinicId, BillingRegisterFilters $filters, \DateTimeZone $tz): array
    {
        $today = $this->clock->now()->setTimezone($tz)->setTime(0, 0);
        $vet   = '' !== $filters->vet ? $this->visits->admissionsOf($clinicId, $filters->vet) : null;
        $term  = '' !== $filters->q ? $this->visits->admissionsMatching($clinicId, $filters->q) : [];
        $view  = $this->search($clinicId, $filters->criteria($today, $vet, $term));

        // A page past the end (the list shrank under a filter) falls back on the last one.
        if ([] === $view->items && $view->total > 0 && $filters->page > $view->pageCount()) {
            $view = $this->search($clinicId, $filters->criteria($today, $vet, $term, $view->pageCount()));
        }

        return [
            'rows'  => $this->rows($clinicId, $view->items, $tz),
            'total' => $view->total,
            'page'  => $view->page,
            'pp'    => $view->perPage,
        ];
    }

    /**
     * Whether the clinic has billed anything yet: a register empty under its
     * filters reads differently from one that never held a document.
     */
    public function isEmpty(string $clinicId): bool
    {
        return 0 === $this->search($clinicId, new SearchInvoicesCriteria(
            perPage: 1,
            kinds: [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE],
        ))->total;
    }

    /**
     * @param list<InvoiceListRow> $items
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $clinicId, array $items, \DateTimeZone $tz): array
    {
        $admissions = array_values(array_unique(array_filter(array_map(static fn (InvoiceListRow $row): ?string => $row->admissionId, $items))));
        $visits     = $this->visits->visits($admissions);
        $team       = $this->team($clinicId);
        $now        = $this->clock->now();

        return array_map(function (InvoiceListRow $row) use ($visits, $team, $tz, $now): array {
            $visit = null !== $row->admissionId ? ($visits[$row->admissionId] ?? null) : null;
            $at    = ($row->issuedAt ?? $row->createdAt)->setTimezone($tz);
            $note  = SearchInvoicesCriteria::KIND_CREDIT_NOTE === $row->kind;

            return [
                'id'    => $row->id,
                'kind'  => $note ? 'av' : 'fac',
                'num'   => $row->number,
                'd'     => $at->format('Y-m-d'),
                'h'     => $at->format('H:i'),
                'cli'   => ['n' => $row->payerName, 'passage' => null === $row->payerClientId],
                'anis'  => null !== $visit ? [self::animal($visit['animal'])] : [],
                'cpt'   => $row->counterSalePayer,
                'objet' => $note
                    ? \sprintf('Avoir sur %s · %s', $row->originInvoiceNumber ?? '—', $row->reason ?? '')
                    : self::objet($visit, $row->admissionId),
                'vet'    => null !== $visit && null !== $visit['vetId'] ? ($team[$visit['vetId']] ?? null) : null,
                'st'     => $note ? 'av' : self::status($row->status),
                'tot'    => $row->totalGrossMinorUnits,
                'du'     => max(0, $row->balanceDueMinorUnits),
                'retard' => $note ? 0 : self::overdueDays($row, $now),
                'tx'     => TransmissionBadge::of($row->transmission),
                'url'    => $this->url($row),
            ];
        }, $items);
    }

    /**
     * Status of the mock-up: the document's life, credit notes apart.
     */
    public static function status(string $status): string
    {
        return match ($status) {
            'DRAFT'     => 'brou',
            'CREDITED'  => 'annav',
            'CANCELLED' => 'ann',
            default     => 'emise',
        };
    }

    /**
     * Days an unpaid invoice of a private client has waited, once past the
     * 30 days it is given; 0 while it is not overdue.
     */
    public static function overdueDays(InvoiceListRow $row, \DateTimeImmutable $now): int
    {
        if ($row->balanceDueMinorUnits <= 0 || null === $row->issuedAt || !\in_array($row->status, ['ISSUED', 'PARTIALLY_PAID'], true)) {
            return 0;
        }

        $days = (int) floor(($now->getTimestamp() - $row->issuedAt->getTimestamp()) / 86400);

        return $days > BillingRegisterFilters::OVERDUE_AFTER_DAYS ? $days : 0;
    }

    /**
     * @param array{id: ?string, name: string, species: ?string} $animal
     *
     * @return array{id: ?string, n: string, e: string}
     */
    public static function animal(array $animal): array
    {
        return ['id' => $animal['id'], 'n' => $animal['name'], 'e' => PrescriptionRegisterViewBuilder::emoji($animal['species'])];
    }

    /**
     * What the visit was for: its motifs, else its complaint; a sale without a
     * visit is the counter's.
     *
     * @param array{motifs: list<string>, complaint: ?string}|null $visit
     */
    public static function objet(?array $visit, ?string $admissionId): string
    {
        if (null === $admissionId) {
            return 'Vente au comptoir';
        }

        if (null === $visit) {
            return 'Visite';
        }

        if ([] !== $visit['motifs']) {
            return implode(' · ', $visit['motifs']);
        }

        return null !== $visit['complaint'] && '' !== $visit['complaint'] ? $visit['complaint'] : 'Consultation';
    }

    private function url(InvoiceListRow $row): string
    {
        return SearchInvoicesCriteria::KIND_CREDIT_NOTE === $row->kind
            ? $this->urls->generate('clinic_credit_note_details', ['creditNoteId' => $row->id])
            : $this->urls->generate('clinic_invoice_details', ['invoiceId' => $row->id]);
    }

    private function search(string $clinicId, SearchInvoicesCriteria $criteria): InvoiceListItemView
    {
        $view = $this->queryBus->ask(new SearchInvoices($clinicId, $criteria));
        \assert($view instanceof InvoiceListItemView);

        return $view;
    }
}
