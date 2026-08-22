<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Query\GetBillingStats\GetBillingStats;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListItemView;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoices;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/invoices', name: 'clinic_invoices', methods: ['GET'])]
final class ListInvoicesController extends AbstractController
{
    private const array PAGE_SIZES = [10, 25, 50, 100];

    private const int DEFAULT_PAGE_SIZE = 25;

    private const string PERIOD_TODAY = 'today';
    private const string PERIOD_WEEK  = 'week';
    private const string PERIOD_MONTH = 'month';
    private const string PERIOD_YEAR  = 'year';

    /**
     * Days of history each period keeps, counted back from today's midnight.
     */
    private const array PERIOD_DAYS = [
        self::PERIOD_TODAY => 0,
        self::PERIOD_WEEK  => 7,
        self::PERIOD_MONTH => 30,
        self::PERIOD_YEAR  => 365,
    ];

    private const array SORTS = [
        SearchInvoicesCriteria::SORT_DATE,
        SearchInvoicesCriteria::SORT_NUMBER,
        SearchInvoicesCriteria::SORT_PAYER,
        SearchInvoicesCriteria::SORT_AMOUNT,
        SearchInvoicesCriteria::SORT_STATUS,
    ];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        $term     = mb_trim($request->query->getString('q'));
        $period   = $this->period($request);
        $statuses = $this->statuses($request);
        $sort     = $this->sort($request);
        $dir      = 'asc' === $request->query->getString('dir') ? 'asc' : 'desc';
        $perPage  = $this->perPage($request);
        $page     = max(1, $request->query->getInt('page', 1));

        $criteria = new SearchInvoicesCriteria(
            term: '' !== $term ? $term : null,
            statuses: $statuses,
            issuedFrom: null !== $period ? $this->periodStart($period) : null,
            issuedTo: null,
            payerClientId: null,
            sortBy: $sort,
            sortDescending: 'desc' === $dir,
            page: $page,
            perPage: $perPage,
        );

        $view = $this->queryBus->ask(new SearchInvoices($clinicId, $criteria));
        \assert($view instanceof InvoiceListItemView);

        // The header figures always read "today", whatever the list is filtered
        // on: they answer "what has the desk taken in", not "what is on screen".
        $stats = $this->queryBus->ask(new GetBillingStats(
            clinicId: $clinicId,
            from: $this->periodStart(self::PERIOD_TODAY),
            to: $this->clock->now(),
        ));

        $total     = $view->total;
        $firstItem = 0 === $total ? 0 : ($page - 1) * $perPage + 1;
        $lastItem  = min($total, $page * $perPage);

        return $this->render('clinic/billing/list/index.html.twig', [
            'invoices'   => $view->items,
            'stats'      => $stats,
            'statuses'   => InvoiceStatus::cases(),
            'pageSizes'  => self::PAGE_SIZES,
            'pagination' => [
                'page'       => $page,
                'limit'      => $perPage,
                'totalItems' => $total,
                'pageCount'  => $view->pageCount(),
                'firstItem'  => $firstItem,
                'lastItem'   => $lastItem,
            ],
            'filters' => [
                'q'      => $term,
                'period' => $period,
                'status' => $statuses,
            ],
            'sort'      => $sort,
            'direction' => $dir,
        ]);
    }

    /**
     * @return list<string>
     */
    private function statuses(Request $request): array
    {
        /** @var array<int, mixed> $raw */
        $raw   = $request->query->all('status');
        $valid = array_map(static fn (InvoiceStatus $status): string => $status->value, InvoiceStatus::cases());

        $statuses = [];

        foreach ($raw as $value) {
            if (\is_string($value) && \in_array($value, $valid, true) && !\in_array($value, $statuses, true)) {
                $statuses[] = $value;
            }
        }

        return $statuses;
    }

    private function period(Request $request): ?string
    {
        $period = $request->query->getString('period');

        return \array_key_exists($period, self::PERIOD_DAYS) ? $period : null;
    }

    private function periodStart(string $period): \DateTimeImmutable
    {
        $midnight = $this->clock->now()->setTime(0, 0);
        $days     = self::PERIOD_DAYS[$period] ?? 0;

        return 0 === $days ? $midnight : $midnight->modify(\sprintf('-%d days', $days));
    }

    private function sort(Request $request): string
    {
        $sort = $request->query->getString('sort');

        return \in_array($sort, self::SORTS, true) ? $sort : SearchInvoicesCriteria::SORT_DATE;
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->query->getInt('per_page', self::DEFAULT_PAGE_SIZE);

        return \in_array($perPage, self::PAGE_SIZES, true) ? $perPage : self::DEFAULT_PAGE_SIZE;
    }
}
