<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\DisableClinicMembership\DisableClinicMembership;
use App\Context\Clinic\Application\Exception\LastClinicAdministratorException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Le membre n’a plus accès à la clinique.
 *
 * The membership is kept either way: what the member did stays attached to them.
 */
#[Route(path: '/settings/team/members/{membershipId}/disable', name: 'clinic_settings_team_disable', methods: ['POST'])]
final class DisableTeamMemberController extends AbstractController
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
            $this->commandBus->dispatch(new DisableClinicMembership(membershipId: $membershipId));
        } catch (LastClinicAdministratorException) {
            // Extends RuntimeException per the folder convention, so it falls
            // straight through the clause below and would become a 500.
            $this->addFlash('error', 'Impossible : plus personne ne pourrait gérer la clinique.');

            return $this->redirectToRoute('clinic_settings_team');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('clinic_settings_team');
        }

        $this->addFlash('success', 'Le membre n’a plus accès à la clinique.');

        return $this->redirectToRoute('clinic_settings_team');
    }
}
