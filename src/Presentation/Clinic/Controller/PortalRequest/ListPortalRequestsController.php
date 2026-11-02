<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Demandes »: what owners asked from the Portal (pet file changes,
 * prescription renewals, « Rattachement » and « Nouveau client » requests),
 * the ones to answer first. Questionnaires are not queue items. Filters
 * `statut`, `type`, `q`, `page`, `pp`. Needs `manage_animals`.
 */
#[Route('/portal-requests', name: 'clinic_portal_requests', methods: ['GET'])]
final class ListPortalRequestsController extends AbstractPortalRequestPage
{
    public function __construct(
        private readonly PortalRequestPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        return $this->renderPage($this->pages, $request, $clinicId->toString());
    }
}
