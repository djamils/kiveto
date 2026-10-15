<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Team\TeamPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Who works here: the team page (equipe-v-topbar_5) — members, their roles and
 * contracts, and the role map. The page module draws it from the payload and
 * posts every change in the background.
 */
#[Route(path: '/settings/team', name: 'clinic_settings_team', methods: ['GET'])]
final class ListClinicTeamController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly TeamPageBuilder $pages,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        return $this->render('clinic/settings/team.html.twig', [
            'payload' => $this->pages->page($clinicId->toString()),
        ]);
    }
}
