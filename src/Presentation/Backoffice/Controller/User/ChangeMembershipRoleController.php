<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use App\Presentation\Backoffice\User\UserActionRefusedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The role of the person in one clinic (« Accès aux cliniques »).
 */
final class ChangeMembershipRoleController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/memberships/{membershipId}/role', name: 'backoffice_user_membership_role', requirements: ['id' => ShowClinicController::UUID, 'membershipId' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id, string $membershipId): RedirectResponse
    {
        $account = $this->account($request, $id);
        $member  = $this->users->membership($id, $membershipId) ?? throw $this->createNotFoundException('Accès introuvable.');
        $role    = ClinicMemberRole::tryFrom($request->request->getString('role'));

        try {
            if (null === $role) {
                throw new UserActionRefusedException('Choisis un rôle.');
            }

            $this->users->changeRole($account, $member, $role);
        } catch (UserActionRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->back($id);
        }

        $this->addFlash('success', \sprintf('Rôle enregistré pour %s.', $member->clinicName));

        return $this->back($id);
    }
}
