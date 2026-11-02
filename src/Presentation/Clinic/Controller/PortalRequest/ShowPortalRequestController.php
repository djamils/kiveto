<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The « Demandes » page with one change or renewal open in the side sheet:
 * what the owner asked, the values side by side for a change (on file,
 * proposed), and « Traiter » / « Refuser » while it waits. The bell entry
 * `PORTAL_REQUEST` and the dashboard open it. Another clinic's request is
 * not found. Needs `manage_animals`.
 */
#[Route('/portal-requests/{id}', name: 'clinic_portal_requests_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ShowPortalRequestController extends AbstractPortalRequestPage
{
    public function __construct(
        private readonly PortalRequestPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $sheet = $this->pages->requestSheet($clinicId->toString(), $id, $this->guard->has(ClinicPermissionGuard::CREATE_PRESCRIPTION));

        if (null === $sheet) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        return $this->renderPage($this->pages, $request, $clinicId->toString(), $sheet);
    }
}
