<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Context\ClientPortal\Application\Query\GetClinicLinkRequest\GetClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * One « Rattachement » request: the Portal account asking to be linked to
 * client files of the clinic (an accepted link suggestion), the profile name
 * next to each file's name and animals, the « Nom différent » and « Déjà
 * relié à un autre compte Portal » badges, then « Confirmer » / « Refuser ».
 * The bell entry opens it. Nothing pending (answered, another clinic's) is
 * not found. Needs `manage_animals`.
 */
#[Route('/portal-requests/rattachements/{userId}', name: 'clinic_portal_link_requests_show', requirements: ['userId' => Requirement::UUID], methods: ['GET'])]
final class ShowClientLinkRequestController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly PortalRequestPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $request = $this->queryBus->ask(new GetClinicLinkRequest($clinicId->toString(), $userId));

        if (!$request instanceof ClinicLinkRequest) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        return $this->render('clinic/portal_requests/client_link.html.twig', [
            'request' => $this->pages->linkDetail($request),
        ]);
    }
}
