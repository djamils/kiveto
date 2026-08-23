<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipEngagement\ChangeClinicMembershipEngagement;
use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole\ChangeClinicMembershipRole;
use App\Context\Clinic\Application\Command\Staff\UpdateStaffProfilePhone\UpdateStaffProfilePhone;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What changes about a member over time: the role they hold, the contract they
 * are on, the number they are reached at.
 */
#[Route(path: '/settings/team/members/{membershipId}', name: 'clinic_settings_team_update', methods: ['POST'])]
final class UpdateTeamMemberController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(Request $request, string $membershipId): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        $role       = ClinicMemberRole::tryFrom($request->request->getString('role'));
        $engagement = ClinicMembershipEngagement::tryFrom($request->request->getString('engagement'));
        $profileId  = mb_trim($request->request->getString('profileId'));

        try {
            if (null !== $role) {
                $this->commandBus->dispatch(new ChangeClinicMembershipRole(
                    membershipId: $membershipId,
                    role: $role,
                ));
            }

            if (null !== $engagement) {
                $this->commandBus->dispatch(new ChangeClinicMembershipEngagement(
                    membershipId: $membershipId,
                    engagement: $engagement,
                ));
            }

            if ('' !== $profileId) {
                $phone = mb_trim($request->request->getString('phone'));

                $this->commandBus->dispatch(new UpdateStaffProfilePhone(
                    profileId: $profileId,
                    phone: '' !== $phone ? $phone : null,
                ));
            }
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('clinic_settings_team');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('clinic_settings_team');
        }

        $this->addFlash('success', 'Membre mis à jour.');

        return $this->redirectToRoute('clinic_settings_team');
    }
}
