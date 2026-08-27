<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipAdministration\ChangeClinicMembershipAdministration;
use App\Context\Clinic\Application\Exception\LastClinicAdministratorException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Le membre ne gère plus la clinique.
 *
 * Refused when it would leave the clinic with nobody able to open its settings.
 * That refusal is a RuntimeException, so it needs its own catch: the
 * DomainException clause below would let it through as a 500.
 */
#[Route(
    path: '/settings/team/members/{membershipId}/administration/revoke',
    name: 'clinic_settings_team_administration_revoke',
    methods: ['POST'],
)]
final class RevokeTeamAdministrationController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(string $membershipId, Request $request): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        if (!$this->isCsrfTokenValid('team_administration', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        try {
            $this->commandBus->dispatch(new ChangeClinicMembershipAdministration(
                clinicId: $clinicId->toString(),
                membershipId: $membershipId,
                isClinicAdministrator: false,
            ));
        } catch (LastClinicAdministratorException) {
            $this->addFlash('error', 'Impossible : plus personne ne pourrait gérer la clinique.');

            return $this->redirectToRoute('clinic_settings_team');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('clinic_settings_team');
        } catch (\InvalidArgumentException) {
            // Either the membership is gone, or it belongs to another clinic —
            // the handler throws the same type for both, as its five siblings do.
            // Both mean the same thing to this page, and neither should echo the
            // ids back.
            $this->addFlash('error', 'Ce membre ne fait pas partie de cette clinique.');

            return $this->redirectToRoute('clinic_settings_team');
        }

        $this->addFlash('success', 'Ce membre ne gère plus la clinique.');

        return $this->redirectToRoute('clinic_settings_team');
    }
}
