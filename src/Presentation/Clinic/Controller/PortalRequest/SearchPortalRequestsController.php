<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestRegisterFilters;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One page of the « Demandes » register for the status segments, the type
 * menu, the search field and the pager of the page module, with the
 * counters, as JSON. Needs `manage_animals`.
 */
#[Route('/portal-requests/search', name: 'clinic_portal_requests_search', methods: ['GET'])]
final class SearchPortalRequestsController extends AbstractController
{
    public function __construct(
        private readonly PortalRequestPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        return new JsonResponse($this->pages->register($clinicId->toString(), PortalRequestRegisterFilters::fromRequest($request)));
    }
}
