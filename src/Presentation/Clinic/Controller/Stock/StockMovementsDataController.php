<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Query\StockOverview\ListMovements;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\MovementPage;
use App\Presentation\Clinic\ViewModel\Stock\StockArticles;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockMovementPresenter;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One page of the trail, as the « Mouvements » tab asks for it: by sense,
 * over a period, narrowed on the register of controlled substances or on what
 * was searched (an article, a lot, a reference), newest first.
 */
#[Route(path: '/stock/movements/data', name: 'clinic_stock_movements_data', methods: ['GET'])]
final readonly class StockMovementsDataController
{
    private const array TYPES = ['in' => 'IN', 'out' => 'OUT', 'adj' => 'ADJUSTMENT'];

    private const array PAGE_SIZES = [5, 25, 50, 100];

    public function __construct(
        private QueryBusInterface $queryBus,
        private StockEndpoint $endpoint,
        private StockArticles $articles,
        private StockMovementPresenter $presenter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $clinicId = $this->endpoint->clinicId();
        $type     = self::TYPES[$request->query->getString('t')] ?? null;
        $period   = $request->query->getString('per', '30');
        $term     = mb_trim($request->query->getString('q'));
        $perPage  = $request->query->getInt('pp', 25);
        $lotId    = mb_trim($request->query->getString('lot'));

        $page = $this->queryBus->ask(new ListMovements($clinicId, new MovementCriteria(
            types: null !== $type ? [$type] : [],
            reasons: '1' === $request->query->getString('cons') ? ['CONSUMPTION_CONSULTATION'] : [],
            articleIds: '1' === $request->query->getString('reg') ? $this->articles->narcotics($clinicId) : null,
            from: \in_array($period, ['7', '30', '90'], true)
                ? $this->clock->now()->setTime(0, 0)->modify(\sprintf('-%d days', (int) $period))
                : null,
            page: max(1, $request->query->getInt('page', 1)),
            perPage: \in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25,
            term: '' !== $term ? $term : null,
            termArticleIds: '' !== $term ? $this->articles->matching($clinicId, $term) : [],
            lotId: 1 === preg_match('/^[0-9a-f-]{36}$/i', $lotId) ? $lotId : null,
        )));

        \assert($page instanceof MovementPage);

        $staff = $this->presenter->staff($clinicId);

        return new JsonResponse([
            'rows'  => array_map(fn ($row): array => $this->presenter->row($row, $staff), $page->items),
            'total' => $page->total,
            'page'  => $page->page,
        ]);
    }
}
