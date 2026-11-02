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
 * The « Demandes » page with one « Nouveau client » request open in the
 * side sheet: a Portal account asking for a first appointment through the
 * clinic directory. The profile name, phone, new pet and slot; the
 * clinic's files whose mobile is that phone, with their animals and the
 * « Nom différent » / « Déjà relié à un autre compte Portal » badges; then
 * « Confirmer » (an existing file or a new one, an animal of it or the new
 * pet) or « Refuser ». Another clinic's request is not found. Needs
 * `manage_animals`.
 */
#[Route('/portal-requests/nouveaux-clients/{id}', name: 'clinic_portal_booking_requests_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ShowBookingRequestController extends AbstractPortalRequestPage
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

        $sheet = $this->pages->bookingSheet($clinicId->toString(), $id);

        if (null === $sheet) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        return $this->renderPage($this->pages, $request, $clinicId->toString(), $sheet);
    }
}
