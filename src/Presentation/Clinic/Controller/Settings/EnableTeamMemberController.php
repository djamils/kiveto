<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\EnableClinicMembership\EnableClinicMembership;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Le membre a de nouveau accès à la clinique.
 *
 * The membership is kept either way: what the member did stays attached to them.
 */
#[Route(path: '/settings/team/members/{membershipId}/enable', name: 'clinic_settings_team_enable', methods: ['POST'])]
final class EnableTeamMemberController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(string $membershipId): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        try {
            $this->commandBus->dispatch(new EnableClinicMembership(membershipId: $membershipId));
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('clinic_settings_team');
        }

        $this->addFlash('success', 'Le membre a de nouveau accès à la clinique.');

        return $this->redirectToRoute('clinic_settings_team');
    }
}
