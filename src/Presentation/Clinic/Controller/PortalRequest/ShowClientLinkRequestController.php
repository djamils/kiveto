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
 * The « Demandes » page with one « Rattachement » open in the side sheet:
 * the Portal account asking to be linked to client files of the clinic (an
 * accepted link suggestion), the profile name next to each file's name and
 * animals, the « Nom différent » and « Déjà relié à un autre compte Portal »
 * badges, then « Confirmer » / « Refuser ». The bell entry opens it.
 * Nothing pending (answered, another clinic's) is not found. Needs
 * `manage_animals`.
 */
#[Route('/portal-requests/rattachements/{userId}', name: 'clinic_portal_link_requests_show', requirements: ['userId' => Requirement::UUID], methods: ['GET'])]
final class ShowClientLinkRequestController extends AbstractPortalRequestPage
{
    public function __construct(
        private readonly PortalRequestPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(string $userId, Request $request): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $sheet = $this->pages->linkSheet($clinicId->toString(), $userId);

        if (null === $sheet) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        return $this->renderPage($this->pages, $request, $clinicId->toString(), $sheet);
    }
}
