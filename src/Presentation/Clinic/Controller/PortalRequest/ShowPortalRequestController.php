<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Context\ClientPortal\Application\Query\GetPortalRequest\GetPortalRequest;
use App\Context\ClientPortal\Application\Query\GetPortalRequest\PortalRequestDetails;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * One Portal request: what the owner asked, the values side by side for a
 * change (at the request, now, proposed), and « Traiter » / « Refuser » while
 * it waits. The bell entry `PORTAL_REQUEST` opens it. Another clinic's
 * request is not found.
 */
#[Route('/portal-requests/{id}', name: 'clinic_portal_requests_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ShowPortalRequestController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly PortalRequestPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $request = $this->queryBus->ask(new GetPortalRequest($clinicId->toString(), $id));

        if (!$request instanceof PortalRequestDetails) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $isRenewal = 'prescription_renewal' === $request->kind;

        return $this->render('clinic/portal_requests/show.html.twig', [
            'request'    => $this->pages->detail($request, $clinicId->toString()),
            'canResolve' => !$isRenewal || $this->guard->has(ClinicPermissionGuard::CREATE_PRESCRIPTION),
        ]);
    }
}
