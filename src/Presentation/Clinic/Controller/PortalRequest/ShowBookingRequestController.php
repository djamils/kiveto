<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Context\ClientPortal\Application\Query\GetBookingRequest\GetBookingRequest;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ClinicBookingRequest;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * One « Nouveau client » request: a Portal account asking for a first
 * appointment through the clinic directory. The profile name, phone, new
 * pet and slot; the clinic's files whose mobile is that phone, with their
 * animals and the « Nom différent » / « Déjà relié à un autre compte
 * Portal » badges; then « Confirmer » (an existing file or a new one, an
 * animal of it or the new pet) or « Refuser ». Another clinic's request is
 * not found. Needs `manage_animals`.
 */
#[Route('/portal-requests/nouveaux-clients/{id}', name: 'clinic_portal_booking_requests_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ShowBookingRequestController extends AbstractController
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

        $request = $this->queryBus->ask(new GetBookingRequest($clinicId->toString(), $id));

        if (!$request instanceof ClinicBookingRequest) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        return $this->render('clinic/portal_requests/booking_request.html.twig', [
            'request' => $this->pages->bookingDetail($request),
        ]);
    }
}
