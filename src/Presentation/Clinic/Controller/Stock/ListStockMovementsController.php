<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Query\StockOverview\ListMovements;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\MovementPage;
use App\Context\Inventory\Application\Query\StockOverview\MovementRow;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Presentation\Clinic\ViewModel\Stock\StockPageContext;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The trail: every movement of stock, newest first. It is append-only, so this
 * page only reads.
 */
#[Route(path: '/stock/movements', name: 'clinic_stock_movements', methods: ['GET'])]
final class ListStockMovementsController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly StockPageContext $context,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $types    = array_map(static fn (MovementType $type): string => $type->value, MovementType::cases());

        $page = $this->queryBus->ask(new ListMovements($clinicId, new MovementCriteria(
            types: $this->context->kinds($request, $types),
            // The trail holds article ids; searching by name is the catalogue's
            // job, and it answers with the ids to narrow on.
            articleIds: $this->context->articleIdsMatching($clinicId, mb_trim($request->query->getString('q'))),
            from: $this->date($request, 'from'),
            to: $this->date($request, 'to', endOfDay: true),
            page: max(1, $request->query->getInt('page', 1)),
            perPage: $this->context->perPage($request),
        )));

        \assert($page instanceof MovementPage);

        $articles = $this->context->articlesById($clinicId, array_map(
            static fn (MovementRow $row): string => $row->articleId,
            $page->items,
        ));

        return $this->render('clinic/stock/movements.html.twig', [
            'movements'     => $page->items,
            'articles'      => $articles,
            'page'          => $page,
            'types'         => $types,
            'selectedTypes' => $this->context->kinds($request, $types),
            'pageSizes'     => StockPageContext::PAGE_SIZES,
            'queryState'    => $this->context->queryState($request),
        ]);
    }

    private function date(Request $request, string $key, bool $endOfDay = false): ?\DateTimeImmutable
    {
        $raw = mb_trim($request->query->getString($key));

        if ('' === $raw) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        if (false === $date) {
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date;
    }
}
