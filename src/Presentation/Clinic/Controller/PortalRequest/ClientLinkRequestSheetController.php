<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The side sheet of one « Rattachement » (nothing pending, or another clinic's, is not found) of the « Demandes » register, as
 * JSON. Another clinic's request is not found. Needs `manage_animals`.
 */
#[Route(
    path: '/portal-requests/rattachements/{userId}/sheet',
    name: 'clinic_portal_link_request_sheet',
    requirements: ['userId' => Requirement::UUID],
    methods: ['GET'],
)]
final class ClientLinkRequestSheetController extends AbstractController
{
    public function __construct(
        private readonly PortalRequestPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(string $userId): JsonResponse
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $sheet = $this->pages->linkSheet($clinicId->toString(), $userId);

        if (null === $sheet) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        return new JsonResponse($sheet);
    }
}
