<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use App\Presentation\Backoffice\User\UserActionRefusedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Retirer »: the person leaves one clinic.
 */
final class RemoveMembershipController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/memberships/{membershipId}/remove', name: 'backoffice_user_membership_remove', requirements: ['id' => ShowClinicController::UUID, 'membershipId' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id, string $membershipId): RedirectResponse
    {
        $account = $this->account($request, $id);
        $member  = $this->users->membership($id, $membershipId) ?? throw $this->createNotFoundException('Accès introuvable.');

        try {
            $this->users->remove($account, $member);
        } catch (UserActionRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->back($id);
        }

        $this->addFlash('success', \sprintf('Accès à %s retiré.', $member->clinicName));

        return $this->back($id);
    }
}
