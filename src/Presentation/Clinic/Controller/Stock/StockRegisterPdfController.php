<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Inventory\Application\Query\StockOverview\ListMovements;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\MovementPage;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Presentation\Clinic\ViewModel\Stock\StockArticles;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockMovementPresenter;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Exporter le registre (PDF) »: every entry and exit of the clinic's
 * narcotics, product by product and in the order they happened, with the
 * balance after each one — the register an inspection asks for, kept ten
 * years.
 */
#[Route(path: '/stock/register.pdf', name: 'clinic_stock_register_pdf', methods: ['GET'])]
final readonly class StockRegisterPdfController
{
    private const int WHOLE = 10000;

    public function __construct(
        private QueryBusInterface $queryBus,
        private StockEndpoint $endpoint,
        private StockArticles $articles,
        private StockMovementPresenter $presenter,
        private DocumentPdfFactory $documents,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(): Response
    {
        $clinicId  = $this->endpoint->clinicId();
        $articles  = $this->articles->byId($clinicId);
        $narcotics = $this->articles->narcotics($clinicId);

        $page = $this->queryBus->ask(new ListMovements($clinicId, new MovementCriteria(
            articleIds: $narcotics,
            perPage: self::WHOLE,
        )));
        \assert($page instanceof MovementPage);

        $staff    = $this->presenter->staff($clinicId);
        $products = [];

        foreach (array_reverse($page->items) as $row) {
            $article = $articles[$row->articleId] ?? null;

            $products[$row->articleId] ??= [
                'name'  => null !== $article ? $article->name : 'Article',
                'code'  => null !== $article ? $article->code : '',
                'unit'  => $row->unit,
                'lines' => [],
            ];
            $products[$row->articleId]['lines'][] = $this->presenter->row($row, $staff);
        }

        uasort($products, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $clinic = $this->queryBus->ask(new GetClinic($clinicId));
        $clinic = $clinic instanceof ClinicDto ? $clinic : null;
        $today  = $this->clock->now();

        $pdf = $this->documents->render(
            'clinic/document/narcotics_register.html.twig',
            ['products' => array_values($products), 'printedAt' => $today],
            $clinic,
            'Registre des stupéfiants',
        );

        return $this->documents->response($pdf, 'registre-stupefiants-' . $today->format('Y-m-d'));
    }
}
