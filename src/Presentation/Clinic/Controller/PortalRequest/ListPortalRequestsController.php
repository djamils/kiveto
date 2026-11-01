<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Demandes Portal »: what owners asked from the Portal (pet file changes,
 * prescription renewals), the ones to answer first. Questionnaires are not
 * queue items. Needs `manage_animals`.
 */
#[Route('/portal-requests', name: 'clinic_portal_requests', methods: ['GET'])]
final class ListPortalRequestsController extends AbstractController
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

        // No status asked: the requests waiting for an answer.
        $status = $request->query->has('statut') ? $request->query->getString('statut') : 'submitted';
        $kind   = $request->query->getString('type');
        $cursor = $request->query->getString('apres');

        return $this->render('clinic/portal_requests/index.html.twig', [
            'queue' => $this->pages->list(
                $clinicId->toString(),
                '' === $status ? null : $status,
                '' === $kind ? null : $kind,
                '' === $cursor ? null : $cursor,
            ),
        ]);
    }
}
