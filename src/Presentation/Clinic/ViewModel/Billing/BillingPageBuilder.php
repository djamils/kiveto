<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetBillingStats\GetBillingStats;
use App\Context\Billing\Application\Query\GetCashDesk\CashDeskView;
use App\Context\Billing\Application\Query\GetCashDesk\GetCashDesk;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClients;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitContextBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Everything the « Facturation » page module starts from: the register's
 * first page and its filters, the header figures, the till of the day, the
 * letterhead of the paper preview, the clients the counter can sell to and —
 * on a document's own route — the sheet to open straight away.
 */
final readonly class BillingPageBuilder
{
    private const array DESK_METHODS = ['CARD' => 'cb', 'CASH' => 'esp', 'CHECK' => 'chq', 'TRANSFER' => 'vir'];

    private const int CLIENT_PAGE = 100;

    private const int MAX_CLIENT_PAGES = 5;

    public function __construct(
        private BillingRegisterViewBuilder $register,
        private BillingSheetBuilder $sheets,
        private CockpitContextBuilder $context,
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
        private ClockInterface $clock,
    ) {
    }

    public function timeZone(string $clinicId): \DateTimeZone
    {
        return new \DateTimeZone($this->context->letterhead($clinicId)['timeZone']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function invoiceSheet(string $clinicId, string $invoiceId): ?array
    {
        return $this->sheets->invoice($clinicId, $invoiceId, $this->register->team($clinicId), $this->timeZone($clinicId));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function creditNoteSheet(string $clinicId, string $creditNoteId): ?array
    {
        return $this->sheets->creditNote($clinicId, $creditNoteId, $this->register->team($clinicId), $this->timeZone($clinicId));
    }

    /**
     * The business day of the clinic, from its midnight to the next.
     *
     * @return array{date: string, start: \DateTimeImmutable, end: \DateTimeImmutable}
     */
    public function businessDay(string $clinicId): array
    {
        $utc   = new \DateTimeZone('UTC');
        $start = $this->clock->now()->setTimezone($this->timeZone($clinicId))->setTime(0, 0);

        return [
            'date'  => $start->format('Y-m-d'),
            'start' => $start->setTimezone($utc),
            'end'   => $start->modify('+1 day')->modify('-1 second')->setTimezone($utc),
        ];
    }

    /**
     * @param array<string, mixed>|null $sheet
     *
     * @return array<string, mixed>
     */
    public function page(string $clinicId, BillingRegisterFilters $filters, ?array $sheet = null, bool $autoPay = false): array
    {
        $letterhead = $this->context->letterhead($clinicId);
        $tz         = new \DateTimeZone($letterhead['timeZone']);
        $day        = $this->businessDay($clinicId);

        $stats = $this->queryBus->ask(new GetBillingStats(
            clinicId: $clinicId,
            from: $day['start'],
            to: $day['end'],
            overdueIssuedBefore: $this->clock->now()->modify(\sprintf('-%d days', BillingRegisterFilters::OVERDUE_AFTER_DAYS)),
        ));
        \assert($stats instanceof BillingStats);

        $desk = $this->queryBus->ask(new GetCashDesk($clinicId, $day['date'], $day['start'], $day['end']));
        \assert($desk instanceof CashDeskView);

        return [
            'today'      => $day['date'],
            'filters'    => $filters->toArray(),
            'register'   => $this->register->register($clinicId, $filters, $tz),
            'vierge'     => $this->register->isEmpty($clinicId),
            'team'       => array_values($this->register->team($clinicId)),
            'letterhead' => $letterhead,
            'kpi'        => [
                'enc'    => $stats->collectedMinorUnits,
                'du'     => $stats->outstandingMinorUnits,
                'retard' => $stats->overdueCount,
            ],
            'caisse'  => $this->cashDesk($desk),
            'clients' => $this->clients($clinicId),
            'sheet'   => $sheet,
            'autoPay' => $autoPay && null !== $sheet,
            'tokens'  => [
                'deposit' => $this->csrf->getToken('billing_cheque_deposit')->getValue(),
                'close'   => $this->csrf->getToken('billing_cash_closing')->getValue(),
                'counter' => $this->csrf->getToken('billing_counter_sale')->getValue(),
            ],
            'urls' => [
                'list'       => $this->urls->generate('clinic_invoices'),
                'search'     => $this->urls->generate('clinic_invoices_search'),
                'sheetFac'   => $this->urls->generate('clinic_invoice_sheet', ['invoiceId' => '00000000-0000-7000-8000-000000000000']),
                'sheetAv'    => $this->urls->generate('clinic_credit_note_sheet', ['creditNoteId' => '00000000-0000-7000-8000-000000000000']),
                'detailsFac' => $this->urls->generate('clinic_invoice_details', ['invoiceId' => '00000000-0000-7000-8000-000000000000']),
                'detailsAv'  => $this->urls->generate('clinic_credit_note_details', ['creditNoteId' => '00000000-0000-7000-8000-000000000000']),
                'selection'  => $this->urls->generate('clinic_invoices_selection_pdf'),
                'export'     => $this->urls->generate('clinic_invoices_export'),
                'articles'   => $this->urls->generate('clinic_counter_articles'),
                'counter'    => $this->urls->generate('clinic_counter_sale'),
                'deposit'    => $this->urls->generate('clinic_cheques_deposit'),
                'close'      => $this->urls->generate('clinic_cash_closing'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cashDesk(CashDeskView $desk): array
    {
        $expected = [];

        foreach (self::DESK_METHODS as $method => $mode) {
            $expected[$mode] = ($desk->takings->collectedByMethod[$method] ?? 0) - ($desk->takings->refundedByMethod[$method] ?? 0);
        }

        return [
            'attendu'  => $expected,
            'total'    => array_sum($expected),
            'cloture'  => null !== $desk->closing ? ['num' => $desk->closing->number] : null,
            'clotures' => $desk->closingCount,
            'cheques'  => ['n' => \count($desk->pendingCheques), 'total' => $desk->pendingChequesTotal()],
        ];
    }

    /**
     * The clinic's clients, for the counter sale's « Client » list.
     *
     * @return list<array{v: string, l: string}>
     */
    private function clients(string $clinicId): array
    {
        $list = [];

        // The client search pages by 100: read the clinic's file a page at a time.
        for ($page = 1; $page <= self::MAX_CLIENT_PAGES; ++$page) {
            $result = $this->queryBus->ask(new SearchClients(clinicId: $clinicId, status: 'active', page: $page, limit: self::CLIENT_PAGE));
            $items  = \is_array($result) && \is_array($result['items'] ?? null) ? $result['items'] : [];

            foreach ($items as $item) {
                if ($item instanceof ClientListItemView) {
                    $list[] = ['v' => $item->id, 'l' => $item->fullName()];
                }
            }

            if (\count($items) < self::CLIENT_PAGE) {
                break;
            }
        }

        return $list;
    }
}
