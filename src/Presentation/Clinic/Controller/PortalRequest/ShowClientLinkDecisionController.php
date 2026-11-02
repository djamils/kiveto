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
 * The « Demandes » page with one answered « Rattachement » open in the side
 * sheet: the files the clinic confirmed or rejected, who answered and when.
 * Another clinic's decision is not found. Needs `manage_animals`.
 */
#[Route('/portal-requests/rattachements/decisions/{id}', name: 'clinic_portal_link_decisions_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ShowClientLinkDecisionController extends AbstractPortalRequestPage
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

        $sheet = $this->pages->linkDecisionSheet($clinicId->toString(), $id);

        if (null === $sheet) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        return $this->renderPage($this->pages, $request, $clinicId->toString(), $sheet);
    }
}
