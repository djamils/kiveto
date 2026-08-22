<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Query\GetActiveAlerts\GetActiveAlerts;
use App\Context\Inventory\Application\Query\GetExpiringLots\GetExpiringLots;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Presentation\Clinic\ViewModel\Stock\StockPageContext;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What needs attention: what is out, what is below its threshold, and what is
 * about to expire.
 */
#[Route(path: '/stock/alerts', name: 'clinic_stock_alerts', methods: ['GET'])]
final class ListStockAlertsController extends AbstractController
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
        $severity = mb_strtoupper(mb_trim($request->query->getString('severity')));

        $raw = $this->queryBus->ask(new GetActiveAlerts(
            clinicId: $clinicId,
            severity: \in_array($severity, ['CRITICAL', 'WARNING', 'INFO'], true) ? $severity : null,
        ));

        $alerts = array_values(array_filter(
            \is_array($raw) ? $raw : [],
            static fn ($alert): bool => $alert instanceof StockAlertView,
        ));

        $articles = $this->context->articlesById($clinicId, array_map(
            static fn (StockAlertView $alert): string => $alert->articleId->toString(),
            $alerts,
        ));

        $expiring = $this->queryBus->ask(new GetExpiringLots(
            clinicId: $clinicId,
            withinDays: StockPageContext::EXPIRING_WITHIN_DAYS,
        ));

        return $this->render('clinic/stock/alerts.html.twig', [
            'alerts'             => $alerts,
            'articles'           => $articles,
            'expiring'           => \is_array($expiring) ? $expiring : [],
            'severity'           => '' !== $severity ? $severity : null,
            'expiringWithinDays' => StockPageContext::EXPIRING_WITHIN_DAYS,
        ]);
    }
}
