<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Audit;

use App\Context\Clinic\Application\Query\Clinic\ListClinics\ClinicCollection;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ListClinics;
use App\Presentation\Backoffice\ViewModel\Audit\AuditLogFilters;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Application\Query\SearchAuditLog\AuditLogPage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * H1 — the audit log: newest first, 12 per page, filtered by category,
 * clinic, kind of actor, account, period and text.
 */
final class AuditLogController extends AbstractController
{
    private const int PER_PAGE = 12;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/audit', name: 'backoffice_audit', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $filters = AuditLogFilters::fromRequest($request);

        $page = $this->queryBus->ask($filters->search(self::PER_PAGE));
        \assert($page instanceof AuditLogPage);

        $clinics = $this->queryBus->ask(new ListClinics());
        \assert($clinics instanceof ClinicCollection);

        $clinicNames = [];
        foreach ($clinics->clinics as $clinic) {
            $clinicNames[$clinic->id] = $clinic->name;
        }
        asort($clinicNames);

        return $this->render('backoffice/console/audit/index.html.twig', [
            'page'        => $page,
            'filters'     => $filters,
            'clinicNames' => $clinicNames,
            'categories'  => AuditLogFilters::CATEGORIES,
            'actors'      => AuditLogFilters::ACTORS,
            'periods'     => AuditLogFilters::PERIODS,
        ]);
    }
}
